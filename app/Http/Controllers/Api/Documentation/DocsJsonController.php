<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Documentation;

use App\Http\Controllers\Controller;
use App\Support\ApiDocumentation\ApiDocumentationConfigFactory;
use App\Support\ApiDocumentation\ReconnectCachedDatabaseConnections;
use Dedoc\Scramble\Generator;
use Illuminate\Http\JsonResponse;

class DocsJsonController extends Controller
{
    public function __invoke(
        Generator $generator,
        ReconnectCachedDatabaseConnections $reconnectCachedDatabaseConnections,
        ApiDocumentationConfigFactory $configFactory,
    ): JsonResponse {
        $reconnectCachedDatabaseConnections();

        $config = $configFactory->make();

        return response()->json($generator($config), options: JSON_PRETTY_PRINT);
    }
}
