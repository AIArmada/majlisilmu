<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Blaze\Config as BlazeConfig;
use Livewire\Blaze\Runtime\BlazeRuntime;

class BlazeServiceProvider extends ServiceProvider
{
    #[\Override]
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $shareRuntime = static function (BlazeRuntime $runtime): void {
            View::share('__blaze', $runtime);
        };

        if ($this->app->bound(BlazeRuntime::class)) {
            $shareRuntime($this->app->make(BlazeRuntime::class));
        }

        $this->app->afterResolving(BlazeRuntime::class, $shareRuntime);

        $configure = static function (BlazeConfig $config): void {
            $config
                ->in(resource_path('views/components'))
                ->in(resource_path('views/components/analytics'), compile: false)
                ->in(resource_path('views/components/pages'), compile: false)
                ->in(resource_path('views/components/home'), compile: false)
                ->in(resource_path('views/components/event-json-ld.blade.php'), compile: false)
                ->in(resource_path('views/components/sidebar-inspiration.blade.php'), compile: false)
                ->in(resource_path('views/components/app-logo-icon.blade.php'), memo: true);
        };

        if ($this->app->bound(BlazeConfig::class)) {
            $configure($this->app->make(BlazeConfig::class));
        }

        $this->app->afterResolving(BlazeConfig::class, $configure);
        $this->app->afterResolving('blaze.config', $configure);
    }
}
