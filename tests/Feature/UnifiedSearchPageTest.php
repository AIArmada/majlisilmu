<?php

use App\Enums\EventFormat;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('submits the homepage hero search to the unified search page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('action="'.route('search.index').'"', false);
});

it('shows grouped event speaker and institution matches on the unified search page', function () {
    $institution = Institution::factory()->create([
        'name' => 'Masjid Nur Hikmah',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $speaker = Speaker::factory()->create([
        'name' => 'Ustaz Nur Hikmah',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $event = Event::factory()
        ->for($institution)
        ->create([
            'title' => 'Kuliah Nur Hikmah',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDay(),
            'event_format' => EventFormat::Physical,
            'is_active' => true,
        ]);

    $this->get(route('search.index', ['search' => 'Nur Hikmah']))
        ->assertOk()
        ->assertSee('Kuliah Nur Hikmah')
        ->assertSee('Ustaz Nur Hikmah')
        ->assertSee('Masjid Nur Hikmah')
        ->assertSee(route('events.show', $event), false)
        ->assertSee(route('speakers.show', $speaker), false)
        ->assertSee(route('institutions.show', $institution), false);
});

it('shows nearby event matches on the unified search page when location is present', function () {
    $institution = Institution::factory()->create([
        'name' => 'Masjid Taman Setia',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $institution->addressModel?->update([
        'lat' => 3.1390,
        'lng' => 101.6869,
    ]);

    Event::factory()
        ->for($institution)
        ->create([
            'title' => 'Kuliah Berdekatan',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDay(),
            'event_format' => EventFormat::Physical,
            'is_active' => true,
        ]);

    $this->get(route('search.index', [
        'lat' => '3.1390',
        'lng' => '101.6869',
    ]))
        ->assertOk()
        ->assertSee('Kuliah Berdekatan')
        ->assertSee(__('Nearby events'));
});
