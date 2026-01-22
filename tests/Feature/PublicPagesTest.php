<?php

use App\Models\Event;
use App\Models\Institution;
use App\Models\Series;
use App\Models\Speaker;

it('loads public index pages', function () {
    $this->get('/')->assertSuccessful()->assertSee('Cari Majlis Ilmu');
    $this->get('/events')->assertSuccessful()->assertSee('Browse Events');
    $this->get('/institutions')->assertSuccessful()->assertSee('Centers of knowledge and community.');
    $this->get('/speakers')->assertSuccessful()->assertSee('Scholars and teachers sharing their knowledge.');
    $this->get('/submit-event')->assertSuccessful()->assertSee('Submit an Event');
    $this->get('/submit-event/success')->assertSuccessful()->assertSee('Event Submitted!');
});

it('loads public detail pages', function () {
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'published_at' => now(),
        'starts_at' => now()->addDay(),
    ]);

    $institution = Institution::factory()->create();
    $speaker = Speaker::factory()->create();
    $series = Series::factory()->create([
        'visibility' => 'public',
    ]);

    $this->get(route('events.show', $event))->assertSuccessful()->assertSee($event->title);
    $this->get(route('institutions.show', $institution))->assertSuccessful()->assertSee($institution->name);
    $this->get(route('speakers.show', $speaker))->assertSuccessful()->assertSee($speaker->name);
    $this->get(route('series.show', $series))->assertSuccessful()->assertSee($series->title);
});
