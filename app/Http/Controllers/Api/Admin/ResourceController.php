<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Api\Admin\AdminResourceService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\PathParameter;
use Dedoc\Scramble\Attributes\QueryParameter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group(
    'Admin Resource',
    'Generic authenticated admin resource interface. '
    .'Read support is resource-key driven, while create and update are schema-driven and only available for resources whose `write_support.schema` flag is true.',
)]
class ResourceController extends Controller
{
    public function __construct(
        private readonly AdminResourceService $resourceService,
    ) {}

    #[PathParameter('resourceKey', 'Admin resource key from `GET /admin/manifest`, for example `events`, `institutions`, or `speakers`.', example: 'events')]
    #[Endpoint(
        title: 'Get admin resource metadata',
        description: 'Returns metadata for a single admin resource, including read and write support flags and related API routes.',
    )]
    public function show(string $resourceKey): JsonResponse
    {
        return response()->json($this->resourceService->resourceMeta($resourceKey));
    }

    #[PathParameter('resourceKey', 'Admin resource key from `GET /admin/manifest`, for example `events`, `institutions`, or `speakers`.', example: 'events')]
    #[QueryParameter('search', 'Optional free-text search across the resource\'s searchable columns.', required: false, type: 'string', infer: false, example: 'maghrib')]
    #[QueryParameter('page', 'Pagination page number.', required: false, type: 'integer', infer: false, default: 1, example: 1)]
    #[QueryParameter('per_page', 'Pagination page size. Values are clamped to the server\'s allowed range.', required: false, type: 'integer', infer: false, default: 15, example: 15)]
    #[Endpoint(
        title: 'List admin resource records',
        description: 'Lists records for a single admin resource. '
            .'This is the read entrypoint for generic admin collections.',
    )]
    public function indexRecords(Request $request, string $resourceKey): JsonResponse
    {
        return response()->json($this->resourceService->listRecords(
            resourceKey: $resourceKey,
            search: (string) $request->query('search', ''),
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
        ));
    }

    #[PathParameter('resourceKey', 'Admin resource key from `GET /admin/manifest`, for example `events`, `institutions`, or `speakers`.', example: 'events')]
    #[QueryParameter('operation', 'Schema mode. Use `create` for new records or `update` for existing records.', required: false, type: 'string', infer: false, default: 'create', example: 'update')]
    #[QueryParameter('recordKey', 'Required when `operation=update`. Use the record route key returned by the admin collection or record endpoints.', required: false, type: 'string', infer: false, example: '0195b86a-3c15-73fa-a2d8-5a45f6a7f701')]
    #[Endpoint(
        title: 'Get admin write schema',
        description: 'Returns the exact create or update contract for a writable admin resource, including defaults, required flags, media state, and conditional rules. '
            .'Use this endpoint before calling the generic admin create or update routes because those mutation payloads are resource-specific and not statically described in the OpenAPI body schema.',
    )]
    public function schema(Request $request, string $resourceKey): JsonResponse
    {
        return response()->json($this->resourceService->writeSchema(
            resourceKey: $resourceKey,
            operation: (string) $request->query('operation', 'create'),
            recordKey: $request->query('recordKey'),
            actor: $this->currentUser($request),
        ));
    }

    #[PathParameter('resourceKey', 'Writable admin resource key from `GET /admin/manifest`.', example: 'speakers')]
    #[Endpoint(
        title: 'Create an admin resource record',
        description: 'Creates a record for a writable admin resource. '
            .'The request body is dynamic and depends on `resourceKey`, so fetch `GET /admin/{resourceKey}/schema?operation=create` first to obtain the canonical required and optional fields.',
    )]
    public function storeRecord(Request $request, string $resourceKey): JsonResponse
    {
        return response()->json(
            $this->resourceService->storeRecord(
                resourceKey: $resourceKey,
                payload: $request->all(),
                actor: $this->currentUser($request),
            ),
            201,
        );
    }

    #[PathParameter('resourceKey', 'Writable admin resource key from `GET /admin/manifest`.', example: 'speakers')]
    #[PathParameter('recordKey', 'Existing admin record route key returned by the collection or record endpoints.', example: '0195b86a-3c15-73fa-a2d8-5a45f6a7f701')]
    #[Endpoint(
        title: 'Update an admin resource record',
        description: 'Updates a record for a writable admin resource. '
            .'The request body is dynamic and depends on both `resourceKey` and the existing record, so fetch `GET /admin/{resourceKey}/schema?operation=update&recordKey={recordKey}` first.',
    )]
    public function updateRecord(Request $request, string $resourceKey, string $recordKey): JsonResponse
    {
        return response()->json($this->resourceService->updateRecord(
            resourceKey: $resourceKey,
            recordKey: $recordKey,
            payload: $request->all(),
            actor: $this->currentUser($request),
        ));
    }

    #[PathParameter('resourceKey', 'Admin resource key from `GET /admin/manifest`.', example: 'events')]
    #[PathParameter('recordKey', 'Existing admin record route key returned by the collection endpoint.', example: '0195b86a-3c15-73fa-a2d8-5a45f6a7f701')]
    #[Endpoint(
        title: 'Get an admin resource record',
        description: 'Returns a single admin record together with its serialized attributes and ability flags.',
    )]
    public function showRecord(string $resourceKey, string $recordKey): JsonResponse
    {
        return response()->json($this->resourceService->showRecord($resourceKey, $recordKey));
    }

    private function currentUser(Request $request): ?User
    {
        $user = $request->user();

        return $user instanceof User ? $user : null;
    }
}
