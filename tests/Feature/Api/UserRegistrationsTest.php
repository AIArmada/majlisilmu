<?php

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

test('authenticated user can list own registrations', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $event = Event::factory()->create([
        'title' => 'User Registration Event',
        'status' => 'approved',
        'visibility' => 'public',
    ]);

    $otherEvent = Event::factory()->create([
        'title' => 'Other Registration Event',
        'status' => 'approved',
        'visibility' => 'public',
    ]);

    Registration::factory()->for($event)->for($user)->create([
        'status' => 'registered',
    ]);

    Registration::factory()->for($otherEvent)->for($otherUser)->create([
        'status' => 'registered',
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(route('api.user.registrations.index'));

    $response->assertSuccessful()
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.event.title', 'User Registration Event');
});

test('unauthenticated user cannot list registrations', function () {
    $response = $this->getJson(route('api.user.registrations.index'));

    $response->assertUnauthorized();
});
