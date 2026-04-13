<?php

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(3),
        'going_count' => 0,
    ]);
});

it('allows an authenticated user to mark going for an event', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson(route('api.events.going.store', $this->event));

    $response->assertCreated()
        ->assertJsonPath('message', 'Going recorded successfully.')
        ->assertJsonPath('data.message', 'Going recorded successfully.')
        ->assertJsonPath('data.going_count', 1);

    $this->assertDatabaseHas('event_attendees', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
    ]);
});

it('returns current going state for the authenticated user', function () {
    Sanctum::actingAs($this->user);

    $this->postJson(route('api.events.going.store', $this->event));

    $this->getJson(route('api.events.going.show', $this->event))
        ->assertOk()
        ->assertJsonPath('data.is_going', true)
        ->assertJsonPath('data.going_count', 1);
});

it('lists the current users going events', function () {
    Sanctum::actingAs($this->user);

    $first = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(1),
    ]);
    $second = Event::factory()->create([
        'status' => 'cancelled',
        'visibility' => 'public',
        'starts_at' => now()->addDays(2),
    ]);

    $this->user->goingEvents()->attach([$first->id, $second->id]);

    $this->getJson(route('api.user.going-events.index'))
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 2);
});

it('allows an authenticated user to remove a going record', function () {
    Sanctum::actingAs($this->user);

    $this->postJson(route('api.events.going.store', $this->event));

    $this->deleteJson(route('api.events.going.destroy', $this->event))
        ->assertOk()
        ->assertJsonPath('message', 'Going removed successfully.')
        ->assertJsonPath('data.message', 'Going removed successfully.')
        ->assertJsonPath('data.going_count', 0);

    $this->assertDatabaseMissing('event_attendees', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
    ]);
});

it('recalculates stale going_count from source rows when marking going', function () {
    Sanctum::actingAs($this->user);

    $this->event->update(['going_count' => 17]);

    $this->postJson(route('api.events.going.store', $this->event))
        ->assertCreated()
        ->assertJsonPath('data.going_count', 1);

    expect($this->event->fresh()->going_count)->toBe(1);
});

it('rejects marking going for past events', function () {
    Sanctum::actingAs($this->user);

    $pastEvent = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->subDay(),
    ]);

    $this->postJson(route('api.events.going.store', $pastEvent))
        ->assertForbidden()
        ->assertJsonPath('error.message', 'Cannot mark going for past events.');
});
