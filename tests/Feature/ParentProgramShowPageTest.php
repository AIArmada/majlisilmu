<?php

use App\Models\Event;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders a dedicated public parent program page with only public child events', function () {
    $institution = Institution::factory()->create(['name' => 'Masjid Al-Hidayah']);

    $parentEvent = Event::factory()->parentProgram()->for($institution)->create([
        'title' => 'Ramadan Umbrella Program',
        'status' => 'approved',
        'visibility' => 'public',
        'is_active' => true,
        'starts_at' => now()->addDays(1),
        'ends_at' => now()->addDays(10),
    ]);

    Event::factory()->childEvent($parentEvent)->for($institution)->create([
        'title' => 'Approved Child Event',
        'status' => 'approved',
        'visibility' => 'public',
        'is_active' => true,
        'starts_at' => now()->addDays(2),
        'ends_at' => now()->addDays(2)->addHours(2),
    ]);

    Event::factory()->childEvent($parentEvent)->for($institution)->create([
        'title' => 'Draft Child Event',
        'status' => 'draft',
        'visibility' => 'public',
        'is_active' => true,
        'starts_at' => now()->addDays(4),
        'ends_at' => now()->addDays(4)->addHours(2),
    ]);

    $this->get(route('events.show', $parentEvent))
        ->assertSuccessful()
        ->assertSee('Parent Program')
        ->assertSee('Program Schedule')
        ->assertSee('Approved Child Event')
        ->assertDontSee('Draft Child Event')
        ->assertDontSee('Check-in');
});
