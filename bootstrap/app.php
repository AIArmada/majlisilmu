<?php

use App\Http\Middleware\SetFilamentTimezone;
use App\Http\Middleware\SetLocale;
use App\Http\Middleware\TrackDawahShareAttribution;
use App\Support\Location\PublicCountryFilterVisibility;
use App\Support\Location\PublicGeolocationPermission;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        __DIR__.'/../app/Console/Commands',
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: [
            'user_timezone',
            PublicCountryFilterVisibility::COOKIE_NAME,
            PublicGeolocationPermission::COOKIE_NAME,
        ]);

        $middleware->web(append: [
            SetLocale::class,
            TrackDawahShareAttribution::class,
        ]);

        // Set default Filament timezone for every request (fixes Octane state persistence)
        $middleware->append(SetFilamentTimezone::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
