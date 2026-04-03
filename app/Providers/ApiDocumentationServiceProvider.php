<?php

namespace App\Providers;

use App\Models\User;
use Dedoc\Scramble\Generator;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class ApiDocumentationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('viewApiDocs', function (?User $user = null): bool {
            if (app()->runningUnitTests()) {
                return true;
            }

            return $user instanceof User
                && $user->hasApplicationAdminAccess();
        });

        Scramble::ignoreDefaultRoutes();

        $apiDomain = (string) config('scramble.api_domain');
        $apiPath = trim((string) config('scramble.api_path', 'api/v1'), '/');
        $docsViewPath = base_path('vendor/dedoc/scramble/resources/views/docs.blade.php');
        $docsMiddleware = config('scramble.middleware', []);
        $configureDocs = static function () use ($apiPath) {
            return Scramble::configure()
                ->useConfig(config('scramble'))
                ->routes(static function (IlluminateRoute $route) use ($apiPath): bool {
                    return $apiPath === '' || Str::startsWith($route->uri(), $apiPath);
                });
        };

        $this->app->booted(function () use ($apiDomain, $configureDocs, $docsMiddleware, $docsViewPath): void {
            $registerDocsRoutes = function () use ($configureDocs, $docsMiddleware, $docsViewPath): void {
                Route::middleware($docsMiddleware)->group(function () use ($configureDocs, $docsViewPath): void {
                    Route::get('docs', function (Generator $generator) use ($configureDocs, $docsViewPath) {
                        $config = $configureDocs();

                        return view()->file($docsViewPath, [
                            'spec' => $generator($config),
                            'config' => $config,
                        ]);
                    })->name('scramble.docs.ui');

                    Route::get('docs.json', function (Generator $generator) use ($configureDocs) {
                        $config = $configureDocs();

                        return response()->json($generator($config), options: JSON_PRETTY_PRINT);
                    })->name('scramble.docs.document');
                });
            };

            if ($apiDomain !== '') {
                Route::domain($apiDomain)->group($registerDocsRoutes);

                return;
            }

            $registerDocsRoutes();
        });
    }
}
