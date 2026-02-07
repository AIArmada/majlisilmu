<?php

use App\Jobs\EscalatePendingEvents;
use App\Jobs\SendSavedSearchDigest;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Saved Search Digests (per documentation B4c)
Schedule::job(new SendSavedSearchDigest('daily'))
    ->dailyAt('08:00')
    ->timezone('Asia/Kuala_Lumpur')
    ->name('saved-search-digest-daily')
    ->withoutOverlapping();

Schedule::job(new SendSavedSearchDigest('weekly'))
    ->weeklyOn(1, '08:00') // Every Monday at 8am
    ->timezone('Asia/Kuala_Lumpur')
    ->name('saved-search-digest-weekly')
    ->withoutOverlapping();

// SLA Escalation (per documentation B4a and B6b)
// Runs hourly to check for pending events needing escalation
Schedule::job(new EscalatePendingEvents)
    ->hourly()
    ->timezone('Asia/Kuala_Lumpur')
    ->name('escalate-pending-events')
    ->withoutOverlapping();

// Prune orphaned entities (institutions, speakers, venues) with no events after 48 hours
Schedule::command('app:prune-orphaned-entities')
    ->daily()
    ->timezone('Asia/Kuala_Lumpur')
    ->name('prune-orphaned-entities')
    ->withoutOverlapping();
