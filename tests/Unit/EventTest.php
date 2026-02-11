<?php

use App\Models\Event;
use App\States\EventStatus\Approved;
use App\States\EventStatus\Draft;
use App\States\EventStatus\Pending;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('active scope filters approved and pending public events', function () {
    // Active events (approved + pending)
    $approvedEvent = Event::factory()->create([
        'status' => Approved::class,
        'visibility' => 'public',
        'is_active' => true,
    ]);

    $pendingEvent = Event::factory()->create([
        'status' => Pending::class,
        'visibility' => 'public',
        'is_active' => true,
    ]);

    // Inactive events
    $draftEvent = Event::factory()->create([
        'status' => Draft::class,
        'visibility' => 'public',
        'is_active' => true,
    ]);

    $privateEvent = Event::factory()->create([
        'status' => Approved::class,
        'visibility' => 'private',
        'is_active' => true,
    ]);

    $deactivatedEvent = Event::factory()->create([
        'status' => Approved::class,
        'visibility' => 'public',
        'is_active' => false,
    ]);

    $results = Event::active()->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('id')->toArray())->toContain($approvedEvent->id)
        ->and($results->pluck('id')->toArray())->toContain($pendingEvent->id)
        ->and($results->pluck('id')->toArray())->not->toContain($draftEvent->id)
        ->and($results->pluck('id')->toArray())->not->toContain($privateEvent->id)
        ->and($results->pluck('id')->toArray())->not->toContain($deactivatedEvent->id);
});
