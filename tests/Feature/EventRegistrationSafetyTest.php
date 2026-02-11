<?php

use App\Models\Event;
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
