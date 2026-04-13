<?php

use App\Models\Event;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->event = Event::factory()->create(['status' => 'approved', 'visibility' => 'public']);
});

test('authenticated user can save an event', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson(route('api.event-saves.store'), [
        'event_id' => $this->event->id,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('message', 'Event saved successfully.')
        ->assertJsonPath('data.message', 'Event saved successfully.')
        ->assertJsonPath('meta.request_id', fn (string $requestId) => filled($requestId));

    $this->assertDatabaseHas('event_saves', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
    ]);
});

test('unauthenticated user cannot save an event', function () {
    $response = $this->postJson(route('api.event-saves.store'), [
        'event_id' => $this->event->id,
    ]);

    $response->assertStatus(401);
});

test('cannot save the same event twice', function () {
    Sanctum::actingAs($this->user);

    // Save first time
    $this->postJson(route('api.event-saves.store'), ['event_id' => $this->event->id]);

    // Save second time
    $response = $this->postJson(route('api.event-saves.store'), [
        'event_id' => $this->event->id,
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'conflict');
});

test('authenticated user can unsave an event', function () {
    Sanctum::actingAs($this->user);

    // Save first
    $this->postJson(route('api.event-saves.store'), ['event_id' => $this->event->id]);

    // Unsave
    $response = $this->deleteJson(route('api.event-saves.destroy', $this->event->id));

    $response->assertStatus(200)
        ->assertJsonPath('message', 'Event unsaved successfully.')
        ->assertJsonPath('data.message', 'Event unsaved successfully.')
        ->assertJsonPath('meta.request_id', fn (string $requestId) => filled($requestId));

    $this->assertDatabaseMissing('event_saves', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
    ]);
});

test('cannot unsave an event that is not saved', function () {
    Sanctum::actingAs($this->user);

    $response = $this->deleteJson(route('api.event-saves.destroy', $this->event->id));

    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');
});

test('can check if event is saved', function () {
    Sanctum::actingAs($this->user);

    // Initially not saved
    $this->getJson(route('api.event-saves.show', $this->event->id))
        ->assertJsonPath('data.is_saved', false);

    // Save it
    $this->postJson(route('api.event-saves.store'), ['event_id' => $this->event->id]);

    // Now saved
    $this->getJson(route('api.event-saves.show', $this->event->id))
        ->assertJsonPath('data.is_saved', true);
});

test('saving an event recalculates stale saves_count from source rows', function () {
    Sanctum::actingAs($this->user);

    $this->event->update(['saves_count' => 99]);

    $response = $this->postJson(route('api.event-saves.store'), [
        'event_id' => $this->event->id,
    ]);

    $response->assertStatus(201);

    $this->event->refresh();

    expect($this->event->saves_count)->toBe(1);
});

test('saved events index still includes cancelled events', function () {
    Sanctum::actingAs($this->user);

    $cancelledEvent = Event::factory()->create([
        'status' => 'cancelled',
        'visibility' => 'public',
        'starts_at' => now()->addDays(10),
    ]);

    $this->user->savedEvents()->attach($this->event->id);
    $this->user->savedEvents()->attach($cancelledEvent->id);

    $response = $this->getJson(route('api.event-saves.index'));

    $response->assertOk()
        ->assertJsonPath('meta.pagination.total', 2);
});
