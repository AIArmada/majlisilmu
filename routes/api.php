<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventCheckInController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\EventGoingController;
use App\Http\Controllers\Api\EventRegistrationController;
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
    Route::post('/auth/register', [AuthController::class, 'register'])->name('api.auth.register');
    Route::post('/auth/login', [AuthController::class, 'login'])->name('api.auth.login');
    Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])->name('api.auth.forgot-password');
    Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])->name('api.auth.reset-password');

    // Events API with query builder
    Route::get('/events', [EventController::class, 'index'])->name('api.events.index');
    Route::get('/events/{event}', [EventController::class, 'show'])->name('api.events.show');
    Route::post('/events/{event}/registrations', [EventRegistrationController::class, 'store'])
        ->middleware('throttle:registration')
        ->name('api.events.registrations.store');
});

// Authenticated API endpoints
Route::prefix('v1')->middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('api.auth.logout');
    Route::post('/auth/email/verification-notification', [AuthController::class, 'resendVerificationEmail'])
        ->name('api.auth.verification-notification');

    // Reports (per documentation B5b) - Rate limited
    Route::post('/reports', [ReportController::class, 'store'])
        ->middleware('throttle:reports')
        ->name('api.reports.store');

    // User
    Route::get('/user', fn (Request $request) => $request->user());
    Route::get('/user/registrations', [UserRegistrationController::class, 'index'])
        ->name('api.user.registrations.index');
    Route::get('/user/going-events', [EventGoingController::class, 'index'])
        ->name('api.user.going-events.index');

    Route::get('/events/{event}/registration-status', [EventRegistrationController::class, 'status'])
        ->name('api.events.registrations.status');
    Route::get('/events/{event}/check-in-state', [EventCheckInController::class, 'show'])
        ->name('api.events.check-in-state.show');
    Route::post('/events/{event}/check-ins', [EventCheckInController::class, 'store'])
        ->name('api.events.check-ins.store');
    Route::get('/events/{event}/going', [EventGoingController::class, 'show'])
        ->name('api.events.going.show');
    Route::post('/events/{event}/going', [EventGoingController::class, 'store'])
        ->name('api.events.going.store');
    Route::delete('/events/{event}/going', [EventGoingController::class, 'destroy'])
        ->name('api.events.going.destroy');

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
