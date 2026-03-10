<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventInterestController;
use App\Http\Controllers\Api\EventSaveController;
use App\Http\Controllers\Api\InspirationController;
use App\Http\Controllers\Api\InstitutionController;
use App\Http\Controllers\Api\RegistrationController;
use App\Http\Controllers\Api\RegistrationExportController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SavedSearchController;
use App\Http\Controllers\Api\SpeakerController;
use App\Http\Controllers\Api\UserProfileController;
use App\Http\Controllers\Api\UserRegistrationController;
use Illuminate\Support\Facades\Route;

// ──────────────────────────────────────────────────────
// Authentication (no auth required)
// ──────────────────────────────────────────────────────
Route::prefix('v1/auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::post('/register', [AuthController::class, 'register'])->name('api.auth.register');
});

// ──────────────────────────────────────────────────────
// Public API endpoints
// ──────────────────────────────────────────────────────
Route::prefix('v1')->group(function () {
    // Events
    Route::get('/events', [EventController::class, 'index'])->name('api.events.index');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('api.events.show');

    // Institutions
    Route::get('/institutions', [InstitutionController::class, 'index'])->name('api.institutions.index');
    Route::get('/institutions/{institution}', [InstitutionController::class, 'show'])->name('api.institutions.show');
    Route::get('/institutions/{institution}/events', [InstitutionController::class, 'events'])->name('api.institutions.events');

    // Speakers
    Route::get('/speakers', [SpeakerController::class, 'index'])->name('api.speakers.index');
    Route::get('/speakers/{speaker}', [SpeakerController::class, 'show'])->name('api.speakers.show');
    Route::get('/speakers/{speaker}/events', [SpeakerController::class, 'events'])->name('api.speakers.events');

    // Inspirations
    Route::get('/inspirations', [InspirationController::class, 'index'])->name('api.inspirations.index');
    Route::get('/inspirations/daily', [InspirationController::class, 'daily'])->name('api.inspirations.daily');
    Route::get('/inspirations/{inspiration}', [InspirationController::class, 'show'])->name('api.inspirations.show');

    // Reports (Rate limited)
    Route::post('/reports', [ReportController::class, 'store'])
        ->middleware('throttle:reports')
        ->name('api.reports.store');
});

// ──────────────────────────────────────────────────────
// Authenticated API endpoints (Sanctum token required)
// ──────────────────────────────────────────────────────
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Auth
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');

    // User Profile
    Route::get('/user', [UserProfileController::class, 'show'])->name('api.user.show');
    Route::put('/user', [UserProfileController::class, 'update'])->name('api.user.update');
    Route::put('/user/password', [UserProfileController::class, 'updatePassword'])->name('api.user.password');
    Route::get('/user/following', [UserProfileController::class, 'following'])->name('api.user.following');
    Route::get('/user/registrations', [UserRegistrationController::class, 'index'])->name('api.user.registrations.index');

    // Saved Searches
    Route::apiResource('saved-searches', SavedSearchController::class)->names('api.saved-searches');
    Route::post('/saved-searches/{savedSearch}/execute', [SavedSearchController::class, 'execute'])
        ->name('api.saved-searches.execute');

    // Event Saves / Bookmarks
    Route::get('/event-saves', [EventSaveController::class, 'index'])->name('api.event-saves.index');
    Route::post('/event-saves', [EventSaveController::class, 'store'])->name('api.event-saves.store');
    Route::get('/event-saves/{eventId}', [EventSaveController::class, 'show'])->name('api.event-saves.show');
    Route::delete('/event-saves/{eventId}', [EventSaveController::class, 'destroy'])->name('api.event-saves.destroy');

    // Event Interests
    Route::get('/event-interests', [EventInterestController::class, 'index'])->name('api.event-interests.index');
    Route::post('/event-interests', [EventInterestController::class, 'store'])->name('api.event-interests.store');
    Route::get('/event-interests/{eventId}', [EventInterestController::class, 'show'])->name('api.event-interests.show');
    Route::delete('/event-interests/{eventId}', [EventInterestController::class, 'destroy'])->name('api.event-interests.destroy');

    // Event Registrations
    Route::post('/events/{event}/register', [RegistrationController::class, 'store'])->name('api.events.register');
    Route::delete('/registrations/{registration}', [RegistrationController::class, 'destroy'])->name('api.registrations.destroy');
    Route::get('/events/{event}/registrations/export', [RegistrationExportController::class, 'export'])
        ->name('api.registrations.export');
});
