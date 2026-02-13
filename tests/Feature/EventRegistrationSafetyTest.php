<?php

use App\Models\Event;
use App\Models\EventSession;
use App\Models\EventSettings;
use App\Models\Registration;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('enforces capacity using live registration rows when counter is stale', function () {
    $event = Event::factory()
        ->has(EventSettings::factory()->state([
            'registration_required' => true,
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDay(),
            'capacity' => 1,
        ]), 'settings')
        ->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'registrations_count' => 0,
        ]);

    Registration::factory()->create([
        'event_id' => $event->id,
        'status' => 'registered',
        'name' => 'Existing Registrant',
        'email' => 'existing@example.com',
    ]);

    $response = $this
        ->withSession(['_token' => 'test-token'])
        ->post("/events/{$event->slug}/register", [
            '_token' => 'test-token',
            'name' => 'New Registrant',
            'email' => 'new@example.com',
        ]);

    $response->assertSessionHasErrors(['registration']);

    expect(Registration::query()->where('event_id', $event->id)->count())->toBe(1);
});

it('requires session selection when registration mode is per-session', function () {
    $event = Event::factory()
        ->has(EventSettings::factory()->state([
            'registration_required' => true,
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDay(),
            'registration_mode' => 'session',
        ]), 'settings')
        ->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
        ]);

    EventSession::factory()->create([
        'event_id' => $event->id,
        'starts_at' => now()->addDays(2)->setTime(20, 0),
        'ends_at' => now()->addDays(2)->setTime(22, 0),
        'status' => 'scheduled',
    ]);

    $response = $this
        ->withSession(['_token' => 'test-token'])
        ->post("/events/{$event->slug}/register", [
            '_token' => 'test-token',
            'name' => 'Registrant',
            'email' => 'session-required@example.com',
        ]);

    $response->assertSessionHasErrors(['event_session_id']);
});

it('enforces uniqueness per session in per-session registration mode', function () {
    $event = Event::factory()
        ->has(EventSettings::factory()->state([
            'registration_required' => true,
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDay(),
            'registration_mode' => 'session',
            'capacity' => 10,
        ]), 'settings')
        ->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
        ]);

    $sessionA = EventSession::factory()->create([
        'event_id' => $event->id,
        'starts_at' => now()->addDays(2)->setTime(20, 0),
        'ends_at' => now()->addDays(2)->setTime(22, 0),
        'status' => 'scheduled',
    ]);

    $sessionB = EventSession::factory()->create([
        'event_id' => $event->id,
        'starts_at' => now()->addDays(3)->setTime(20, 0),
        'ends_at' => now()->addDays(3)->setTime(22, 0),
        'status' => 'scheduled',
    ]);

    $first = $this
        ->withSession(['_token' => 'test-token'])
        ->post("/events/{$event->slug}/register", [
            '_token' => 'test-token',
            'name' => 'Registrant',
            'email' => 'same@example.com',
            'event_session_id' => $sessionA->id,
        ]);

    $first->assertSessionHasNoErrors();

    $duplicateInSameSession = $this
        ->withSession(['_token' => 'test-token'])
        ->post("/events/{$event->slug}/register", [
            '_token' => 'test-token',
            'name' => 'Registrant Again',
            'email' => 'same@example.com',
            'event_session_id' => $sessionA->id,
        ]);

    $duplicateInSameSession->assertSessionHasErrors(['registration']);

    $sameContactDifferentSession = $this
        ->withSession(['_token' => 'test-token'])
        ->post("/events/{$event->slug}/register", [
            '_token' => 'test-token',
            'name' => 'Registrant Other Session',
            'email' => 'same@example.com',
            'event_session_id' => $sessionB->id,
        ]);

    $sameContactDifferentSession->assertSessionHasNoErrors();

    expect(
        Registration::query()
            ->where('event_id', $event->id)
            ->where('status', 'registered')
            ->count()
    )->toBe(2);
});
