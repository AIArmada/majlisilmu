<?php

declare(strict_types=1);

namespace App\Support\Api\Admin;

use App\Models\User;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AdminResourceService
{
    public function __construct(
        private readonly AdminResourceRegistry $registry,
        private readonly AdminResourceMutationService $mutationService,
    ) {}

    /**
     * @return array{data: array{resources: list<array<string, mixed>>}}
     */
    public function manifest(): array
    {
        return [
            'data' => [
                'resources' => array_values(array_map(
                    fn (string $resourceClass): array => $this->registry->metadata($resourceClass),
                    $this->registry->accessibleResources(),
                )),
            ],
        ];
    }

    /**
     * @return array{data: array{resource: array<string, mixed>}}
     */
    public function resourceMeta(string $resourceKey): array
    {
        $resourceClass = $this->resolveAccessibleResource($resourceKey);

        return [
            'data' => [
                'resource' => $this->registry->metadata($resourceClass),
            ],
        ];
    }

    /**
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function listRecords(string $resourceKey, string $search = '', int $page = 1, int $perPage = 15): array
    {
        $resourceClass = $this->resolveAccessibleResource($resourceKey);

        abort_unless($resourceClass::canViewAny(), 403);

        $query = $this->registry->queryFor($resourceClass);
        $normalizedSearch = trim($search);

        if ($normalizedSearch !== '') {
            $this->applySearch($query, $resourceClass, $normalizedSearch);
        }

        $this->applyDefaultOrdering($query, $resourceClass);

        $records = $query->paginate(
            perPage: min(max($perPage, 1), 100),
            page: max($page, 1),
        );

        return [
            'data' => array_values(array_map(
                fn (Model $record): array => $this->registry->serializeRecord($resourceClass, $record),
                $records->items(),
            )),
            'meta' => $this->recordsMeta($resourceClass, $records, $normalizedSearch),
        ];
    }

    /**
     * @return array{data: array{resource: array<string, mixed>, record: array<string, mixed>}}
     */
    public function showRecord(string $resourceKey, string $recordKey): array
    {
        $resourceClass = $this->resolveAccessibleResource($resourceKey);
        $record = $this->registry->resolveRecord($resourceClass, $recordKey);
        $abilities = $this->registry->recordAbilities($resourceClass, $record);

        abort_unless(collect($abilities)->contains(true), 403);

        return [
            'data' => [
                'resource' => $this->registry->metadata($resourceClass),
                'record' => $this->registry->serializeRecord($resourceClass, $record),
            ],
        ];
    }

    /**
     * @return array{data: array{resource: array<string, mixed>, schema: array<string, mixed>}}
     */
    public function writeSchema(string $resourceKey, string $operation = 'create', ?string $recordKey = null, ?User $actor = null): array
    {
        $resourceClass = $this->resolveWritableResource($resourceKey);

        abort_unless($actor instanceof User, 403);
        abort_unless(in_array($operation, ['create', 'update'], true), 422);

        $record = null;

        if ($operation === 'create') {
            abort_unless($actor->can('create', $resourceClass::getModel()), 403);
        } else {
            abort_unless(is_string($recordKey) && trim($recordKey) !== '', 422);

            $record = $this->registry->resolveRecord($resourceClass, trim($recordKey));
            abort_unless($actor->can('update', $record), 403);
        }

        return [
            'data' => [
                'resource' => $this->registry->metadata($resourceClass),
                'schema' => $this->mutationService->schema($resourceClass, $resourceKey, $operation, $record),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{data: array{resource: array<string, mixed>, record: array<string, mixed>}}
     */
    public function storeRecord(string $resourceKey, array $payload, ?User $actor = null): array
    {
        $resourceClass = $this->resolveWritableResource($resourceKey);

        abort_unless($actor instanceof User && $actor->can('create', $resourceClass::getModel()), 403);

        $validated = Validator::make(
            $payload,
            $this->mutationService->rules($resourceClass),
        )->validate();

        $record = $this->mutationService->store($resourceClass, $validated, $actor);

        return [
            'data' => [
                'resource' => $this->registry->metadata($resourceClass),
                'record' => $this->registry->serializeRecord($resourceClass, $record),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{data: array{resource: array<string, mixed>, record: array<string, mixed>}}
     */
    public function updateRecord(string $resourceKey, string $recordKey, array $payload, ?User $actor = null): array
    {
        $resourceClass = $this->resolveWritableResource($resourceKey);

        abort_unless($actor instanceof User, 403);

        $record = $this->registry->resolveRecord($resourceClass, $recordKey);
        abort_unless($actor->can('update', $record), 403);

        $validated = Validator::make(
            $payload,
            $this->mutationService->rules($resourceClass, updating: true),
        )->validate();

        $record = $this->mutationService->update($resourceClass, $record, $validated, $actor);

        return [
            'data' => [
                'resource' => $this->registry->metadata($resourceClass),
                'record' => $this->registry->serializeRecord($resourceClass, $record),
            ],
        ];
    }

    public function hasAnyWritableResourceAccess(?User $user = null): bool
    {
        if (! $user instanceof User || ! $user->hasApplicationAdminAccess()) {
            return false;
        }

        foreach ($this->registry->accessibleResources() as $resourceClass) {
            if (! $this->mutationService->supports($resourceClass)) {
                continue;
            }

            if ($user->can('create', $resourceClass::getModel())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  class-string<\Filament\Resources\Resource>  $resourceClass
     */
    public function applyDefaultOrdering(Builder $query, string $resourceClass): void
    {
        $model = $query->getModel();
        $table = $model->getTable();
        $recordTitleAttribute = $resourceClass::getRecordTitleAttribute();

        if (is_string($recordTitleAttribute) && $recordTitleAttribute !== '' && Schema::hasColumn($table, $recordTitleAttribute)) {
            $sortColumn = $recordTitleAttribute;
        } elseif (Schema::hasColumn($table, 'created_at')) {
            $sortColumn = 'created_at';
        } else {
            $sortColumn = $model->getKeyName();
        }

        $query->orderBy($model->qualifyColumn($sortColumn), $sortColumn === 'created_at' ? 'desc' : 'asc');
    }

    /**
     * @param  Builder<Model>  $query
     * @param  class-string<resource>  $resourceClass
     */
    protected function applySearch(Builder $query, string $resourceClass, string $search): void
    {
        $columns = $this->registry->searchableColumns($resourceClass);

        if ($columns === []) {
            return;
        }

        $model = $query->getModel();

        $query->where(function (Builder $builder) use ($columns, $search, $model): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $builder->{$method}($model->qualifyColumn($column), 'like', "%{$search}%");
            }
        });
    }

    /**
     * @param  class-string<\Filament\Resources\Resource>  $resourceClass
     * @param  LengthAwarePaginator<int, Model>  $records
     * @return array<string, mixed>
     */
    protected function recordsMeta(string $resourceClass, LengthAwarePaginator $records, string $search): array
    {
        return [
            'resource' => $this->registry->metadata($resourceClass),
            'search' => $search !== '' ? $search : null,
            'pagination' => [
                'page' => $records->currentPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ];
    }

    /**
     * @return class-string<\Filament\Resources\Resource>
     */
    protected function resolveAccessibleResource(string $resourceKey): string
    {
        $resourceClass = $this->registry->resolve($resourceKey);

        if (! is_string($resourceClass)) {
            throw new NotFoundHttpException;
        }

        abort_unless($this->registry->canAccessResource($resourceClass), 403);

        return $resourceClass;
    }

    /**
     * @return class-string<\Filament\Resources\Resource>
     */
    protected function resolveWritableResource(string $resourceKey): string
    {
        $resourceClass = $this->resolveAccessibleResource($resourceKey);

        if (! $this->mutationService->supports($resourceClass)) {
            throw new NotFoundHttpException;
        }

        return $resourceClass;
    }
}
