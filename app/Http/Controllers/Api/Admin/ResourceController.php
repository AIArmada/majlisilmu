<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Api\Admin\AdminResourceService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResourceController extends Controller
{
    public function __construct(
        private readonly AdminResourceService $resourceService,
    ) {}

    #[Group('Admin Resource')]
    public function show(string $resourceKey): JsonResponse
    {
        return response()->json($this->resourceService->resourceMeta($resourceKey));
    }

    #[Group('Admin Resource')]
    public function indexRecords(Request $request, string $resourceKey): JsonResponse
    {
        return response()->json($this->resourceService->listRecords(
            resourceKey: $resourceKey,
            search: (string) $request->query('search', ''),
            page: $request->integer('page', 1),
            perPage: $request->integer('per_page', 15),
        ));
    }

    #[Group('Admin Resource')]
    public function schema(Request $request, string $resourceKey): JsonResponse
    {
        return response()->json($this->resourceService->writeSchema(
            resourceKey: $resourceKey,
            operation: (string) $request->query('operation', 'create'),
            recordKey: $request->query('recordKey'),
            actor: $this->currentUser($request),
        ));
    }

    #[Group('Admin Resource')]
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

    #[Group('Admin Resource')]
    public function updateRecord(Request $request, string $resourceKey, string $recordKey): JsonResponse
    {
        return response()->json($this->resourceService->updateRecord(
            resourceKey: $resourceKey,
            recordKey: $recordKey,
            payload: $request->all(),
            actor: $this->currentUser($request),
        ));
    }

    #[Group('Admin Resource')]
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
