<?php

namespace App\Support\Api\Admin;

use App\Models\Speaker;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * @phpstan-type AdminResourceMeta array{
 *   key: string,
 *   resource_class: class-string<Resource>,
 *   model_class: class-string<Model>,
 *   model_label: string,
 *   plural_model_label: string,
 *   navigation_group: string|null,
 *   record_title_attribute: string|null,
 *   pages: array<string, bool>,
 *   relations: list<string>,
 *   abilities: array<string, bool>,
 *   write_support: array<string, bool>,
 *   api_routes: array<string, string|null>,
 *   panel_routes: array<string, string|null>
 * }
 */
class AdminResourceRegistry
{
    public function __construct(
        private readonly AdminResourceMutationService $mutationService,
    ) {}

    /**
     * @return list<class-string<resource>>
     */
    public function resources(): array
    {
        /** @var array<int|string, class-string<resource>> $resources */
        $resources = Filament::getPanel('admin')->getResources();

        return array_values($resources);
    }

    /**
     * @return list<class-string<resource>>
     */
    public function accessibleResources(): array
    {
        return array_values(array_filter(
            $this->resources(),
            $this->canAccessResource(...),
        ));
    }

    public function resolve(string $resourceKey): ?string
    {
        foreach ($this->resources() as $resourceClass) {
            if ($this->keyFor($resourceClass) === $resourceKey) {
                return $resourceClass;
            }
        }

        return null;
    }

    public function canAccessResource(string $resourceClass): bool
    {
        return $this->canViewAny($resourceClass)
            || $this->canCreate($resourceClass)
            || $resourceClass::canDeleteAny()
            || $resourceClass::canForceDeleteAny()
            || $resourceClass::canRestoreAny()
            || $resourceClass::canReorder();
    }

    /**
     * @return AdminResourceMeta
     */
    public function metadata(string $resourceClass): array
    {
        $key = $this->keyFor($resourceClass);
        $pages = $resourceClass::getPages();

        return [
            'key' => $key,
            'resource_class' => $resourceClass,
            'model_class' => $resourceClass::getModel(),
            'model_label' => $resourceClass::getModelLabel(),
            'plural_model_label' => $resourceClass::getPluralModelLabel(),
            'navigation_group' => $this->stringOrNull($resourceClass::getNavigationGroup()),
            'record_title_attribute' => $resourceClass::getRecordTitleAttribute(),
            'pages' => [
                'index' => array_key_exists('index', $pages),
                'create' => array_key_exists('create', $pages),
                'view' => array_key_exists('view', $pages),
                'edit' => array_key_exists('edit', $pages),
            ],
            'relations' => $this->relationNames($resourceClass),
            'abilities' => [
                'view_any' => $this->canViewAny($resourceClass),
                'create' => $this->canCreate($resourceClass),
                'delete_any' => $resourceClass::canDeleteAny(),
                'force_delete_any' => $resourceClass::canForceDeleteAny(),
                'restore_any' => $resourceClass::canRestoreAny(),
                'reorder' => $resourceClass::canReorder(),
            ],
            'write_support' => [
                'schema' => $this->mutationService->supports($resourceClass),
                'store' => $this->mutationService->supports($resourceClass) && array_key_exists('create', $pages),
                'update' => $this->mutationService->supports($resourceClass) && array_key_exists('edit', $pages),
            ],
            'api_routes' => [
                'collection' => route('api.admin.resources.index', ['resourceKey' => $key], false),
                'meta' => route('api.admin.resources.meta', ['resourceKey' => $key], false),
                'schema' => $this->mutationService->supports($resourceClass)
                    ? route('api.admin.resources.schema', ['resourceKey' => $key], false)
                    : null,
                'store' => $this->mutationService->supports($resourceClass) && array_key_exists('create', $pages)
                    ? route('api.admin.resources.store', ['resourceKey' => $key], false)
                    : null,
                'item_template' => route('api.admin.resources.show', ['resourceKey' => $key, 'recordKey' => 'record'], false),
                'update_template' => $this->mutationService->supports($resourceClass) && array_key_exists('edit', $pages)
                    ? route('api.admin.resources.update', ['resourceKey' => $key, 'recordKey' => 'record'], false)
                    : null,
            ],
            'panel_routes' => [
                'index' => array_key_exists('index', $pages) ? $resourceClass::getUrl('index', panel: 'admin') : null,
                'create' => array_key_exists('create', $pages) ? $resourceClass::getUrl('create', panel: 'admin') : null,
                'view_template' => array_key_exists('view', $pages) ? $resourceClass::getUrl('view', ['record' => 'record'], panel: 'admin') : null,
                'edit_template' => array_key_exists('edit', $pages) ? $resourceClass::getUrl('edit', ['record' => 'record'], panel: 'admin') : null,
            ],
        ];
    }

    /**
     * @return Builder<Model>
     */
    public function queryFor(string $resourceClass): Builder
    {
        /** @var Builder<Model> $query */
        $query = $resourceClass::getEloquentQuery();

        $this->applyDefaultApiEagerLoads($query);

        return $query;
    }

    public function resolveRecord(string $resourceClass, string $recordKey): Model
    {
        $query = $this->queryFor($resourceClass);
        $model = $query->getModel();
        $routeKeyName = $model->getRouteKeyName();
        $keyName = $model->getKeyName();

        return $query
            ->where(function (Builder $builder) use ($recordKey, $routeKeyName, $keyName, $model): void {
                if ($routeKeyName !== '') {
                    $builder->where($model->qualifyColumn($routeKeyName), $recordKey);
                }

                if ($routeKeyName !== $keyName) {
                    $builder->orWhere($model->qualifyColumn($keyName), $recordKey);
                }
            })
            ->firstOrFail();
    }

