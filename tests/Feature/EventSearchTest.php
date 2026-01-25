<?php

use App\Models\Event;
use App\Models\State;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('Event Search Filters', function () {
    beforeEach(function () {
        $this->seed(\Database\Seeders\PermissionSeeder::class);
        $this->seed(\Database\Seeders\RoleSeeder::class);
        // Set locale to English for tests
        app()->setLocale('en');
        // Get an actual state for filtering
        $this->state = State::where('country_code', 'MY')->first();
    });

    it('displays the events index page', function () {
        Event::factory()->count(5)->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        $response = $this->get('/events');

        $response->assertOk()
            ->assertSee('Circle of');
    });

    it('searches events by title', function () {
        Event::factory()->create([
            'title' => 'Kuliah Maghrib Special',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        Event::factory()->create([
            'title' => 'Ceramah Subuh',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);

        $response = $this->get('/events?search=Maghrib');

        $response->assertOk()
            ->assertSee('Kuliah Maghrib Special')
            ->assertDontSee('Ceramah Subuh');
    });

    it('filters events by language', function () {
        $malay = \Nnjeim\World\Models\Language::where('code', 'ms')->first() ?? \Nnjeim\World\Models\Language::query()->create(['code' => 'ms', 'name' => 'Malay', 'name_native' => 'Bahasa Melayu', 'dir' => 'ltr']);
        $english = \Nnjeim\World\Models\Language::where('code', 'en')->first() ?? \Nnjeim\World\Models\Language::query()->create(['code' => 'en', 'name' => 'English', 'name_native' => 'English', 'dir' => 'ltr']);

        $event1 = Event::factory()->create([
            'title' => 'Malay Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);
        $event1->languages()->attach($malay);

        $event2 = Event::factory()->create([
            'title' => 'English Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);
        $event2->languages()->attach($english);

        $response = $this->get('/events?language=en');

        $response->assertOk()
            ->assertSee('English Event')
            ->assertDontSee('Malay Event');
    });

    it('filters events by genre', function () {
        Event::factory()->create([
            'title' => 'Kuliah Event',
            'event_type' => 'kuliah',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        Event::factory()->create([
            'title' => 'Forum Event',
            'event_type' => 'forum',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);

        $response = $this->get('/events?event_type=forum');

        $response->assertOk()
            ->assertSee('Forum Event')
            ->assertDontSee('Kuliah Event');
    });

    it('only shows approved public events', function () {
        Event::factory()->create([
            'title' => 'Approved Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        Event::factory()->create([
            'title' => 'Pending Event',
            'status' => 'pending',
            'visibility' => 'public',
            'starts_at' => now()->addDays(2),
        ]);

        Event::factory()->create([
            'title' => 'Private Event',
            'status' => 'approved',
            'visibility' => 'private',
            'published_at' => now(),
            'starts_at' => now()->addDays(3),
        ]);

        $response = $this->get('/events');

        $response->assertOk()
            ->assertSee('Approved Event')
            ->assertDontSee('Pending Event')
            ->assertDontSee('Private Event');
    });

    it('paginates results', function () {
        foreach (range(1, 13) as $index) {
            Event::factory()->create([
                'title' => 'Event '.$index,
                'status' => 'approved',
                'visibility' => 'public',
                'published_at' => now(),
                'starts_at' => now()->addDays($index),
            ]);
        }

        $response = $this->get('/events');

        $response->assertOk()
            ->assertSee('Event 12')
            ->assertDontSee('Event 13');
    });

    it('displays event count', function () {
        Event::factory()->count(5)->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        $response = $this->get('/events');

        $response->assertOk()
            ->assertSee('5')
            ->assertSee('Upcoming Gatherings');
    });
});

describe('Event Detail Page', function () {
    beforeEach(function () {
        app()->setLocale('en');
    });

    it('displays event details', function () {
        $event = Event::factory()->create([
            'title' => 'My Amazing Event',
            'description' => 'This is the description',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
        ]);

        $response = $this->get("/events/{$event->slug}");

        $response->assertOk()
            ->assertSee('My Amazing Event')
            ->assertSee('This is the description');
    });

    it('shows 404 for unapproved events', function () {
        $event = Event::factory()->create([
            'status' => 'pending',
            'visibility' => 'public',
        ]);

        $response = $this->get("/events/{$event->slug}");

        $response->assertNotFound();
    });

    it('shows 404 for private events', function () {
        $event = Event::factory()->create([
            'status' => 'approved',
            'visibility' => 'private',
            'published_at' => now(),
        ]);

        $response = $this->get("/events/{$event->slug}");

        $response->assertNotFound();
    });

    it('includes JSON-LD structured data', function () {
        $event = Event::factory()->create([
            'title' => 'SEO Test Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
        ]);

        $response = $this->get("/events/{$event->slug}");

        $response->assertOk()
            ->assertSee('application/ld+json', false);
    });

    it('includes OpenGraph meta tags', function () {
        $event = Event::factory()->create([
            'title' => 'OG Test Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
        ]);

        $response = $this->get("/events/{$event->slug}");

        $response->assertOk()
            ->assertSee('og:title', false);
    });

    it('displays speakers', function () {
        $event = Event::factory()
            ->hasSpeakers(2)
            ->create([
                'status' => 'approved',
                'visibility' => 'public',
                'published_at' => now(),
            ]);

        $response = $this->get("/events/{$event->slug}");

        $response->assertOk()
            ->assertSee('Speakers');
    });
});

describe('Event Registration', function () {
    beforeEach(function () {
        app()->setLocale('en');
    });

    it('shows registration button for events requiring registration', function () {
        $event = Event::factory()->create([
            'title' => 'Registration Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'registration_required' => true,
        ]);

        $response = $this->get("/events/{$event->slug}");

        $response->assertOk()
            ->assertSee('Register');
    });

    it('shows no registration message for open events', function () {
        $event = Event::factory()->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'registration_required' => false,
        ]);

        $response = $this->get("/events/{$event->slug}");

        $response->assertOk()
            ->assertSee('No registration required');
    });

    it('allows guest registration', function () {
        $event = Event::factory()->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'registration_required' => true,
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDay(),
            'capacity' => 100,
            'registrations_count' => 0,
        ]);

        $response = $this->post("/events/{$event->slug}/register", [
            'name' => 'Ahmad',
            'email' => 'ahmad@example.com',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('registrations', [
            'event_id' => $event->id,
            'name' => 'Ahmad',
            'email' => 'ahmad@example.com',
        ]);
    });

    it('prevents duplicate registration', function () {
        $event = Event::factory()->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'registration_required' => true,
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDay(),
        ]);

        // First registration
        $this->post("/events/{$event->slug}/register", [
            'name' => 'Ahmad',
            'email' => 'ahmad@example.com',
        ]);

        // Duplicate
        $response = $this->post("/events/{$event->slug}/register", [
            'name' => 'Ahmad Again',
            'email' => 'ahmad@example.com',
        ]);

        $response->assertSessionHasErrors(['registration']);
    });

    it('enforces capacity limits', function () {
        $event = Event::factory()->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'registration_required' => true,
            'registration_opens_at' => now()->subDay(),
            'registration_closes_at' => now()->addDay(),
            'capacity' => 1,
            'registrations_count' => 1,
        ]);

        $response = $this->post("/events/{$event->slug}/register", [
            'name' => 'Late Registrant',
            'email' => 'late@example.com',
        ]);

        $response->assertSessionHasErrors(['registration']);
    });
});
