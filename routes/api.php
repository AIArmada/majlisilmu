<?php

use App\Http\Controllers\Api\EventInterestController;
use App\Http\Controllers\Api\EventSaveController;
use App\Http\Controllers\Api\RegistrationExportController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SavedSearchController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public API endpoints
Route::prefix('v1')->group(function () {
    // Reports (per documentation B5b) - Rate limited
    Route::post('/reports', [ReportController::class, 'store'])
        ->middleware('throttle:reports')
        ->name('api.reports.store');
});

// Authenticated API endpoints
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // User
    Route::get('/user', fn (Request $request) => $request->user());

    // Saved Searches (per documentation B5a)
    Route::apiResource('saved-searches', SavedSearchController::class);
    Route::post('/saved-searches/{savedSearch}/execute', [SavedSearchController::class, 'execute'])
        ->name('saved-searches.execute');

    // Event Saves / Bookmarks (per documentation B5)
    Route::get('/event-saves', [EventSaveController::class, 'index'])->name('api.event-saves.index');
    Route::post('/event-saves', [EventSaveController::class, 'store'])->name('api.event-saves.store');
    Route::get('/event-saves/{eventId}', [EventSaveController::class, 'show'])->name('api.event-saves.show');
    Route::delete('/event-saves/{eventId}', [EventSaveController::class, 'destroy'])->name('api.event-saves.destroy');

    // Event Interests - Mark interest in events
    Route::get('/event-interests', [EventInterestController::class, 'index'])->name('api.event-interests.index');
    Route::post('/event-interests', [EventInterestController::class, 'store'])->name('api.event-interests.store');
    Route::get('/event-interests/{eventId}', [EventInterestController::class, 'show'])->name('api.event-interests.show');
    Route::delete('/event-interests/{eventId}', [EventInterestController::class, 'destroy'])->name('api.event-interests.destroy');

    // Reports (authenticated users get higher limits)
    Route::post('/reports', [ReportController::class, 'store'])
        ->middleware('throttle:reports')
        ->name('api.reports.store.auth');

    // Registration Exports (per documentation B9d)
    Route::get('/events/{event}/registrations/export', [RegistrationExportController::class, 'export'])
        ->name('api.registrations.export');
});
