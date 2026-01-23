<?php

namespace App\Providers;

use App\Models\Donation;
use App\Models\Event;
use App\Models\EventSubmission;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use App\Models\Topic;
use App\Models\User;
use App\Models\Venue;
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
            'event_submission' => EventSubmission::class,
            'institution' => Institution::class,
            'speaker' => Speaker::class,
            'venue' => Venue::class,
            'donation' => Donation::class,
            'topic' => Topic::class,
            'reference' => Reference::class,
        ]);
    }
}
