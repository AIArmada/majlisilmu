<?php

use App\Models\Event;
use App\States\EventStatus\Approved;
use App\States\EventStatus\Draft;
use App\States\EventStatus\Pending;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('active scope filters approved and public events', function () {
    // Active event
    $activeEvent = Event::factory()->create([
        'status' => Approved::class,
        'visibility' => 'public',
    ]);

    // Inactive events
    $draftEvent = Event::factory()->create([
        'status' => Draft::class,
        'visibility' => 'public',
    ]);

    $pendingEvent = Event::factory()->create([
        'status' => Pending::class,
        'visibility' => 'public',
    ]);

    $privateEvent = Event::factory()->create([
        'status' => Approved::class,
        'visibility' => 'private',
    ]);

    $results = Event::active()->get();

    expect($results)->toHaveCount(1)
        ->and($results->first()->id)->toBe($activeEvent->id);
});
