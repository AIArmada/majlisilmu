<?php

use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\User;
use App\Models\Venue;
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
        ->assertJsonPath('data.going_count', 1)
        ->assertJsonPath('meta.request_id', fn (string $requestId) => filled($requestId));

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
        ->assertJsonPath('data.going_count', 1)
        ->assertJsonPath('meta.request_id', fn (string $requestId) => filled($requestId));
});

it('lists the current users going events', function () {
    Sanctum::actingAs($this->user);

    $institution = Institution::factory()->create([
        'name' => 'Masjid Going',
        'slug' => 'masjid-going',
    ]);
    $venue = Venue::factory()->create([
        'name' => 'Dewan Going',
    ]);
    $speaker = Speaker::factory()->create([
        'name' => 'Speaker Going',
        'slug' => 'speaker-going',
    ]);

    $first = Event::factory()->create([
        'title' => 'Going Event One',
        'slug' => 'going-event-one',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(1),
        'event_url' => 'https://example.com/events/going-event-one',
        'institution_id' => $institution->id,
        'venue_id' => $venue->id,
    ]);
    $second = Event::factory()->create([
        'title' => 'Going Event Two',
        'status' => 'cancelled',
        'visibility' => 'public',
        'starts_at' => now()->addDays(2),
    ]);

    $first->speakers()->attach($speaker->id);

    $this->user->goingEvents()->attach([$first->id, $second->id]);

    $this->getJson(route('api.user.going-events.index'))
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 2)
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('data.0.title', 'Going Event One')
        ->assertJsonPath('data.0.slug', 'going-event-one')
        ->assertJsonPath('data.0.status', 'approved')
        ->assertJsonPath('data.0.visibility', 'public')
        ->assertJsonPath('data.0.event_url', 'https://example.com/events/going-event-one')
        ->assertJsonPath('data.0.institution.id', $institution->id)
        ->assertJsonPath('data.0.institution.name', 'Masjid Going')
        ->assertJsonPath('data.0.institution.slug', $institution->slug)
        ->assertJsonPath('data.0.venue.id', $venue->id)
        ->assertJsonPath('data.0.venue.name', 'Dewan Going')
        ->assertJsonPath('data.0.speakers.0.id', $speaker->id)
        ->assertJsonPath('data.0.speakers.0.name', 'Speaker Going')
        ->assertJsonPath('data.0.speakers.0.slug', $speaker->slug)
        ->assertJsonPath('data.0.speakers.0.pivot.event_id', $first->id)
        ->assertJsonPath('data.0.pivot.event_id', $first->id)
        ->assertJsonPath('data.0.pivot.user_id', $this->user->id)
        ->assertJsonPath('meta.request_id', fn (string $requestId) => filled($requestId));
});

it('allows an authenticated user to remove a going record', function () {
    Sanctum::actingAs($this->user);

    $this->postJson(route('api.events.going.store', $this->event));

    $this->deleteJson(route('api.events.going.destroy', $this->event))
        ->assertOk()
        ->assertJsonPath('message', 'Going removed successfully.')
        ->assertJsonPath('data.message', 'Going removed successfully.')
        ->assertJsonPath('data.going_count', 0)
        ->assertJsonPath('meta.request_id', fn (string $requestId) => filled($requestId));

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

it('keeps missing institution and venue relations as null in the going events list', function () {
    Sanctum::actingAs($this->user);

    $event = Event::factory()->create([
        'title' => 'Going Event Without Relations',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(4),
        'institution_id' => null,
        'venue_id' => null,
    ]);

    $this->user->goingEvents()->attach($event->id);

    $this->getJson(route('api.user.going-events.index'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $event->id)
        ->assertJsonPath('data.0.institution', null)
        ->assertJsonPath('data.0.venue', null);
});

it('returns a conflict response when the user already marked going', function () {
    Sanctum::actingAs($this->user);

    $this->postJson(route('api.events.going.store', $this->event))
        ->assertCreated();

    $this->postJson(route('api.events.going.store', $this->event))
        ->assertConflict()
        ->assertJsonPath('error.code', 'conflict')
        ->assertJsonPath('error.message', 'You have already marked going for this event.');
});
