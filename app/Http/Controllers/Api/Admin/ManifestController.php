<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\Api\Admin\AdminResourceService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

class ManifestController extends Controller
{
    public function __construct(
        private readonly AdminResourceService $resourceService,
    ) {}

    #[Group('Admin Manifest')]
    #[Endpoint(
        title: 'List admin resources and write support',
        description: 'Returns the authenticated admin resource manifest. '
            .'Use the `write_support` flags here to discover which resources expose schema, create, and update support through the generic admin API.',
    )]
    public function __invoke(): JsonResponse
    {
        return response()->json($this->resourceService->manifest());
    }
}
