<?php

use App\Models\Speaker;
use App\Models\User;
use Livewire\Livewire;

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

it('renders translated search placeholder on speaker index', function () {
    app()->setLocale('ms');

    get('/penceramah')
        ->assertSuccessful()
        ->assertSee(__('Search speakers...'));
});

it('shows add-missing-speaker call to action on speaker index', function () {
    get('/penceramah')
        ->assertSuccessful()
        ->assertSee('Tak jumpa penceramah? Tambah penceramah');
});

it('supports fuzzy search with minor typos', function () {
    Speaker::factory()->create([
        'name' => 'Samad Al-Bakri',
        'status' => 'verified',
        'is_active' => true,
    ]);

    Speaker::factory()->create([
        'name' => 'Sulaiman Hasan',
        'status' => 'verified',
        'is_active' => true,
    ]);

    get('/penceramah?search=Smad')
        ->assertSuccessful()
        ->assertSee('Samad')
        ->assertDontSee('Sulaiman');
});

it('updates search results live when query changes', function () {
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

    Livewire::test('pages.speakers.index')
        ->set('search', 'Smad')
        ->assertSee('Samad')
        ->assertDontSee('Ahmad');
});

it('allows users to submit a missing speaker from speaker index with pending status', function () {
    $speakerName = 'Ustaz Cadangan Baru';
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages.speakers.index')
        ->call('openSpeakerSubmissionForm')
        ->set('speakerSubmissionData.name', $speakerName)
        ->set('speakerSubmissionData.gender', 'male')
        ->call('submitSpeaker')
        ->assertHasNoErrors();

    $speaker = Speaker::query()
        ->where('name', $speakerName)
        ->first();

    expect($speaker)->not->toBeNull()
        ->and($speaker?->status)->toBe('pending')
        ->and($speaker?->is_active)->toBeTrue();
});

it('redirects guests to login when opening add speaker form', function () {
    Livewire::test('pages.speakers.index')
        ->call('openSpeakerSubmissionForm')
        ->assertRedirect(route('login'));
});