    /**
     * @return array<string, bool>
     */
    public function recordAbilities(string $resourceClass, Model $record): array
    {
        return [
            'view' => $this->canActOnRecord('view', $resourceClass, $record),
            'edit' => $this->canActOnRecord('update', $resourceClass, $record),
            'delete' => $this->canActOnRecord('delete', $resourceClass, $record),
            'force_delete' => $resourceClass::canForceDelete($record),
            'restore' => $resourceClass::canRestore($record),
            'replicate' => $resourceClass::canReplicate($record),
        ];
    }

    public function keyFor(string $resourceClass): string
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $resourceClass::getModel();

        return Str::kebab(Str::pluralStudly(class_basename($modelClass)));
    }

    /**
     * @return array{
     *   id: string,
     *   route_key: string,
     *   title: string|null,
     *   attributes: array<string, mixed>,
     *   abilities: array<string, bool>,
     *   panel_routes: array<string, string|null>
     * }
     */
    public function serializeRecord(string $resourceClass, Model $record): array
    {
        $pages = $resourceClass::getPages();

        $this->loadMissingApiRelations($record);

        return [
            'id' => (string) $record->getKey(),
            'route_key' => (string) $record->getRouteKey(),
            'title' => $this->htmlableToString($resourceClass::getRecordTitle($record)),
            'attributes' => $this->serializeAttributes($record),
            'abilities' => $this->recordAbilities($resourceClass, $record),
            'panel_routes' => [
                'view' => array_key_exists('view', $pages) ? $resourceClass::getUrl('view', ['record' => $record], panel: 'admin') : null,
                'edit' => array_key_exists('edit', $pages) ? $resourceClass::getUrl('edit', ['record' => $record], panel: 'admin') : null,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function searchableColumns(string $resourceClass): array
    {
        $modelClass = $resourceClass::getModel();
        $model = new $modelClass;
        $table = $model->getTable();
        $candidates = [
            $resourceClass::getRecordTitleAttribute(),
            $model->getRouteKeyName(),
            'name',
            'title',
            'slug',
            'email',
        ];

        $normalizedCandidates = [];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            $normalizedCandidates[] = $candidate;
        }

        $normalizedCandidates = array_values(array_unique($normalizedCandidates));

        return array_values(array_filter(
            $normalizedCandidates,
            static fn (string $column): bool => Schema::hasColumn($table, $column),
        ));
    }

    /**
     * @return list<string>
     */
    private function relationNames(string $resourceClass): array
    {
        $relationNames = [];

        foreach ($resourceClass::getRelations() as $relation) {
            $name = $this->relationName($relation);

            if (! filled($name)) {
                continue;
            }

            $relationNames[] = $name;
        }

        return $relationNames;
    }

    private function relationName(mixed $relation): ?string
    {
        if (is_string($relation)) {
            return class_basename($relation);
        }

        if (is_object($relation)) {
            return class_basename($relation::class);
        }

        return null;
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function canCreate(string $resourceClass): bool
    {
        $user = auth()->user();
        $modelClass = $resourceClass::getModel();

        return $user instanceof User
            ? $user->can('create', $modelClass)
            : $resourceClass::canCreate();
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function canViewAny(string $resourceClass): bool
    {
        $user = auth()->user();
        $modelClass = $resourceClass::getModel();

        return $user instanceof User
            ? $user->can('viewAny', $modelClass)
            : $resourceClass::canViewAny();
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyDefaultApiEagerLoads(Builder $query): void
    {
        $relations = $this->apiRelations($query->getModel());

        if ($relations !== []) {
            $query->with($relations);
        }
    }

    private function loadMissingApiRelations(Model $record): void
    {
        $relations = $this->apiRelations($record);

        if ($relations !== []) {
            $record->loadMissing($relations);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAttributes(Model $record): array
    {
        $attributes = $record->toArray();

        if ($record instanceof Speaker && is_array($attributes['address'] ?? null)) {
            $speakerAddress = $attributes['address'];

            $attributes['address'] = [
                'state_id' => $speakerAddress['state_id'] ?? null,
                'district_id' => $speakerAddress['district_id'] ?? null,
                'subdistrict_id' => $speakerAddress['subdistrict_id'] ?? null,
            ];
        }

        return $attributes;
    }

    /**
     * @return list<string>
     */
    private function apiRelations(Model $model): array
    {
        return method_exists($model, 'address') ? ['address'] : [];
    }

    /**
     * @param  class-string<resource>  $resourceClass
     */
    private function canActOnRecord(string $ability, string $resourceClass, Model $record): bool
    {
        $user = auth()->user();

        return $user instanceof User
            ? $user->can($ability, $record)
            : match ($ability) {
                'view' => $resourceClass::canView($record),
                'update' => $resourceClass::canEdit($record),
                'delete' => $resourceClass::canDelete($record),
                default => false,
            };
    }

    private function stringOrNull(string|UnitEnum|null $value): ?string
    {
        if ($value instanceof UnitEnum) {
            return $value instanceof \BackedEnum ? (string) $value->value : $value->name;
        }

        return filled($value) ? $value : null;
    }

    private function htmlableToString(Htmlable|string|null $value): ?string
    {
        if ($value instanceof Htmlable) {
            return $value->toHtml();
        }

        return $value;
    }
}
