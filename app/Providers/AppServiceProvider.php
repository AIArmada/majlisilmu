<?php

namespace App\Providers;

use App\Models\DonationChannel;
use App\Models\Event;
use App\Models\EventSubmission;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Series;
use App\Models\Speaker;
use App\Models\Topic;
use App\Models\User;
use App\Models\Venue;
use App\Observers\EventObserver;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\Support\Facades\FilamentTimezone;
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
        FilamentTimezone::set('Asia/Kuala_Lumpur');

        // Register model observers
        Event::observe(EventObserver::class);

        LanguageSwitch::configureUsing(function (LanguageSwitch $switch): void {
            $switch->locales(['en', 'ms', 'jv', 'ta', 'zh']);
        });

        Relation::enforceMorphMap([
            'user' => User::class,
            'event' => Event::class,
            'event_submission' => EventSubmission::class,
            'institution' => Institution::class,
            'speaker' => Speaker::class,
            'series' => Series::class,
            'venue' => Venue::class,
            'donation_channel' => DonationChannel::class,
            'topic' => Topic::class,
            'reference' => Reference::class,
        ]);
    }
}
