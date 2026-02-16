<?php

use App\Models\Speaker;

use function Pest\Laravel\get;

it('can search speakers case-insensitively', function () {
    // Create speakers with different cases
    Speaker::factory()->create([
        'name' => 'Samad Al-Bakri',
        'status' => 'verified',
        'is_active' => true,
    ]);

    Speaker::factory()->create([
        'name' => 'Ahmad Bin Ali',
        'status' => 'verified',
        'is_active' => true,
    ]);

    // Test with exact name
    get('/penceramah?search=Samad')
        ->assertSuccessful()
        ->assertSee('Samad')
        ->assertDontSee('Ahmad');

    // Test with lowercase name (should find Samad because of ILIKE fix)
    get('/penceramah?search=samad')
        ->assertSuccessful()
        ->assertSee('Samad')
        ->assertDontSee('Ahmad');
});

it('filters by active status on public speaker index', function () {
    Speaker::factory()->create([
        'name' => 'Active Speaker',
        'status' => 'verified',
        'is_active' => true,
    ]);

    Speaker::factory()->create([
        'name' => 'Inactive Speaker',
        'status' => 'verified',
        'is_active' => false,
    ]);

    get('/penceramah')
        ->assertSuccessful()
        ->assertSee('Active Speaker')
        ->assertDontSee('Inactive Speaker');
});
