<?php

namespace App\Providers;

use App\Models\Donation;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\User;
use App\Observers\EventObserver;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers
        Event::observe(EventObserver::class);

        Relation::enforceMorphMap([
            'user' => User::class,
            'event' => Event::class,
            'event_submission' => \App\Models\EventSubmission::class,
            'institution' => Institution::class,
            'speaker' => Speaker::class,
            'venue' => \App\Models\Venue::class,
            'donation' => Donation::class,
        ]);
    }
}
