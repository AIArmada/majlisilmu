<?php

namespace App\Providers;

use App\Support\ApiDocumentation\ApiDocumentationUrlResolver;
use App\Support\ApiDocumentation\ApiExceptionToResponseExtension;
use App\Support\ApiDocumentation\ApiRequestBodyExamplesExtension;
use App\Support\ApiDocumentation\ApiSecurityRequirementExtension;
use App\Support\ApiDocumentation\ApiWorkflowSchemasTransformer;
use App\Support\ApiDocumentation\PublicDirectorySchemasTransformer;
use App\Support\ApiDocumentation\ReconnectCachedDatabaseConnections;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class ApiDocumentationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Scramble::ignoreDefaultRoutes();
        Scramble::registerExtension(ApiExceptionToResponseExtension::class);

        $urlResolver = app(ApiDocumentationUrlResolver::class);
        $apiDomain = $urlResolver->apiDomain();
        $apiPath = trim((string) config('scramble.api_path', 'api/v1'), '/');
        $docsViewPath = base_path('vendor/dedoc/scramble/resources/views/docs.blade.php');
        $docsMiddleware = config('scramble.middleware', []);
        $mobileRefPath = base_path('docs/MAJLISILMU_MOBILE_API_REFERENCE.md');
        $configureDocs = function () use ($apiPath, $mobileRefPath, $urlResolver) {
            $config = config('scramble');
            $config['info']['description'] = $this->docsDescription($mobileRefPath, $urlResolver, (string) ($config['info']['description'] ?? ''));

            return Scramble::configure()
                ->useConfig($config)
                ->withOperationTransformers([
                    ApiSecurityRequirementExtension::class,
                    ApiRequestBodyExamplesExtension::class,
                ])
                ->withDocumentTransformers([
                    PublicDirectorySchemasTransformer::class,
                    ApiWorkflowSchemasTransformer::class,
                ])
                ->routes(static fn (IlluminateRoute $route): bool => $apiPath === '' || Str::startsWith($route->uri(), $apiPath));
        };

        $this->app->booted(function () use ($apiDomain, $configureDocs, $docsMiddleware, $docsViewPath): void {
            $registerDocsRoutes = function () use ($configureDocs, $docsMiddleware, $docsViewPath): void {
                Route::middleware($docsMiddleware)->group(function () use ($configureDocs, $docsViewPath): void {
                    Route::get('docs', function (Generator $generator, ReconnectCachedDatabaseConnections $reconnectCachedDatabaseConnections) use ($configureDocs, $docsViewPath) {
                        $reconnectCachedDatabaseConnections();

                        $config = $configureDocs();

                        return view()->file($docsViewPath, [
                            'spec' => $generator($config),
                            'config' => $config,
                        ]);
                    })->name('scramble.docs.ui');

                    Route::get('docs.json', function (Generator $generator, ReconnectCachedDatabaseConnections $reconnectCachedDatabaseConnections) use ($configureDocs) {
                        $reconnectCachedDatabaseConnections();

                        $config = $configureDocs();

                        return response()->json($generator($config), options: JSON_PRETTY_PRINT);
                    })->name('scramble.docs.document');
                });
            };

            if (filled($apiDomain)) {
                Route::domain($apiDomain)->group($registerDocsRoutes);

                return;
            }

            $registerDocsRoutes();
        });
    }

    private function docsDescription(string $mobileRefPath, ApiDocumentationUrlResolver $urlResolver, string $baseDescription): string
    {
        $description = trim($baseDescription);
        $description .= "\n\n---\n\n".$this->aiQuickstartMarkdown($urlResolver);

        if (file_exists($mobileRefPath)) {
            $mobileRefContent = file_get_contents($mobileRefPath);

            if ($mobileRefContent !== false) {
                $description .= "\n\n---\n\n".trim($mobileRefContent);
            }
        }

        return $description;
    }

    private function aiQuickstartMarkdown(ApiDocumentationUrlResolver $urlResolver): string
    {
        $apiBaseUrl = $urlResolver->apiBaseUrl();
        $publicManifestUrl = $apiBaseUrl.'/manifest';
        $adminManifestUrl = $apiBaseUrl.'/admin/manifest';

        return <<<MD
AI QUICKSTART:

1. Read {$urlResolver->docsJsonUrl()} first for the complete OpenAPI contract.
2. Use {$urlResolver->docsUrl()} for the human-readable overview and integration notes.
3. For public and client workflows, discover the live contract at {$publicManifestUrl}.
4. For admin workflows, discover writable resources at {$adminManifestUrl}.
5. Before any write, fetch the exact schema first: `GET /forms/*` on the public surface, or `GET /admin/{resourceKey}/schema` on the admin surface.
6. Admin mutation paths require UUID `id` values returned by admin collection or record endpoints. Do not use `route_key` or public slugs for admin writes.
7. Treat `error.code` as the machine-readable failure type and `meta.request_id` as the trace identifier.
MD;
    }
}
