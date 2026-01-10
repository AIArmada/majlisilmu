<?php

namespace App\Providers;

use App\Models\DonationAccount;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
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
        Relation::enforceMorphMap([
            'event' => Event::class,
            'institution' => Institution::class,
            'speaker' => Speaker::class,
            'donation_account' => DonationAccount::class,
        ]);
    }
}
