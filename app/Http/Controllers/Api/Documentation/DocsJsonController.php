<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Documentation;

use App\Http\Controllers\Controller;
use App\Support\ApiDocumentation\ApiDocumentationConfigFactory;
use App\Support\ApiDocumentation\ApiDocumentationUrlResolver;
use App\Support\ApiDocumentation\ApiDocumentationVersionResolver;
use App\Support\ApiDocumentation\ReconnectCachedDatabaseConnections;
use Dedoc\Scramble\Generator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class DocsJsonController extends Controller
{
    public function __invoke(
        Generator $generator,
        ReconnectCachedDatabaseConnections $reconnectCachedDatabaseConnections,
        ApiDocumentationConfigFactory $configFactory,
        ApiDocumentationUrlResolver $urlResolver,
        ApiDocumentationVersionResolver $versionResolver,
    ): JsonResponse {
        $cacheScope = sha1(implode('|', [
            app()->environment(),
            $urlResolver->apiBaseUrl(),
            (string) config('scramble.api_domain', ''),
            (string) config('scramble.api_path', 'api/v1'),
        ]));

        $cacheKey = 'api-documentation:openapi-json:'.$cacheScope.':'.$versionResolver->current();
        $latestCacheKeyPointer = 'api-documentation:openapi-json:latest-key:'.$cacheScope;
        $previousCacheKey = Cache::get($latestCacheKeyPointer);

        /** @var array<string, mixed> $document */
        $document = Cache::rememberForever(
            $cacheKey,
            function () use ($reconnectCachedDatabaseConnections, $configFactory, $generator): array {
                if (function_exists('set_time_limit')) {
                    @set_time_limit(120);
                }

                $reconnectCachedDatabaseConnections();

                return $generator($configFactory->make());
            },
        );

        if ($previousCacheKey !== $cacheKey) {
            if (is_string($previousCacheKey) && $previousCacheKey !== '') {
                Cache::forget($previousCacheKey);
            }

            Cache::forever($latestCacheKeyPointer, $cacheKey);
        }

        return response()->json($document, options: JSON_PRETTY_PRINT);
    }
}
