<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Api\Admin\AdminResourceMutationService;
use App\Support\Api\Admin\AdminResourceRegistry;
use Dedoc\Scramble\Attributes\Group;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ResourceController extends Controller
{
    public function __construct(
        private readonly AdminResourceRegistry $registry,
        private readonly AdminResourceMutationService $mutationService,
    ) {}

    #[Group('Admin Resource')]
    public function show(string $resourceKey): JsonResponse
    {
        $resourceClass = $this->resolveAccessibleResource($resourceKey);

        return response()->json([
            'data' => [
                'resource' => $this->registry->metadata($resourceClass),
            ],
        ]);
    }

    #[Group('Admin Resource')]
    public function indexRecords(Request $request, string $resourceKey): JsonResponse
    {
        $resourceClass = $this->resolveAccessibleResource($resourceKey);

        abort_unless($resourceClass::canViewAny(), 403);

        $query = $this->registry->queryFor($resourceClass);
        $search = trim((string) $request->query('search', ''));

        if ($search !== '') {
            $columns = $this->registry->searchableColumns($resourceClass);

            if ($columns !== []) {
                $query->where(function (Builder $builder) use ($columns, $search, $query): void {
                    $model = $query->getModel();

                    foreach ($columns as $index => $column) {
                        $method = $index === 0 ? 'where' : 'orWhere';
                        $builder->{$method}($model->qualifyColumn($column), 'like', "%{$search}%");
                    }
                });
            }
        }

        $this->applyDefaultOrdering($query, $resourceClass);

        $records = $query->paginate(min(max($request->integer('per_page', 15), 1), 100));

        return response()->json([
            'data' => collect($records->items())
                ->map(fn (Model $record): array => $this->registry->serializeRecord($resourceClass, $record))
                ->all(),
            'meta' => [
                'resource' => $this->registry->metadata($resourceClass),
                'search' => $search !== '' ? $search : null,
                'pagination' => [
                    'page' => $records->currentPage(),
                    'per_page' => $records->perPage(),
                    'total' => $records->total(),
                ],
            ],
        ]);
    }

    #[Group('Admin Resource')]
    public function schema(Request $request, string $resourceKey): JsonResponse
    {
        $resourceClass = $this->resolveAccessibleResource($resourceKey);
        $actor = $this->currentUser($request);

        abort_unless($this->mutationService->supports($resourceClass), 404);
        abort_unless($actor instanceof User, 403);

        $operation = (string) $request->query('operation', 'create');
        abort_unless(in_array($operation, ['create', 'update'], true), 422);

        $record = null;

        if ($operation === 'create') {
            abort_unless($actor->can('create', $resourceClass::getModel()), 403);
        } else {
            $recordKey = trim((string) $request->query('recordKey', ''));
            abort_unless($recordKey !== '', 422);

            $record = $this->registry->resolveRecord($resourceClass, $recordKey);
            abort_unless($actor->can('update', $record), 403);
        }

        return response()->json([
            'data' => [
                'resource' => $this->registry->metadata($resourceClass),
                'schema' => $this->mutationService->schema($resourceClass, $resourceKey, $operation, $record),
            ],
        ]);
    }

    #[Group('Admin Resource')]
    public function storeRecord(Request $request, string $resourceKey): JsonResponse
    {
        $resourceClass = $this->resolveAccessibleResource($resourceKey);
        $actor = $this->currentUser($request);

        abort_unless($this->mutationService->supports($resourceClass), 404);
        abort_unless($actor instanceof User && $actor->can('create', $resourceClass::getModel()), 403);

        $validated = Validator::make(
            $request->all(),
            $this->mutationService->rules($resourceClass),
        )->validate();

        $record = $this->mutationService->store($resourceClass, $validated, $actor);

        return response()->json([
            'data' => [
                'resource' => $this->registry->metadata($resourceClass),
                'record' => $this->registry->serializeRecord($resourceClass, $record),
            ],
        ], 201);
    }

    #[Group('Admin Resource')]
    public function updateRecord(Request $request, string $resourceKey, string $recordKey): JsonResponse
    {
        $resourceClass = $this->resolveAccessibleResource($resourceKey);
        $actor = $this->currentUser($request);

        abort_unless($this->mutationService->supports($resourceClass), 404);
        abort_unless($actor instanceof User, 403);

        $record = $this->registry->resolveRecord($resourceClass, $recordKey);
        abort_unless($actor->can('update', $record), 403);

        $validated = Validator::make(
            $request->all(),
            $this->mutationService->rules($resourceClass, updating: true),
        )->validate();

        $record = $this->mutationService->update($resourceClass, $record, $validated, $actor);

        return response()->json([
            'data' => [
                'resource' => $this->registry->metadata($resourceClass),
                'record' => $this->registry->serializeRecord($resourceClass, $record),
            ],
        ]);
    }

    #[Group('Admin Resource')]
    public function showRecord(string $resourceKey, string $recordKey): JsonResponse
    {
        $resourceClass = $this->resolveAccessibleResource($resourceKey);
        $record = $this->registry->resolveRecord($resourceClass, $recordKey);
        $abilities = $this->registry->recordAbilities($resourceClass, $record);

        abort_unless(collect($abilities)->contains(true), 403);

        return response()->json([
            'data' => [
                'resource' => $this->registry->metadata($resourceClass),
                'record' => $this->registry->serializeRecord($resourceClass, $record),
            ],
        ]);
    }

    /**
     * @return class-string<Resource>
     */
    private function resolveAccessibleResource(string $resourceKey): string
    {
        $resourceClass = $this->registry->resolve($resourceKey);

        abort_unless(is_string($resourceClass), 404);
        abort_unless($this->registry->canAccessResource($resourceClass), 403);

        return $resourceClass;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  class-string<Resource>  $resourceClass
     */
    private function applyDefaultOrdering(Builder $query, string $resourceClass): void
    {
        $model = $query->getModel();
        $table = $model->getTable();
        $recordTitleAttribute = $resourceClass::getRecordTitleAttribute();
        $sortColumn = null;

        if (is_string($recordTitleAttribute) && $recordTitleAttribute !== '' && \Illuminate\Support\Facades\Schema::hasColumn($table, $recordTitleAttribute)) {
            $sortColumn = $recordTitleAttribute;
        } elseif (\Illuminate\Support\Facades\Schema::hasColumn($table, 'created_at')) {
            $sortColumn = 'created_at';
        } else {
            $sortColumn = $model->getKeyName();
        }

        $query->orderBy($model->qualifyColumn($sortColumn), $sortColumn === 'created_at' ? 'desc' : 'asc');
    }

    private function currentUser(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
