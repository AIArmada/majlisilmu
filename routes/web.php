<?php

use App\Http\Controllers\Auth\SocialiteController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Public\EventsController;
use Illuminate\Support\Facades\Route;

Route::livewire('/', 'pages.⚡home')->name('home');
Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

// Socialite OAuth Routes
Route::get('/oauth/{provider}/redirect', [SocialiteController::class, 'redirect'])
    ->name('socialite.redirect')
    ->whereIn('provider', ['google']);
Route::get('/oauth/{provider}/callback', [SocialiteController::class, 'callback'])
    ->name('socialite.callback')
    ->whereIn('provider', ['google']);

// Authentication is handled by Fortify

// Events (with search rate limiting)
Route::livewire('/events', 'pages.events.index')
    ->middleware('throttle:search')
    ->name('events.index');
Route::livewire('/events/{event:slug}', 'pages.events.show')->name('events.show');
Route::get('/events/{event:slug}/calendar.ics', [EventsController::class, 'calendar'])->name('events.calendar');

// Event Submission (Public) - Rate limited to prevent spam
Route::livewire('/submit-event', 'pages.submit-event.create')->name('submit-event.create');
Route::livewire('/submit-event/success', 'pages.submit-event.success')->name('submit-event.success');

// Event Registration - Rate limited
Route::post('/events/{event:slug}/register', [EventsController::class, 'register'])
    ->middleware('throttle:registration')
    ->name('events.register');

// Institutions (with search rate limiting)
Route::livewire('/institutions', 'pages.institutions.index')
    ->middleware('throttle:search')
    ->name('institutions.index');
Route::livewire('/institutions/{institution:slug}', 'pages.institutions.show')->name('institutions.show');

// Speakers (with search rate limiting)
Route::livewire('/speakers', 'pages.speakers.index')
    ->middleware('throttle:search')
    ->name('speakers.index');
Route::livewire('/speakers/{speaker:slug}', 'pages.speakers.show')->name('speakers.show');

// Series
Route::livewire('/series/{series:slug}', 'pages.series.show')->name('series.show');

// Sitemap
Route::get('/sitemap.xml', [\App\Http\Controllers\SitemapController::class, 'index'])->name('sitemap.index');
Route::get('/sitemap-events.xml', [\App\Http\Controllers\SitemapController::class, 'events'])->name('sitemap.events');
Route::get('/sitemap-institutions.xml', [\App\Http\Controllers\SitemapController::class, 'institutions'])->name('sitemap.institutions');
Route::get('/sitemap-speakers.xml', [\App\Http\Controllers\SitemapController::class, 'speakers'])->name('sitemap.speakers');
