<?php

use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\State;
use App\Models\Topic;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('displays the homepage successfully', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
    $response->assertSee('Cari');
    $response->assertSee('Majlis');
    $response->assertSee('Ilmu');
    $response->assertSee('Berdekatan Anda');
});

it('contains livewire components on the homepage', function () {
    $response = $this->get('/');

    $response->assertSuccessful();
    // The page should contain Livewire component markers
    $response->assertSee('wire:snapshot', false);
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

it('loads the browse by location component', function () {
    // Get an actual Malaysian state from World package data
    $state = State::where('country_code', 'MY')->first();

    if (! $state) {
        $this->markTestSkipped('No Malaysian states seeded. Run WorldSeeder first.');
    }

    Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(1),
        'state_id' => $state->id,
    ]);

    // Create a topic with events
    $topic = Topic::factory()->create();
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(1),
    ]);
    $event->topics()->attach($topic);

    Livewire::test('home.browse-by-location')
        ->assertSee('Cari Mengikut Negeri')
        ->assertSee('Cari Mengikut Topik');
});
