<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\Api\Admin\AdminResourceService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

class ManifestController extends Controller
{
    public function __construct(
        private readonly AdminResourceService $resourceService,
    ) {}

    #[Group('Admin Manifest')]
    public function __invoke(): JsonResponse
    {
        return response()->json($this->resourceService->manifest());
    }
}
