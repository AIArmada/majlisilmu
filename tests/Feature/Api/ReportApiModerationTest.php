<?php

use App\Enums\EventVisibility;
use App\Models\Event;

it('uses moderation transition when a high risk event report is submitted', function () {
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
    ]);

    $response = $this->postJson('/api/v1/reports', [
        'entity_type' => 'event',
        'entity_id' => $event->id,
        'category' => 'donation_scam',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.id', fn ($value) => is_string($value) && $value !== '');

    $event->refresh();

    expect((string) $event->status)->toBe('pending');

    $this->assertDatabaseHas('moderation_reviews', [
        'event_id' => $event->id,
        'decision' => 'remoderated',
    ]);
});

it('prevents duplicate anonymous reports from the same reporter fingerprint within 24 hours', function () {
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
    ]);

    $payload = [
        'entity_type' => 'event',
        'entity_id' => $event->id,
        'category' => 'wrong_info',
    ];

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeaders(['User-Agent' => 'MajlisIlmu-Test-Agent'])
        ->postJson('/api/v1/reports', $payload)
        ->assertCreated();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.10'])
        ->withHeaders(['User-Agent' => 'MajlisIlmu-Test-Agent'])
        ->postJson('/api/v1/reports', $payload)
        ->assertStatus(409);

    $this->assertDatabaseCount('reports', 1);
});

it('escalates when two distinct anonymous reporters submit reports within 24 hours', function () {
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
    ]);

    $payload = [
        'entity_type' => 'event',
        'entity_id' => $event->id,
        'category' => 'wrong_info',
    ];

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11'])
        ->withHeaders(['User-Agent' => 'MajlisIlmu-Guest-1'])
        ->postJson('/api/v1/reports', $payload)
        ->assertCreated();

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.12'])
        ->withHeaders(['User-Agent' => 'MajlisIlmu-Guest-2'])
        ->postJson('/api/v1/reports', $payload)
        ->assertCreated();

    $event->refresh();

    expect((string) $event->status)->toBe('pending');

    $this->assertDatabaseHas('moderation_reviews', [
        'event_id' => $event->id,
        'decision' => 'remoderated',
    ]);
});
