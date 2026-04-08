<?php

use App\Enums\ReferenceType;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

it('uses a 16:9 placeholder aspect ratio on featured home cards without posters', function () {
    Event::factory()->create([
        'title' => 'Majlis Pilihan Tanpa Poster',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(3),
        'views_count' => 100,
        'is_active' => true,
    ]);

    Livewire::test('home.featured-events')
        ->assertSee('Majlis Pilihan Tanpa Poster')
        ->assertSee('data-poster-aspect="16:9"', false);
});

it('renders the featured homepage card date badge below the poster image', function () {
    Event::factory()->create([
        'title' => 'Majlis Pilihan Dengan Tarikh Bawah',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(3),
        'views_count' => 120,
        'is_active' => true,
    ]);

    Livewire::test('home.featured-events')
        ->assertSee('Majlis Pilihan Dengan Tarikh Bawah')
        ->assertSee('data-testid="homepage-featured-card-meta-row"', false)
        ->assertSee('data-testid="homepage-featured-card-date-badge"', false)
        ->assertSeeInOrder([
            'data-poster-aspect=',
            'data-testid="homepage-featured-card-meta-row"',
            'data-testid="homepage-featured-card-title-link"',
        ], false);
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

it('renders the attached book title across homepage event components without parentheses', function () {
    Carbon::setTestNow(Carbon::create(2026, 4, 7, 18, 0, 0));

    $bookReference = Reference::factory()->create([
        'title' => 'Riyadhus Solihin',
        'type' => ReferenceType::Book->value,
    ]);

    $featuredEvent = Event::factory()->create([
        'title' => 'Kuliah Kitab Pilihan',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDay(),
        'views_count' => 150,
        'is_active' => true,
    ]);

    $tonightEvent = Event::factory()->create([
        'title' => 'Kuliah Kitab Malam Ini',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addHours(2),
        'is_active' => true,
    ]);

    $featuredEvent->references()->attach($bookReference->id);
    $tonightEvent->references()->attach($bookReference->id);

    Livewire::test('home.featured-events')
        ->assertSee('Kuliah Kitab Pilihan')
        ->assertSee('Riyadhus Solihin')
        ->assertDontSee('(Riyadhus Solihin)');

    Livewire::test('home.upcoming-events')
        ->assertSee('Kuliah Kitab Pilihan')
        ->assertSee('Riyadhus Solihin')
        ->assertDontSee('(Riyadhus Solihin)');

    Livewire::test('home.tonight-events')
        ->assertSee('Kuliah Kitab Malam Ini')
        ->assertSee('Riyadhus Solihin')
        ->assertDontSee('(Riyadhus Solihin)');

    Livewire::test('home.upcoming-prayer-events')
        ->assertSee('Kuliah Kitab Malam Ini')
        ->assertSee('Riyadhus Solihin')
        ->assertDontSee('(Riyadhus Solihin)');

    Carbon::setTestNow();
});

it('renders the homepage discovery categories', function () {
    $response = $this->get('/');

    $response->assertSuccessful()
        ->assertSee('Jelajah Mengikut Kategori')
        ->assertSee('Tazkirah')
        ->assertSee('Tafsir');
});
