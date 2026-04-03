<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Support\Api\Admin\AdminResourceRegistry;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

class ManifestController extends Controller
{
    public function __construct(
        private readonly AdminResourceRegistry $registry,
    ) {}

    #[Group('Admin Manifest')]
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'data' => [
                'resources' => collect($this->registry->accessibleResources())
                    ->map(fn (string $resourceClass): array => $this->registry->metadata($resourceClass))
                    ->values()
                    ->all(),
            ],
        ]);
    }
}
