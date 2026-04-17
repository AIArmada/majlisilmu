<?php

declare(strict_types=1);

namespace App\Support\Api\Admin;

use App\Models\User;
use App\Support\Api\ApiPagination;
use App\Support\ApiDocumentation\ApiDocumentationUrlResolver;
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
        private readonly ApiDocumentationUrlResolver $urlResolver,
    ) {}

    /**
     * @return array{data: array{resources: list<array<string, mixed>>}}
     */
    public function manifest(bool $compact = false, bool $writableOnly = false): array
    {
        $resources = array_values(array_filter(
            array_map(
                $this->registry->metadata(...),
                $this->registry->accessibleResources(),
            ),
            fn (array $resource): bool => ! $writableOnly || $resource['write_support']['schema'] === true,
        ));

        return [
            'data' => [
                'version' => '2026-04-16',
                'docs' => [
                    'ui' => $this->urlResolver->docsUrl(),
                    'openapi' => $this->urlResolver->docsJsonUrl(),
                    'api_base' => $this->urlResolver->apiBaseUrl(),
                ],
                'write_workflow' => [
                    'discover_resources' => route('api.admin.manifest'),
                    'fetch_create_schema_template' => route('api.admin.resources.schema', ['resourceKey' => 'resourceKey'], false).'?operation=create',
                    'fetch_update_schema_template' => route('api.admin.resources.schema', ['resourceKey' => 'resourceKey'], false).'?operation=update&recordKey=recordKey',
                    'create_endpoint_template' => route('api.admin.resources.store', ['resourceKey' => 'resourceKey'], false),
                    'update_endpoint_template' => route('api.admin.resources.update', ['resourceKey' => 'resourceKey', 'recordKey' => 'recordKey'], false),
                ],
                'rules' => [
                    'Use resource keys returned by the manifest to select the correct admin schema and route family.',
                    'Use the admin record route_key returned by admin collection or record endpoints for record-specific paths; id remains accepted as a compatibility fallback.',
                    'Fetch the exact schema before every create or update because required fields and catalogs are resource-specific.',
                    'Admin PUT requests are full schema-guided updates, not partial patches.',
                    'Use authenticated /admin/catalogs/* endpoints for dependent selectors referenced by schema catalog metadata.',
                ],
                'catalogs' => [
                    'countries' => route('api.admin.catalogs.countries'),
                    'states' => route('api.admin.catalogs.states'),
                    'districts' => route('api.admin.catalogs.districts'),
                    'subdistricts' => route('api.admin.catalogs.subdistricts'),
                ],
                'resources' => array_values(array_map(
                    fn (array $resource): array => $compact ? $this->summarizeResource($resource) : $resource,
                    $resources,
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
            perPage: ApiPagination::normalizePerPage($perPage, default: 15, max: 100),
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

            $record = $this->registry->resolveRecord($resourceClass, trim((string) $recordKey));
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

        if (is_array($payload['address'] ?? null) && ! array_key_exists('address', $validated)) {
            $validated['address'] = $payload['address'];
        }

        $validated = $this->mutationService->normalizeValidatedPayload($resourceClass, $validated);

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

        if (is_array($payload['address'] ?? null) && ! array_key_exists('address', $validated)) {
            $validated['address'] = $payload['address'];
        }

        $validated = $this->mutationService->normalizeValidatedPayload($resourceClass, $validated);

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
     * @param  array<string, mixed>  $resource
     * @return array<string, mixed>
     */
    protected function summarizeResource(array $resource): array
    {
        return [
            'key' => $resource['key'],
            'model_label' => $resource['model_label'],
            'plural_model_label' => $resource['plural_model_label'],
            'navigation_group' => $resource['navigation_group'],
            'pages' => $resource['pages'],
            'abilities' => $resource['abilities'],
            'write_support' => $resource['write_support'],
            'api_routes' => [
                'collection' => $resource['api_routes']['collection'],
                'meta' => $resource['api_routes']['meta'],
                'schema' => $resource['api_routes']['schema'],
                'store' => $resource['api_routes']['store'],
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
