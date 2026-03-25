<?php

use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('displays the homepage successfully', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertSee('Cari');
    $response->assertSee('Majlis');
    $response->assertSee('Ilmu');
    $response->assertSee('Berdekatan Saya');
    $response->assertDontSee('/flux/flux.js', false);
    $response->assertDontSee('grainy-gradients.vercel.app', false);
});

it('contains livewire components on the homepage', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
    // The page should contain Livewire component markers
    $response->assertSee('wire:snapshot', false);
    $response->assertSee('livewire.js', false);
});

it('loads the stats component', function () {
    // Create some test data
    Event::factory()->count(5)->create(['status' => 'approved']);
    Speaker::factory()->count(3)->create();
    Institution::factory()->count(2)->create();

    Livewire::test('home.stats')
        ->assertSee('Majlis')
        ->assertSee('Penceramah')
        ->assertSee('Institusi');
});

it('loads the tonight events component when events exist', function () {
    // Create an event for tonight
    Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addHours(2),
    ]);

    Livewire::test('home.tonight-events')
        ->assertSee('Malam Ini');
});

it('loads the featured events component with upcoming events', function () {
    // Create an event for this week
    Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(3),
        'views_count' => 100,
    ]);

    Livewire::test('home.featured-events')
        ->assertSee('Majlis Pilihan');
});

it('loads the upcoming events component', function () {
    Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(1),
    ]);

    Livewire::test('home.upcoming-events')
        ->assertSee('Majlis Akan Datang');
});

it('renders the homepage discovery categories', function () {
    $response = $this->get('/');

    $response->assertSuccessful()
        ->assertSee('Jelajah Mengikut Kategori')
        ->assertSee('Tazkirah')
        ->assertSee('Tafsir');
});
