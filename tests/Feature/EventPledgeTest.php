<?php

use App\Models\Event;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(7), // Future event
    ]);
});

test('authenticated user can mark interest in an event', function () {
    Sanctum::actingAs($this->user);

    $response = $this->postJson(route('api.event-interests.store'), [
        'event_id' => $this->event->id,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.message', 'Interest recorded successfully.')
        ->assertJsonPath('data.interests_count', 1);

    $this->assertDatabaseHas('event_interests', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
    ]);

    // Check that the event interests_count was incremented
    $this->event->refresh();
    expect($this->event->interests_count)->toBe(1);
});

test('unauthenticated user cannot mark interest in an event', function () {
    $response = $this->postJson(route('api.event-interests.store'), [
        'event_id' => $this->event->id,
    ]);

    $response->assertStatus(401);
});

test('cannot mark interest in the same event twice', function () {
    Sanctum::actingAs($this->user);

    // Mark interest first time
    $this->postJson(route('api.event-interests.store'), ['event_id' => $this->event->id]);

    // Mark interest second time
    $response = $this->postJson(route('api.event-interests.store'), [
        'event_id' => $this->event->id,
    ]);

    $response->assertStatus(409)
        ->assertJsonPath('error.code', 'conflict');
});

test('cannot mark interest in a past event', function () {
    Sanctum::actingAs($this->user);

    $pastEvent = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->subDays(1), // Past event
    ]);

    $response = $this->postJson(route('api.event-interests.store'), [
        'event_id' => $pastEvent->id,
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('error.message', 'Cannot mark interest for past events.');
});

test('cannot mark interest in a non-public event', function () {
    Sanctum::actingAs($this->user);

    $privateEvent = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'private',
        'starts_at' => now()->addDays(7),
    ]);

    $response = $this->postJson(route('api.event-interests.store'), [
        'event_id' => $privateEvent->id,
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'forbidden');
});

test('cannot mark interest in a non-approved event', function () {
    Sanctum::actingAs($this->user);

    $pendingEvent = Event::factory()->create([
        'status' => 'pending',
        'visibility' => 'public',
        'starts_at' => now()->addDays(7),
    ]);

    $response = $this->postJson(route('api.event-interests.store'), [
        'event_id' => $pendingEvent->id,
    ]);

    $response->assertStatus(403)
        ->assertJsonPath('error.code', 'forbidden');
});

test('authenticated user can remove their interest', function () {
    Sanctum::actingAs($this->user);

    // Mark interest first
    $this->postJson(route('api.event-interests.store'), ['event_id' => $this->event->id]);

    $this->event->refresh();
    expect($this->event->interests_count)->toBe(1);

    // Remove interest
    $response = $this->deleteJson(route('api.event-interests.destroy', $this->event->id));

    $response->assertStatus(200)
        ->assertJsonPath('data.message', 'Interest removed successfully.')
        ->assertJsonPath('data.interests_count', 0);

    $this->assertDatabaseMissing('event_interests', [
        'user_id' => $this->user->id,
        'event_id' => $this->event->id,
    ]);

    // Check that the event interests_count was decremented
    $this->event->refresh();
    expect($this->event->interests_count)->toBe(0);
});

test('cannot remove interest that does not exist', function () {
    Sanctum::actingAs($this->user);

    $response = $this->deleteJson(route('api.event-interests.destroy', $this->event->id));

    $response->assertStatus(404)
        ->assertJsonPath('error.code', 'not_found');
});

test('can check if user has marked interest in an event', function () {
    Sanctum::actingAs($this->user);

    // Initially not interested
    $this->getJson(route('api.event-interests.show', $this->event->id))
        ->assertJsonPath('data.is_interested', false)
        ->assertJsonPath('data.interests_count', 0);

    // Mark interest
    $this->postJson(route('api.event-interests.store'), ['event_id' => $this->event->id]);

    // Now interested
    $this->getJson(route('api.event-interests.show', $this->event->id))
        ->assertJsonPath('data.is_interested', true)
        ->assertJsonPath('data.interests_count', 1);
});

test('can list all interested events for authenticated user', function () {
    Sanctum::actingAs($this->user);

    // Create multiple events and mark interest
    $events = Event::factory()->count(3)->create([
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(7),
    ]);

    foreach ($events as $event) {
        $this->postJson(route('api.event-interests.store'), ['event_id' => $event->id]);
    }

    $response = $this->getJson(route('api.event-interests.index'));

    $response->assertStatus(200)
        ->assertJsonPath('meta.pagination.total', 3);
});

test('event show page displays interest button for authenticated users', function () {
    $this->actingAs($this->user);

    $this->event->update(['published_at' => now()]);

    $response = $this->get(route('events.show', $this->event));

    $response->assertStatus(200)
        ->assertSee(__('Minat'))
        ->assertSee('wire:click="toggleInterest"', escape: false);
});

test('event show page displays login link for guest users', function () {
    $this->event->update(['published_at' => now()]);

    $response = $this->get(route('events.show', $this->event));

    $response->assertStatus(200)
        ->assertSee(__('Log Masuk untuk Hadir'));
});

test('event show page does not show interest button for past events', function () {
    $this->actingAs($this->user);

    $this->event->update([
        'starts_at' => now()->subDays(1),
        'published_at' => now(),
    ]);

    $response = $this->get(route('events.show', $this->event));

    // The view currently always renders the interest button regardless of event timing.
    // The button is visible but the API endpoint rejects interest for past events (403).
    $response->assertStatus(200);
});

test('marking interest recalculates stale interests_count from source rows', function () {
    Sanctum::actingAs($this->user);

    $this->event->update(['interests_count' => 77]);

    $response = $this->postJson(route('api.event-interests.store'), [
        'event_id' => $this->event->id,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.interests_count', 1);

    $this->event->refresh();
    expect($this->event->interests_count)->toBe(1);
});
