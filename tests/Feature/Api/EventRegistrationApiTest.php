<?php

use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\EventSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

function registrationReadyEvent(array $eventOverrides = [], array $settingsOverrides = []): Event
{
    return Event::factory()
        ->has(EventSettings::factory()->state(array_merge([
            'registration_required' => true,
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDay(),
            'registration_mode' => 'event',
        ], $settingsOverrides)), 'settings')
        ->create(array_merge([
            'status' => 'approved',
            'visibility' => EventVisibility::Public,
            'published_at' => now(),
        ], $eventOverrides));
}

it('allows an authenticated user to register through the api', function () {
    $user = User::factory()->create();
    $event = registrationReadyEvent();

    Sanctum::actingAs($user);

    $response = $this->postJson(route('api.events.registrations.store', $event), [
        'name' => 'Registered Mobile User',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.event_id', $event->id)
        ->assertJsonPath('data.user_id', $user->id)
        ->assertJsonPath('data.status', 'registered');

    $this->getJson(route('api.events.registrations.status', $event))
        ->assertOk()
        ->assertJsonPath('data.is_registered', true)
        ->assertJsonPath('data.registration.user_id', $user->id);
});

it('allows a guest to register through the api when contact info is provided', function () {
    $event = registrationReadyEvent();

    $this->postJson(route('api.events.registrations.store', $event), [
        'name' => 'Guest Registrant',
        'email' => 'guest@example.test',
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Guest Registrant')
        ->assertJsonPath('data.email', 'guest@example.test')
        ->assertJsonPath('data.user_id', null);
});

it('rejects guest registration without email or phone', function () {
    $event = registrationReadyEvent();

    $this->postJson(route('api.events.registrations.store', $event), [
        'name' => 'Guest Without Contact',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['contact']);
});

it('allows registration for unlisted events when registration is enabled', function () {
    $event = registrationReadyEvent([
        'visibility' => EventVisibility::Unlisted,
    ]);

    $this->postJson(route('api.events.registrations.store', $event), [
        'name' => 'Unlisted Registrant',
        'email' => 'unlisted@example.test',
    ])->assertCreated()
        ->assertJsonPath('data.event_id', $event->id);
});

it('requires authentication to inspect the current users registration status', function () {
    $event = registrationReadyEvent();

    $this->getJson(route('api.events.registrations.status', $event))
        ->assertUnauthorized();
});
