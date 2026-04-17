<?php

declare(strict_types=1);

namespace App\Support\Api\Member;

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

class MemberResourceService
{
    public function __construct(
        private readonly MemberResourceRegistry $registry,
        private readonly MemberResourceMutationService $mutationService,
        private readonly ApiDocumentationUrlResolver $urlResolver,
    ) {}

    /**
     * @return array{data: array<string, mixed>}
     */
    public function manifest(bool $compact = false): array
    {
        $resources = array_values(array_map(
            $this->registry->metadata(...),
            $this->registry->accessibleResources(),
        ));

        return [
            'data' => [
                'version' => '2026-04-17',
                'audience' => 'member',
                'panel' => 'ahli',
                'docs' => [
                    'ui' => $this->urlResolver->docsUrl(),
                    'openapi' => $this->urlResolver->docsJsonUrl(),
                    'api_base' => $this->urlResolver->apiBaseUrl(),
                ],
                'write_workflow' => [
                    'fetch_update_schema_tool' => 'member-get-write-schema',
                    'update_tool' => 'member-update-record',
                ],
                'rules' => [
                    'Use the returned resource keys to scope follow-up MCP reads to your own memberships and related records.',
                    'Resource visibility follows the Ahli workspace boundary and live membership relationships, not the full admin panel.',
                    'Member MCP writes are limited to schema-guided updates for records the current user can already edit through the Ahli surface.',
                    'Fetch the exact update schema before every member write because required fields remain resource-specific.',
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
    public function writeSchema(string $resourceKey, string $recordKey, ?User $actor = null): array
    {
        $resourceClass = $this->resolveWritableResource($resourceKey, $actor);

        abort_unless($actor instanceof User, 403);

        $record = $this->registry->resolveRecord($resourceClass, trim($recordKey));
        abort_unless($actor->can('update', $record), 403);

        return [
            'data' => [
                'resource' => $this->registry->metadata($resourceClass),
                'schema' => $this->mutationService->schema($resourceClass, $resourceKey, $record),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{data: array{resource: array<string, mixed>, record: array<string, mixed>}}
     */
    public function updateRecord(string $resourceKey, string $recordKey, array $payload, ?User $actor = null): array
    {
        $resourceClass = $this->resolveWritableResource($resourceKey, $actor);

        abort_unless($actor instanceof User, 403);

        $record = $this->registry->resolveRecord($resourceClass, $recordKey);
        abort_unless($actor->can('update', $record), 403);

        $validated = Validator::make(
            $payload,
            $this->mutationService->rules($resourceClass),
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
        if (! $user instanceof User || ! $user->hasMemberMcpAccess()) {
            return false;
        }

        foreach ($this->registry->accessibleResources() as $resourceClass) {
            if ($this->mutationService->supports($resourceClass) && $this->mutationService->canWriteResource($resourceClass, $user)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  class-string<resource>  $resourceClass
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
     * @param  class-string<resource>  $resourceClass
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
            'panel_routes' => [
                'index' => $resource['panel_routes']['index'],
                'view_template' => $resource['panel_routes']['view_template'],
                'edit_template' => $resource['panel_routes']['edit_template'],
            ],
        ];
    }

    /**
     * @return class-string<resource>
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
     * @return class-string<resource>
     */
    protected function resolveWritableResource(string $resourceKey, ?User $actor = null): string
    {
        $resourceClass = $this->resolveAccessibleResource($resourceKey);

        if (! $this->mutationService->supports($resourceClass)) {
            throw new NotFoundHttpException;
        }

        abort_unless($actor instanceof User && $this->mutationService->canWriteResource($resourceClass, $actor), 403);

        return $resourceClass;
    }
}
