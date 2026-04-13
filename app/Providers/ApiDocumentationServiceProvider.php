<?php

namespace App\Providers;

use App\Support\ApiDocumentation\ApiDocumentationUrlResolver;
use App\Support\ApiDocumentation\ApiExceptionToResponseExtension;
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

        $apiDomain = app(ApiDocumentationUrlResolver::class)->apiDomain();
        $apiPath = trim((string) config('scramble.api_path', 'api/v1'), '/');
        $docsViewPath = base_path('vendor/dedoc/scramble/resources/views/docs.blade.php');
        $docsMiddleware = config('scramble.middleware', []);
        $mobileRefPath = base_path('docs/MAJLISILMU_MOBILE_API_REFERENCE.md');
        $configureDocs = static function () use ($apiPath, $mobileRefPath) {
            $config = config('scramble');
            if (file_exists($mobileRefPath)) {
                $mobileRefContent = file_get_contents($mobileRefPath);
                if ($mobileRefContent !== false) {
                    $config['info']['description'] = ($config['info']['description'] ?? '')
                        ."\n\n---\n\n"
                        .$mobileRefContent;
                }
            }

            return Scramble::configure()
                ->useConfig($config)
                ->withDocumentTransformers(PublicDirectorySchemasTransformer::class)
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
}
