<?php

use App\Http\Controllers\Public\EventsController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\InstitutionsController;
use App\Http\Controllers\Public\SeriesController;
use App\Http\Controllers\Public\SpeakersController;
use App\Http\Controllers\LocaleController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/locale/{locale}', LocaleController::class)->name('locale.switch');

Route::get('/events', [EventsController::class, 'index'])->name('events.index');
Route::get('/events/{event:slug}', [EventsController::class, 'show'])->name('events.show');

Route::get('/institutions', [InstitutionsController::class, 'index'])->name('institutions.index');
Route::get('/institutions/{institution:slug}', [InstitutionsController::class, 'show'])->name('institutions.show');

Route::get('/speakers', [SpeakersController::class, 'index'])->name('speakers.index');
Route::get('/speakers/{speaker:slug}', [SpeakersController::class, 'show'])->name('speakers.show');

Route::get('/series/{series:slug}', [SeriesController::class, 'show'])->name('series.show');
