<?php

declare(strict_types=1);

namespace App\Support\Api\Member;

use App\Filament\Ahli\Resources\Events\EventResource as AhliEventResource;
use App\Filament\Ahli\Resources\Institutions\InstitutionResource as AhliInstitutionResource;
use App\Filament\Ahli\Resources\References\ReferenceResource as AhliReferenceResource;
use App\Filament\Ahli\Resources\Speakers\SpeakerResource as AhliSpeakerResource;
use App\Models\Speaker;
use App\Models\User;
use Filament\Resources\Resource;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * @phpstan-type MemberResourceMeta array{
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
 *   panel_routes: array<string, string|null>
 * }
 */
class MemberResourceRegistry
{
    /** @var list<class-string<resource>>|null */
    private ?array $resourcesCache = null;

    /** @var array<class-string<resource>, array<string, mixed>> */
    private array $metadataCache = [];

    /** @var array<class-string<resource>, list<string>> */
    private array $searchableColumnsCache = [];

    public function __construct(
        private readonly MemberResourceMutationService $mutationService,
    ) {}

    /**
     * @return list<class-string<resource>>
     */
    public function resources(): array
    {
        return $this->resourcesCache ??= [
            AhliInstitutionResource::class,
            AhliSpeakerResource::class,
            AhliReferenceResource::class,
            AhliEventResource::class,
        ];
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
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        return match ($resourceClass) {
            AhliInstitutionResource::class => $user->institutions()->exists(),
            AhliSpeakerResource::class => $user->speakers()->exists(),
            AhliReferenceResource::class => $user->references()->exists(),
            AhliEventResource::class => $user->institutions()->exists()
                || $user->speakers()->exists()
                || $user->memberEvents()->exists(),
            default => false,
        };
    }

    /**
     * @return MemberResourceMeta
     */
    public function metadata(string $resourceClass): array
    {
        if (array_key_exists($resourceClass, $this->metadataCache)) {
            return $this->metadataCache[$resourceClass];
        }

        $key = $this->keyFor($resourceClass);
        $pages = $resourceClass::getPages();

        return $this->metadataCache[$resourceClass] = [
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
                'view_any' => $this->canAccessResource($resourceClass),
                'create' => false,
                'update' => $this->canWriteResource($resourceClass),
            ],
            'write_support' => [
                'schema' => $this->canWriteResource($resourceClass),
                'store' => false,
                'update' => $this->canWriteResource($resourceClass),
            ],
            'panel_routes' => [
                'index' => array_key_exists('index', $pages) ? $resourceClass::getUrl('index', panel: 'ahli') : null,
                'create' => null,
                'view_template' => array_key_exists('view', $pages) ? $resourceClass::getUrl('view', ['record' => 'record'], panel: 'ahli') : null,
                'edit_template' => array_key_exists('edit', $pages) ? $resourceClass::getUrl('edit', ['record' => 'record'], panel: 'ahli') : null,
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
        $model = $this->queryFor($resourceClass)->getModel();

        $routeBoundRecord = $model
            ->resolveRouteBindingQuery($this->queryFor($resourceClass), $recordKey)
            ->first();

        if ($routeBoundRecord instanceof Model) {
            return $routeBoundRecord;
        }

        abort(404);
    }

    /**
     * @return array<string, bool>
     */
    public function recordAbilities(Model $record): array
    {
        $user = auth()->user();

        return [
            'view' => true,
            'edit' => $user instanceof User && $user->can('update', $record),
            'delete' => $user instanceof User && $user->can('delete', $record),
            'manage_members' => $user instanceof User && $user->can('manageMembers', $record),
        ];
    }

    public function canWriteResource(string $resourceClass): bool
    {
        $user = auth()->user();

        return $user instanceof User
            && $this->mutationService->supports($resourceClass)
            && $this->mutationService->canWriteResource($resourceClass, $user);
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
            'abilities' => $this->recordAbilities($record),
            'panel_routes' => [
                'view' => array_key_exists('view', $pages) ? $resourceClass::getUrl('view', ['record' => $record], panel: 'ahli') : null,
                'edit' => array_key_exists('edit', $pages) ? $resourceClass::getUrl('edit', ['record' => $record], panel: 'ahli') : null,
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function searchableColumns(string $resourceClass): array
    {
        if (array_key_exists($resourceClass, $this->searchableColumnsCache)) {
            return $this->searchableColumnsCache[$resourceClass];
        }

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

        return $this->searchableColumnsCache[$resourceClass] = array_values(array_filter(
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
            $attributes['address'] = Arr::only($attributes['address'], [
                'country_id',
                'state_id',
                'district_id',
                'subdistrict_id',
            ]);
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
            return (string) $value->toHtml();
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
