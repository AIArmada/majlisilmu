<?php

use App\Models\Institution;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('can list active institutions', function () {
    Institution::factory()->count(3)->create(['is_active' => true]);
    Institution::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/v1/institutions');

    $response->assertSuccessful()
        ->assertJsonStructure(['data', 'current_page', 'total'])
        ->assertJsonCount(3, 'data');
});

it('can filter institutions by name search', function () {
    Institution::factory()->create(['name' => 'Masjid Al-Falah', 'is_active' => true]);
    Institution::factory()->create(['name' => 'Surau Baitul Aman', 'is_active' => true]);

    $response = $this->getJson('/api/v1/institutions?filter[search]=masjid');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('can show a single institution by id', function () {
    $institution = Institution::factory()->create(['is_active' => true]);

    $response = $this->getJson("/api/v1/institutions/{$institution->id}");

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['id', 'name', 'logo_url']]);
});

it('returns 404 for inactive institution', function () {
    $institution = Institution::factory()->create(['is_active' => false]);

    $response = $this->getJson("/api/v1/institutions/{$institution->id}");

    $response->assertNotFound();
});
