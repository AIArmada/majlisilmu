<?php

namespace App\Http\Middleware;

use App\Support\Timezone\UserTimezoneResolver;
use Closure;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetFilamentTimezone
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $timezone = UserTimezoneResolver::resolve($request);

        FilamentTimezone::set($timezone);
        if ($request->hasSession()) {
            $request->session()->put('user_timezone', $timezone);
        }

        return $next($request);
    }
}
