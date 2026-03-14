<?php

use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('returns check-in state and records a self-reported check-in for open events', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'published_at' => now()->subDay(),
        'starts_at' => now('Asia/Kuala_Lumpur')->addHour()->utc(),
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $event->settings()->delete();

    Sanctum::actingAs($user);

    $this->getJson(route('api.events.check-in-state.show', $event))
        ->assertOk()
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.method', 'self_reported');

    $this->postJson(route('api.events.check-ins.store', $event))
        ->assertCreated()
        ->assertJsonPath('data.status', 'created')
        ->assertJsonPath('data.checkin.method', 'self_reported');

    expect(EventCheckin::query()
        ->where('event_id', $event->id)
        ->where('user_id', $user->id)
        ->where('method', 'self_reported')
        ->count())->toBe(1);
});

it('requires registration before check-in when the event requires registration', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'published_at' => now()->subDay(),
        'starts_at' => now('Asia/Kuala_Lumpur')->addHour()->utc(),
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $event->settings()->updateOrCreate([], [
        'registration_required' => true,
        'registration_mode' => 'event',
        'registration_opens_at' => now()->subDay(),
        'registration_closes_at' => now()->addDay(),
    ]);

    Sanctum::actingAs($user);

    $this->getJson(route('api.events.check-in-state.show', $event))
        ->assertOk()
        ->assertJsonPath('data.available', false)
        ->assertJsonPath('data.reason', 'Majlis ini memerlukan pendaftaran sebelum check-in.');

    $this->postJson(route('api.events.check-ins.store', $event))
        ->assertForbidden()
        ->assertJsonPath('error.message', 'Majlis ini memerlukan pendaftaran sebelum check-in.');
});

it('uses the registered check-in path when the user already has a registration', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'published_at' => now()->subDay(),
        'starts_at' => now('Asia/Kuala_Lumpur')->addHour()->utc(),
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $event->settings()->updateOrCreate([], [
        'registration_required' => true,
        'registration_mode' => 'event',
        'registration_opens_at' => now()->subDay(),
        'registration_closes_at' => now()->addDay(),
    ]);

    $registration = Registration::factory()->create([
        'event_id' => $event->id,
        'user_id' => $user->id,
        'status' => 'registered',
    ]);

    Sanctum::actingAs($user);

    $this->getJson(route('api.events.check-in-state.show', $event))
        ->assertOk()
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.method', 'registered_self_checkin')
        ->assertJsonPath('data.registration_id', $registration->id);

    $this->postJson(route('api.events.check-ins.store', $event))
        ->assertCreated()
        ->assertJsonPath('data.checkin.method', 'registered_self_checkin')
        ->assertJsonPath('data.checkin.registration_id', $registration->id);
});

it('returns a duplicate status instead of creating a second check-in', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'published_at' => now()->subDay(),
        'starts_at' => now('Asia/Kuala_Lumpur')->addHour()->utc(),
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $event->settings()->delete();

    Sanctum::actingAs($user);

    $this->postJson(route('api.events.check-ins.store', $event))
        ->assertCreated();

    $this->postJson(route('api.events.check-ins.store', $event))
        ->assertOk()
        ->assertJsonPath('data.status', 'duplicate');

    expect(EventCheckin::query()
        ->where('event_id', $event->id)
        ->where('user_id', $user->id)
        ->count())->toBe(1);
});
