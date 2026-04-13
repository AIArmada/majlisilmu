<?php

use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\User;
use App\Models\Venue;
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
        ->assertOk()
        ->assertJsonPath('data.is_saved', false)
        ->assertJsonPath('meta.request_id', fn (string $requestId) => filled($requestId));

    // Save it
    $this->postJson(route('api.event-saves.store'), ['event_id' => $this->event->id]);

    // Now saved
    $this->getJson(route('api.event-saves.show', $this->event->id))
        ->assertOk()
        ->assertJsonPath('data.is_saved', true)
        ->assertJsonPath('meta.request_id', fn (string $requestId) => filled($requestId));
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

    $institution = Institution::factory()->create([
        'name' => 'Masjid Saved',
        'slug' => 'masjid-saved',
    ]);
    $venue = Venue::factory()->create([
        'name' => 'Dewan Saved',
    ]);
    $speaker = Speaker::factory()->create([
        'name' => 'Speaker Saved',
        'slug' => 'speaker-saved',
    ]);

    $savedEvent = Event::factory()->create([
        'title' => 'Saved Event One',
        'slug' => 'saved-event-one',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(5),
        'institution_id' => $institution->id,
        'venue_id' => $venue->id,
    ]);

    $cancelledEvent = Event::factory()->create([
        'status' => 'cancelled',
        'visibility' => 'public',
        'starts_at' => now()->addDays(10),
    ]);

    $savedEvent->speakers()->attach($speaker->id);

    $this->user->savedEvents()->attach($savedEvent->id);
    $this->user->savedEvents()->attach($cancelledEvent->id);

    $response = $this->getJson(route('api.event-saves.index'));

    $response->assertOk()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('data.0.id', $savedEvent->id)
        ->assertJsonPath('data.0.title', 'Saved Event One')
        ->assertJsonPath('data.0.slug', 'saved-event-one')
        ->assertJsonPath('data.0.status', 'approved')
        ->assertJsonPath('data.0.visibility', 'public')
        ->assertJsonPath('data.0.institution.id', $institution->id)
        ->assertJsonPath('data.0.institution.name', 'Masjid Saved')
        ->assertJsonPath('data.0.institution.slug', $institution->slug)
        ->assertJsonPath('data.0.venue.id', $venue->id)
        ->assertJsonPath('data.0.venue.name', 'Dewan Saved')
        ->assertJsonPath('data.0.speakers.0.id', $speaker->id)
        ->assertJsonPath('data.0.speakers.0.name', 'Speaker Saved')
        ->assertJsonPath('data.0.speakers.0.slug', $speaker->slug)
        ->assertJsonPath('data.0.speakers.0.pivot.event_id', $savedEvent->id)
        ->assertJsonPath('data.0.pivot.event_id', $savedEvent->id)
        ->assertJsonPath('data.0.pivot.user_id', $this->user->id)
        ->assertJsonPath('meta.request_id', fn (string $requestId) => filled($requestId));
});
