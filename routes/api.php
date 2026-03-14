<?php

use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventInterestController;
use App\Http\Controllers\Api\EventSaveController;
use App\Http\Controllers\Api\NotificationDestinationController;
use App\Http\Controllers\Api\NotificationMessageController;
use App\Http\Controllers\Api\NotificationSettingsController;
use App\Http\Controllers\Api\RegistrationExportController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SavedSearchController;
use App\Http\Controllers\Api\UserRegistrationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Public API endpoints
Route::prefix('v1')->group(function () {
    // Events API with query builder
    Route::get('/events', [EventController::class, 'index'])->name('api.events.index');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('api.events.show');

});

// Authenticated API endpoints
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    // Reports (per documentation B5b) - Rate limited
    Route::post('/reports', [ReportController::class, 'store'])
        ->middleware('throttle:reports')
        ->name('api.reports.store');

    // User
    Route::get('/user', fn (Request $request) => $request->user());
    Route::get('/user/registrations', [UserRegistrationController::class, 'index'])
        ->name('api.user.registrations.index');

    // Saved Searches (per documentation B5a)
    Route::apiResource('saved-searches', SavedSearchController::class)
        ->names('api.saved-searches');
    Route::post('/saved-searches/{savedSearch}/execute', [SavedSearchController::class, 'execute'])
        ->name('api.saved-searches.execute');

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

    // Registration Exports (per documentation B9d)
    Route::get('/events/{event}/registrations/export', [RegistrationExportController::class, 'export'])
        ->name('api.registrations.export');

    // Notification center
    Route::get('/notifications', [NotificationMessageController::class, 'index'])
        ->name('api.notifications.index');
    Route::post('/notifications/{message}/read', [NotificationMessageController::class, 'read'])
        ->name('api.notifications.read');
    Route::post('/notifications/read-all', [NotificationMessageController::class, 'readAll'])
        ->name('api.notifications.read-all');
    Route::get('/notification-settings/catalog', [NotificationSettingsController::class, 'catalog'])
        ->name('api.notification-settings.catalog');
    Route::get('/notification-settings', [NotificationSettingsController::class, 'show'])
        ->name('api.notification-settings.show');
    Route::put('/notification-settings', [NotificationSettingsController::class, 'update'])
        ->name('api.notification-settings.update');
    Route::post('/notification-destinations/push', [NotificationDestinationController::class, 'storePush'])
        ->name('api.notification-destinations.push.store');
    Route::put('/notification-destinations/push/{installation}', [NotificationDestinationController::class, 'updatePush'])
        ->name('api.notification-destinations.push.update');
    Route::delete('/notification-destinations/push/{installation}', [NotificationDestinationController::class, 'destroyPush'])
        ->name('api.notification-destinations.push.destroy');
});
