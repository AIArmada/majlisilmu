<?php

namespace App\Providers;

use App\Models\DonationChannel;
use App\Models\Event;
use App\Models\EventSubmission;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Series;
use App\Models\Speaker;
use App\Models\User;
use App\Models\Venue;
use App\Observers\EventObserver;
use BezhanSalleh\LanguageSwitch\LanguageSwitch;
use Filament\Forms\Components\Select;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
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

        // Register custom scripts
        FilamentAsset::register([
            Js::make('close-on-select', __DIR__.'/../../resources/js/filament/close-on-select.js'),
        ]);

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
            'reference' => Reference::class,
        ]);

        // Register closeOnSelect macro for Filament Select component
        // This allows multi-select dropdowns to close after each selection
        Select::macro('closeOnSelect', function (bool $condition = true): static {
            if ($condition) {
                /** @var Select $this */
                $this->extraAttributes([
                    'x-close-on-select' => true,
                ]);
            }

            return $this;
        });
    }
}
