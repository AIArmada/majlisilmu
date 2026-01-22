<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class RateLimitServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     * Configures rate limits per documentation B9e.
     */
    public function boot(): void
    {
        // Default API rate limit: 60 per minute
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
        });

        // Public search: 30 per minute per IP
        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(30)->by($request->ip());
        });

        // Event submission: 5 per hour per IP (anti-spam)
        RateLimiter::for('event-submission', function (Request $request) {
            return Limit::perHour(5)->by($request->ip());
        });

        // Registration: 10 per hour per IP
        RateLimiter::for('registration', function (Request $request) {
            return Limit::perHour(10)->by($request->ip());
        });

        // Report submission: 3 per hour per user/IP
        RateLimiter::for('reports', function (Request $request) {
            return Limit::perHour(3)->by($request->user()?->id ?: $request->ip());
        });

        // Saved searches: 20 per minute
        RateLimiter::for('saved-searches', function (Request $request) {
            return Limit::perMinute(20)->by($request->user()?->id ?: $request->ip());
        });

        // Admin/Moderation: Higher limits for moderators
        RateLimiter::for('moderation', function (Request $request) {
            $user = $request->user();

            if ($user?->hasAnyRole(['super_admin', 'moderator'])) {
                return Limit::perMinute(200)->by($user->id);
            }

            return Limit::perMinute(60)->by($request->ip());
        });
    }
}
