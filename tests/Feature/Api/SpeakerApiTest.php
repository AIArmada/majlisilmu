<?php

use App\Models\Speaker;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('can list active speakers', function () {
    Speaker::factory()->count(3)->create(['is_active' => true]);
    Speaker::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/v1/speakers');

    $response->assertSuccessful()
        ->assertJsonStructure(['data', 'current_page', 'total'])
        ->assertJsonCount(3, 'data');
});

it('can filter speakers by name search', function () {
    Speaker::factory()->create(['name' => 'Ustaz Ahmad', 'is_active' => true]);
    Speaker::factory()->create(['name' => 'Dr. Faridah', 'is_active' => true]);

    $response = $this->getJson('/api/v1/speakers?filter[search]=ustaz');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('can show a single speaker', function () {
    $speaker = Speaker::factory()->create(['is_active' => true]);

    $response = $this->getJson("/api/v1/speakers/{$speaker->id}");

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['id', 'name', 'avatar_url', 'formatted_name']]);
});

it('returns 404 for inactive speaker', function () {
    $speaker = Speaker::factory()->create(['is_active' => false]);

    $response = $this->getJson("/api/v1/speakers/{$speaker->id}");

    $response->assertNotFound();
});
