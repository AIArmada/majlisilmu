<?php

use App\Enums\InspirationCategory;
use App\Models\Inspiration;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('can list active inspirations', function () {
    Inspiration::factory()->count(4)->create(['is_active' => true]);
    Inspiration::factory()->create(['is_active' => false]);

    $response = $this->getJson('/api/v1/inspirations');

    $response->assertSuccessful()
        ->assertJsonStructure(['data', 'current_page', 'total'])
        ->assertJsonCount(4, 'data');
});

it('can filter inspirations by category', function () {
    Inspiration::factory()->category(InspirationCategory::QuranQuote)->create(['is_active' => true]);
    Inspiration::factory()->category(InspirationCategory::HadithQuote)->create(['is_active' => true]);

    $response = $this->getJson('/api/v1/inspirations?filter[category]=quran_quote');

    $response->assertSuccessful()
        ->assertJsonCount(1, 'data');
});

it('can show a daily random inspiration', function () {
    Inspiration::factory()->count(3)->create(['is_active' => true]);

    $response = $this->getJson('/api/v1/inspirations/daily');

    $response->assertSuccessful()
        ->assertJsonStructure(['data']);
});

it('can show a single inspiration', function () {
    $inspiration = Inspiration::factory()->create(['is_active' => true]);

    $response = $this->getJson("/api/v1/inspirations/{$inspiration->id}");

    $response->assertSuccessful()
        ->assertJsonStructure(['data' => ['id', 'title', 'content_html']]);
});

it('returns 404 for inactive inspiration', function () {
    $inspiration = Inspiration::factory()->create(['is_active' => false]);

    $response = $this->getJson("/api/v1/inspirations/{$inspiration->id}");

    $response->assertNotFound();
});
