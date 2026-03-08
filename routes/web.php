<?php

use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Public\EventsController;
use App\Livewire\Pages\About\Show as AboutPage;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages.⚡home')->name('home');
Route::livewire('/glm', \App\Livewire\Pages\Home\GlmHome::class)->name('home.glm');
Route::livewire('/tentang-kami', AboutPage::class)->name('about');
Route::get('/bahasa/{locale}', LocaleController::class)->name('locale.switch');

// Socialite OAuth Routes
Route::get('/oauth/{provider}/redirect', [SocialiteController::class, 'redirect'])
    ->name('socialite.redirect')
    ->whereIn('provider', ['google']);
Route::get('/oauth/{provider}/callback', [SocialiteController::class, 'callback'])
    ->name('socialite.callback')
    ->whereIn('provider', ['google']);

// Authentication is handled by Fortify

// Events (with search rate limiting)
Route::livewire('/majlis', 'pages.events.index')
    ->middleware('throttle:search')
    ->name('events.index');
Route::livewire('/majlis/{event:slug}', 'pages.events.show')->name('events.show');
Route::get('/majlis/{event:slug}/kalendar.ics', [EventsController::class, 'calendar'])->name('events.calendar');

// Event Submission (Public)
Route::livewire('/hantar-majlis', 'pages.submit-event.create')
    ->name('submit-event.create');
Route::livewire('/hantar-majlis/berjaya', 'pages.submit-event.success')->name('submit-event.success');

Route::middleware('auth')->group(function () {
    Route::livewire('/papan-pemuka', \App\Livewire\Pages\Dashboard\UserDashboard::class)->name('dashboard');
    Route::livewire('/papan-pemuka/institusi', \App\Livewire\Pages\Dashboard\InstitutionDashboard::class)->name('dashboard.institutions');
    Route::livewire('/papan-pemuka/majlis/cipta-lanjutan', \App\Livewire\Pages\Dashboard\Events\CreateAdvanced::class)->name('dashboard.events.create-advanced');
    Route::livewire('/papan-pemuka/majlis/{event}/jadual', \App\Livewire\Pages\Dashboard\Events\Schedule::class)->name('dashboard.events.schedule');
    Route::livewire('/carian-tersimpan', \App\Livewire\Pages\SavedSearches\Index::class)->name('saved-searches.index');
});

// Event Registration - Rate limited
Route::post('/majlis/{event:slug}/daftar', [EventsController::class, 'register'])
    ->middleware('throttle:registration')
    ->name('events.register');

// Institutions (with search rate limiting)
Route::livewire('/institusi', 'pages.institutions.index')
    ->middleware('throttle:search')
    ->name('institutions.index');
Route::livewire('/institusi/{institution:slug}', 'pages.institutions.show')->name('institutions.show');

// Speakers (with search rate limiting)
Route::livewire('/penceramah', 'pages.speakers.index')
    ->middleware('throttle:search')
    ->name('speakers.index');
Route::livewire('/penceramah/{speaker:slug}', 'pages.speakers.show')->name('speakers.show');

// Series
Route::livewire('/siri/{series:slug}', 'pages.series.show')->name('series.show');

// References
Route::livewire('/rujukan/{reference}', 'pages.references.show')->name('references.show');

// Sitemap
Route::get('/peta-laman.xml', [\App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/peta-laman-majlis.xml', [\App\Http\Controllers\SitemapController::class, 'events'])->name('sitemap.events');
Route::get('/peta-laman-institusi.xml', [\App\Http\Controllers\SitemapController::class, 'institutions'])->name('sitemap.institutions');
Route::get('/peta-laman-penceramah.xml', [\App\Http\Controllers\SitemapController::class, 'speakers'])->name('sitemap.speakers');

// Legacy URI aliases (temporary backward compatibility)
Route::get('/locale/{locale}', LocaleController::class);
Route::livewire('/events', 'pages.events.index')->middleware('throttle:search');
Route::livewire('/events/{event:slug}', 'pages.events.show');
Route::get('/events/{event:slug}/calendar.ics', [EventsController::class, 'calendar']);
Route::livewire('/submit-event', 'pages.submit-event.create');
Route::livewire('/submit-event/success', 'pages.submit-event.success');
Route::livewire('/about', AboutPage::class);
Route::post('/events/{event:slug}/register', [EventsController::class, 'register'])
    ->middleware('throttle:registration');
Route::livewire('/institutions', 'pages.institutions.index')->middleware('throttle:search');
Route::livewire('/institutions/{institution:slug}', 'pages.institutions.show');
Route::livewire('/speakers', 'pages.speakers.index')->middleware('throttle:search');
Route::livewire('/speakers/{speaker:slug}', 'pages.speakers.show');
Route::livewire('/series/{series:slug}', 'pages.series.show');
Route::redirect('/sitemap.xml', '/peta-laman.xml', 301);
Route::redirect('/sitemap-events.xml', '/peta-laman-majlis.xml', 301);
Route::redirect('/sitemap-institutions.xml', '/peta-laman-institusi.xml', 301);
Route::redirect('/sitemap-speakers.xml', '/peta-laman-penceramah.xml', 301);

Route::middleware('auth')->group(function () {
    Route::livewire('/dashboard', \App\Livewire\Pages\Dashboard\UserDashboard::class);
    Route::livewire('/dashboard/institutions', \App\Livewire\Pages\Dashboard\InstitutionDashboard::class);
    Route::livewire('/dashboard/events/create-advanced', \App\Livewire\Pages\Dashboard\Events\CreateAdvanced::class);
    Route::livewire('/dashboard/events/{event}/schedule', \App\Livewire\Pages\Dashboard\Events\Schedule::class);
    Route::livewire('/saved-searches', \App\Livewire\Pages\SavedSearches\Index::class);
});
