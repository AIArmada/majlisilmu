<?php

declare(strict_types=1);

namespace App\Support\Api\Admin;

use App\Filament\Resources\Speakers\SpeakerResource;
use App\Models\User;
use App\Support\Api\ApiPagination;
use App\Support\ApiDocumentation\ApiDocumentationUrlResolver;
use App\Support\Timezone\UserDateTimeFormatter;
use Carbon\CarbonInterface;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
                    'Use the admin record route_key returned by admin collection or detail payloads for record-specific paths.',
                    'Use the relation keys returned by admin resource metadata when traversing nested related records through the related-record route or MCP tool.',
                    'Fetch the exact schema before every create or update because required fields and catalogs are resource-specific.',
                    'Send validate_only=true to preview and normalize a write request without persisting it; inspect the returned warning envelope before retrying without the flag.',
                    'Admin PUT requests are full schema-guided updates, not partial patches.',
                    'Use api_routes for HTTP API clients and mcp_tools for MCP clients; MCP tools take structured arguments instead of URL paths.',
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
    public function listRecords(
        string $resourceKey,
        string $search = '',
        int $page = 1,
        int $perPage = 15,
        array $filters = [],
        ?string $startsAfter = null,
        ?string $startsBefore = null,
        ?string $startsOnLocalDate = null,
    ): array {
        $resourceClass = $this->resolveAccessibleResource($resourceKey);

        abort_unless($resourceClass::canViewAny(), 403);

        $query = $this->registry->queryFor($resourceClass);
        $normalizedSearch = trim($search);

        if ($normalizedSearch !== '') {
            $this->applySearch($query, $resourceClass, $normalizedSearch);
        }

        $this->applyFilters($query, $resourceClass, $filters);

        $this->applyDateFilters(
            $query,
            $resourceClass,
            $startsAfter,
            $startsBefore,
            $startsOnLocalDate,
        );

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
     * @return array{data: list<array<string, mixed>>, meta: array<string, mixed>}
     */
    public function listRelatedRecords(string $resourceKey, string $recordKey, string $relation, string $search = '', int $page = 1, int $perPage = 15): array
    {
        $resourceClass = $this->resolveAccessibleResource($resourceKey);
        $record = $this->registry->resolveRecord($resourceClass, $recordKey);
        $abilities = $this->registry->recordAbilities($resourceClass, $record);

        abort_unless(collect($abilities)->contains(true), 403);

        $resourceMeta = $this->registry->metadata($resourceClass);
        $relationName = Str::snake(trim($relation));

        abort_unless(in_array($relationName, $resourceMeta['relations'], true), 404);

        $relationMethod = Str::camel($relationName);

        if (! method_exists($record, $relationMethod)) {
            throw new NotFoundHttpException;
        }

        /** @var Relation<Model, Model, mixed> $relationQuery */
        $relationQuery = $record->{$relationMethod}();
        $query = $relationQuery->getQuery();
        $relatedModel = $relationQuery->getRelated();
        $relatedResourceClass = $this->registry->resolveForModel($relatedModel::class);
        $query->select($relatedModel->qualifyColumn('*'));
        $normalizedSearch = trim($search);

        if ($normalizedSearch !== '') {
            if ($relatedResourceClass !== null) {
                $this->applySearch($query, $relatedResourceClass, $normalizedSearch);
            } else {
                $this->applyGenericSearch($query, $relatedModel, $normalizedSearch);
            }
        }

        if ($relatedResourceClass !== null) {
            $this->applyDefaultOrdering($query, $relatedResourceClass);
        } else {
            $this->applyDefaultOrderingForModel($query, $relatedModel);
        }

        $records = $query->paginate(
            perPage: ApiPagination::normalizePerPage($perPage, default: 15, max: 100),
            page: max($page, 1),
        );

        return [
            'data' => array_values(array_map(
                fn (Model $relatedRecord): array => $this->serializeRelatedRecord($relatedResourceClass, $relatedRecord),
                $records->items(),
            )),
            'meta' => [
                'resource' => $resourceMeta,
                'parent_record' => $this->registry->serializeRecordDetail($resourceClass, $record),
                'relation' => [
                    'name' => $relationName,
                    'method' => $relationMethod,
                    'related_model_class' => $relatedModel::class,
                    'related_resource' => $relatedResourceClass !== null ? $this->registry->metadata($relatedResourceClass) : null,
                ],
                'search' => $normalizedSearch !== '' ? $normalizedSearch : null,
                'pagination' => ApiPagination::paginationMeta($records),
            ],
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
                'record' => $this->registry->serializeRecordDetail($resourceClass, $record),
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
            if (! is_string($recordKey) || trim($recordKey) === '') {
                throw ValidationException::withMessages([
                    'recordKey' => [__('Record key is required when operation is update.')],
                ]);
            }

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
     * @return array<string, mixed>
     */
    public function storeRecord(string $resourceKey, array $payload, ?User $actor = null, bool $validateOnly = false): array
    {
        $resourceClass = $this->resolveWritableResource($resourceKey);

        abort_unless($actor instanceof User && $actor->can('create', $resourceClass::getModel()), 403);

        $validated = $this->validatedPayload($resourceClass, $payload);

        if ($validateOnly) {
            return $this->previewWriteRecord($resourceClass, $validated, 'create');
        }

        $record = $this->mutationService->store($resourceClass, $validated, $actor);

        return [
            'data' => [
                'resource' => $this->registry->metadata($resourceClass),
                'record' => $this->registry->serializeRecordDetail($resourceClass, $record),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateRecord(string $resourceKey, string $recordKey, array $payload, ?User $actor = null, bool $validateOnly = false): array
    {
        $resourceClass = $this->resolveWritableResource($resourceKey);

        abort_unless($actor instanceof User, 403);

        $record = $this->registry->resolveRecord($resourceClass, $recordKey);
        abort_unless($actor->can('update', $record), 403);

        $validated = $this->validatedPayload($resourceClass, $payload, updating: true, record: $record);

        if ($validateOnly) {
            return $this->previewWriteRecord($resourceClass, $validated, 'update', $record);
        }

        $record = $this->mutationService->update($resourceClass, $record, $validated, $actor);

        return [
            'data' => [
                'resource' => $this->registry->metadata($resourceClass),
                'record' => $this->registry->serializeRecordDetail($resourceClass, $record),
            ],
        ];
    }

    public function hasAnyWritableResourceAccess(?User $user = null): bool
    {
        if (! $user instanceof User || ! $user->hasApplicationAdminAccess()) {
            return false;
        }

        foreach ($this->registry->resources() as $resourceClass) {
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
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validatedPayload(string $resourceClass, array $payload, bool $updating = false, ?Model $record = null): array
    {
        $validated = Validator::make(
            $payload,
            $this->mutationService->rules($resourceClass, updating: $updating),
        )->validate();

        if (is_array($payload['address'] ?? null) && ! array_key_exists('address', $validated)) {
            $validated['address'] = $payload['address'];
        }

        return $this->mutationService->normalizeValidatedPayload($resourceClass, $validated, $record);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{data: array{resource: array<string, mixed>, preview: array<string, mixed>}}
     */
    private function previewWriteRecord(string $resourceClass, array $validated, string $operation, ?Model $record = null): array
    {
        $preview = $this->mutationService->previewNormalizedPayload($validated);

        return [
            'data' => [
                'resource' => $this->registry->metadata($resourceClass),
                'preview' => array_merge(
                    [
                        'validate_only' => true,
                        'operation' => $operation,
                        'current_record' => $record instanceof Model
                            ? $this->registry->serializeRecordDetail($resourceClass, $record)
                            : null,
                    ],
                    $preview,
                ),
            ],
        ];
    }

    /**
     * @return array{
     *   id: string,
     *   route_key: string,
     *   title: string|null,
     *   attributes: array<string, mixed>,
     *   abilities?: array<string, bool>,
     *   panel_routes?: array<string, string|null>
     * }
     */
    private function serializeRelatedRecord(?string $resourceClass, Model $record): array
    {
        if ($resourceClass !== null) {
            return $this->registry->serializeRecord($resourceClass, $record);
        }

        return [
            'id' => (string) $record->getKey(),
            'route_key' => (string) $record->getRouteKey(),
            'title' => $this->genericRecordTitle($record),
            'attributes' => $this->serializeGenericAttributes($record),
        ];
    }

    private function genericRecordTitle(Model $record): string
    {
        $table = $record->getTable();
        $candidates = [
            'title',
            'name',
            'label',
            'slug',
            'email',
            $record->getRouteKeyName(),
        ];

        foreach (array_values(array_unique(array_filter($candidates, static fn (string $column): bool => $column !== ''))) as $column) {
            if (! Schema::hasColumn($table, $column)) {
                continue;
            }

            $value = $record->getAttribute($column);

            if (is_string($value) && trim($value) !== '') {
                return $value;
            }

            if (is_scalar($value) && trim((string) $value) !== '') {
                return (string) $value;
            }
        }

        return (string) $record->getRouteKey();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeGenericAttributes(Model $record): array
    {
        $attributes = $record->toArray();

        if ($record instanceof User) {
            return Arr::except($attributes, [
                'email',
                'email_verified_at',
                'phone',
                'phone_verified_at',
                'daily_prayer_institution_id',
                'friday_prayer_institution_id',
            ]);
        }

        return $attributes;
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyDefaultOrderingForModel(Builder $query, Model $model): void
    {
        $table = $model->getTable();
        $candidates = [
            'name',
            'title',
            'label',
            'slug',
        ];

        foreach ($candidates as $candidate) {
            if (! Schema::hasColumn($table, $candidate)) {
                continue;
            }

            $query->orderBy($model->qualifyColumn($candidate));

            return;
        }

        if (Schema::hasColumn($table, 'created_at')) {
            $query->orderBy($model->qualifyColumn('created_at'), 'desc');

            return;
        }

        $query->orderBy($model->qualifyColumn($model->getKeyName()));
    }

    /**
     * @param  Builder<Model>  $query
     */
    private function applyGenericSearch(Builder $query, Model $model, string $search): void
    {
        $table = $model->getTable();
        $candidates = [
            'title',
            'name',
            'label',
            'slug',
            'email',
            'description',
            $model->getRouteKeyName(),
        ];
        $columns = array_values(array_filter(array_unique($candidates), static fn (string $column): bool => Schema::hasColumn($table, $column)));

        if ($columns === []) {
            return;
        }

        $query->where(function (Builder $builder) use ($columns, $search, $model): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $builder->{$method}($model->qualifyColumn($column), 'like', "%{$search}%");
            }
        });
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
     * @param  array<string, mixed>  $filters
     */
    protected function applyFilters(Builder $query, string $resourceClass, array $filters): void
    {
        if ($filters === [] || $resourceClass !== SpeakerResource::class) {
            return;
        }

        $model = $query->getModel();

        if (array_key_exists('status', $filters)) {
            $rawStatus = $filters['status'];

            if ($rawStatus !== null && $rawStatus !== '') {
                $status = $this->normalizeStatusFilter($rawStatus);

                if ($status === null || ! in_array($status, ['pending', 'verified', 'rejected'], true)) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->where($model->qualifyColumn('status'), $status);
            }
        }

        if (array_key_exists('is_active', $filters)) {
            $rawIsActive = $filters['is_active'];

            if ($rawIsActive !== null && $rawIsActive !== '') {
                $isActive = $this->normalizeBooleanFilter($rawIsActive);

                if ($isActive === null) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->where($model->qualifyColumn('is_active'), $isActive);
            }
        }

        if (array_key_exists('has_events', $filters)) {
            $rawHasEvents = $filters['has_events'];

            if ($rawHasEvents !== null && $rawHasEvents !== '') {
                $hasEvents = $this->normalizeBooleanFilter($rawHasEvents);

                if ($hasEvents === null) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                if ($hasEvents) {
                    $query->whereHas('events');

                    return;
                }

                $query->whereDoesntHave('events');
            }
        }
    }

    private function normalizeStatusFilter(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeBooleanFilter(mixed $value): ?bool
    {
        $normalized = filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);

        return is_bool($normalized) ? $normalized : null;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  class-string<resource>  $resourceClass
     */
    protected function applyDateFilters(
        Builder $query,
        string $resourceClass,
        ?string $startsAfter,
        ?string $startsBefore,
        ?string $startsOnLocalDate,
    ): void {
        $resource = $this->registry->metadata($resourceClass);

        if (! is_array($resource['date_semantics'] ?? null)) {
            return;
        }

        $model = $query->getModel();
        $startsAtColumn = $model->qualifyColumn('starts_at');

        if (is_string($startsAfter) && trim($startsAfter) !== '') {
            $parsedStartsAfter = UserDateTimeFormatter::parseUserDateToUtc($startsAfter, false);

            if ($parsedStartsAfter instanceof CarbonInterface) {
                $query->where($startsAtColumn, '>=', $parsedStartsAfter);
            }
        }

        if (is_string($startsBefore) && trim($startsBefore) !== '') {
            $parsedStartsBefore = UserDateTimeFormatter::parseUserDateToUtc($startsBefore, true);

            if ($parsedStartsBefore instanceof CarbonInterface) {
                $query->where($startsAtColumn, '<=', $parsedStartsBefore);
            }
        }

        if (is_string($startsOnLocalDate) && trim($startsOnLocalDate) !== '') {
            $startsOnLocalDateStart = UserDateTimeFormatter::parseUserDateToUtc($startsOnLocalDate, false);
            $startsOnLocalDateEnd = UserDateTimeFormatter::parseUserDateToUtc($startsOnLocalDate, true);

            if ($startsOnLocalDateStart instanceof CarbonInterface && $startsOnLocalDateEnd instanceof CarbonInterface) {
                $query->whereBetween($startsAtColumn, [$startsOnLocalDateStart, $startsOnLocalDateEnd]);
            }
        }
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
            'pagination' => ApiPagination::paginationMeta($records),
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
            'filters' => $resource['filters'],
            'write_support' => $resource['write_support'],
            'api_routes' => [
                'collection' => $resource['api_routes']['collection'],
                'meta' => $resource['api_routes']['meta'],
                'schema' => $resource['api_routes']['schema'],
                'store' => $resource['api_routes']['store'],
                'related_collection' => $resource['api_routes']['related_collection'],
                'item_template' => $resource['api_routes']['item_template'],
                'update_template' => $resource['api_routes']['update_template'],
            ],
            'mcp_tools' => $resource['mcp_tools'],
            'timezone_sensitive' => $resource['timezone_sensitive'],
            'date_semantics' => $resource['date_semantics'],
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
