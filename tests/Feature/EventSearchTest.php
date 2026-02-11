<?php

use App\Models\District;
use App\Models\Event;
use App\Models\Institution;
use App\Models\State;
use App\Models\Subdistrict;
use App\Models\Tag;
use App\Models\Venue;
use App\Services\EventSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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
            ->assertSee('Circle of')
            ->assertSee('Advanced Filters');
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

    it('filters events by district', function () {
        $state = State::where('country_code', 'MY')->first();

        if (! $state) {
            $countryId = \Illuminate\Support\Facades\DB::table('countries')->insertGetId([
                'iso2' => 'MY',
                'name' => 'Malaysia',
                'status' => 1,
                'phone_code' => '60',
                'iso3' => 'MYS',
                'region' => 'Asia',
                'subregion' => 'South-Eastern Asia',
            ]);

            $stateId = \Illuminate\Support\Facades\DB::table('states')->insertGetId([
                'country_id' => $countryId,
                'name' => 'Selangor',
                'country_code' => 'MY',
            ]);

            $state = State::query()->findOrFail($stateId);
        }

        $districtA = District::query()->create([
            'country_id' => $state->country_id,
            'state_id' => $state->id,
            'country_code' => 'MY',
            'name' => 'District A '.uniqid(),
        ]);

        $districtB = District::query()->create([
            'country_id' => $state->country_id,
            'state_id' => $state->id,
            'country_code' => 'MY',
            'name' => 'District B '.uniqid(),
        ]);

        $subdistrictA = Subdistrict::query()->create([
            'country_id' => $state->country_id,
            'state_id' => $state->id,
            'district_id' => $districtA->id,
            'country_code' => 'MY',
            'name' => 'Subdistrict A '.uniqid(),
        ]);

        $subdistrictB = Subdistrict::query()->create([
            'country_id' => $state->country_id,
            'state_id' => $state->id,
            'district_id' => $districtB->id,
            'country_code' => 'MY',
            'name' => 'Subdistrict B '.uniqid(),
        ]);

        $venueA = Venue::factory()->create();
        $venueA->address()->update([
            'state_id' => $state->id,
            'district_id' => $districtA->id,
            'subdistrict_id' => $subdistrictA->id,
        ]);

        $venueB = Venue::factory()->create();
        $venueB->address()->update([
            'state_id' => $state->id,
            'district_id' => $districtB->id,
            'subdistrict_id' => $subdistrictB->id,
        ]);

        Event::factory()->for($venueA)->create([
            'title' => 'District Filter Match',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);

        Event::factory()->for($venueB)->create([
            'title' => 'District Filter Non Match',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);

        $response = $this->get('/events?district_id='.$districtA->id);

        $response->assertOk()
            ->assertSee('District Filter Match')
            ->assertDontSee('District Filter Non Match');
    });

    it('filters events by subdistrict', function () {
        $state = State::where('country_code', 'MY')->first();

        if (! $state) {
            $countryId = \Illuminate\Support\Facades\DB::table('countries')->insertGetId([
                'iso2' => 'MY',
                'name' => 'Malaysia',
                'status' => 1,
                'phone_code' => '60',
                'iso3' => 'MYS',
                'region' => 'Asia',
                'subregion' => 'South-Eastern Asia',
            ]);

            $stateId = \Illuminate\Support\Facades\DB::table('states')->insertGetId([
                'country_id' => $countryId,
                'name' => 'Selangor',
                'country_code' => 'MY',
            ]);

            $state = State::query()->findOrFail($stateId);
        }

        $district = District::query()->create([
            'country_id' => $state->country_id,
            'state_id' => $state->id,
            'country_code' => 'MY',
            'name' => 'District C '.uniqid(),
        ]);

        $subdistrictA = Subdistrict::query()->create([
            'country_id' => $state->country_id,
            'state_id' => $state->id,
            'district_id' => $district->id,
            'country_code' => 'MY',
            'name' => 'Subdistrict C1 '.uniqid(),
        ]);

        $subdistrictB = Subdistrict::query()->create([
            'country_id' => $state->country_id,
            'state_id' => $state->id,
            'district_id' => $district->id,
            'country_code' => 'MY',
            'name' => 'Subdistrict C2 '.uniqid(),
        ]);

        $venueA = Venue::factory()->create();
        $venueA->address()->update([
            'state_id' => $state->id,
            'district_id' => $district->id,
            'subdistrict_id' => $subdistrictA->id,
        ]);

        $venueB = Venue::factory()->create();
        $venueB->address()->update([
            'state_id' => $state->id,
            'district_id' => $district->id,
            'subdistrict_id' => $subdistrictB->id,
        ]);

        Event::factory()->for($venueA)->create([
            'title' => 'Subdistrict Filter Match',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);

        Event::factory()->for($venueB)->create([
            'title' => 'Subdistrict Filter Non Match',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);

        $response = $this->get('/events?subdistrict_id='.$subdistrictA->id);

        $response->assertOk()
            ->assertSee('Subdistrict Filter Match')
            ->assertDontSee('Subdistrict Filter Non Match');
    });

    it('filters events by genre', function () {
        Event::factory()->create([
            'title' => 'Kuliah Event',
            'event_type' => [\App\Enums\EventType::KuliahCeramah],
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        Event::factory()->create([
            'title' => 'Forum Event',
            'event_type' => [\App\Enums\EventType::Forum],
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

    it('filters events by selected topics', function () {
        $tafsirTag = Tag::factory()->discipline()->create([
            'name' => ['en' => 'Tafsir', 'ms' => 'Tafsir'],
        ]);

        $fiqhTag = Tag::factory()->discipline()->create([
            'name' => ['en' => 'Fiqh', 'ms' => 'Fiqh'],
        ]);

        $tafsirEvent = Event::factory()->create([
            'title' => 'Tafsir Session',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);
        $tafsirEvent->attachTag($tafsirTag);

        $fiqhEvent = Event::factory()->create([
            'title' => 'Fiqh Session',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);
        $fiqhEvent->attachTag($fiqhTag);

        $query = http_build_query(['topic_ids' => [$tafsirTag->id]]);

        $response = $this->get("/events?{$query}");

        $response->assertOk()
            ->assertSee('Tafsir Session')
            ->assertDontSee('Fiqh Session')
            ->assertSee('Tafsir');
    });

    it('shows approved and pending public events', function () {
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
            'title' => 'Draft Event',
            'status' => 'draft',
            'visibility' => 'public',
            'starts_at' => now()->addDays(3),
        ]);

        Event::factory()->create([
            'title' => 'Private Event',
            'status' => 'approved',
            'visibility' => 'private',
            'published_at' => now(),
            'starts_at' => now()->addDays(4),
        ]);

        $response = $this->get('/events');

        $response->assertOk()
            ->assertSee('Approved Event')
            ->assertSee('Pending Event')
            ->assertSee('Menunggu Kelulusan')
            ->assertDontSee('Draft Event')
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

    it('eager loads event card relationships', function () {
        config(['scout.driver' => 'database']);

        $institution = \App\Models\Institution::factory()->create();
        $venue = \App\Models\Venue::factory()->create();

        Event::factory()
            ->for($institution)
            ->for($venue)
            ->hasSpeakers(1)
            ->create([
                'status' => 'approved',
                'visibility' => 'public',
                'published_at' => now(),
                'starts_at' => now()->addDays(1),
                'event_type' => [\App\Enums\EventType::KuliahCeramah],
            ]);

        $events = app(EventSearchService::class)->search(
            query: null,
            filters: [],
            perPage: 20,
            sort: 'time'
        );

        $event = collect($events->items())->first();

        expect($event)->not->toBeNull()
            ->and($event->relationLoaded('institution'))->toBeTrue()
            ->and($event->relationLoaded('venue'))->toBeTrue()
            ->and($event->relationLoaded('speakers'))->toBeTrue()
            ->and($event->relationLoaded('media'))->toBeTrue();

        if ($event->institution) {
            expect($event->institution->relationLoaded('media'))->toBeTrue();
        }

        if ($event->speakers->isNotEmpty()) {
            expect($event->speakers->first()->relationLoaded('media'))->toBeTrue();
        }
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

    it('filters events by a start date range', function () {
        Event::factory()->create([
            'title' => 'Within Date Range',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(7),
        ]);

        Event::factory()->create([
            'title' => 'Outside Date Range',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(20),
        ]);

        $query = http_build_query([
            'starts_after' => now()->addDays(5)->toDateString(),
            'starts_before' => now()->addDays(10)->toDateString(),
        ]);

        $response = $this->get("/events?{$query}");

        $response->assertOk()
            ->assertSee('Within Date Range')
            ->assertDontSee('Outside Date Range');
    });

    it('returns distance in database nearby search fallback', function () {
        config(['scout.driver' => 'database']);

        $nearInstitution = Institution::factory()->create();
        $nearVenue = Venue::factory()->create();
        $nearVenue->address()->update([
            'lat' => 3.1390,
            'lng' => 101.6869,
        ]);

        $farInstitution = Institution::factory()->create();
        $farVenue = Venue::factory()->create();
        $farVenue->address()->update([
            'lat' => 3.2600,
            'lng' => 101.8600,
        ]);

        Event::factory()->for($nearInstitution)->for($nearVenue)->create([
            'title' => 'Nearby Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(4),
        ]);

        Event::factory()->for($farInstitution)->for($farVenue)->create([
            'title' => 'Far Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(4),
        ]);

        $events = app(EventSearchService::class)->searchNearby(
            lat: 3.1390,
            lng: 101.6869,
            radiusKm: 12,
            filters: [],
            perPage: 20
        );

        $eventTitles = collect($events->items())->pluck('title')->all();

        expect($eventTitles)->toContain('Nearby Event');
        expect($eventTitles)->not->toContain('Far Event');

        $nearest = collect($events->items())->first();

        expect($nearest)->not->toBeNull()
            ->and($nearest->distance_km ?? null)->not->toBeNull();
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

    it('shows pending events on detail page with warning banner', function () {
        $event = Event::factory()->create([
            'title' => 'Pending Detail Event',
            'status' => 'pending',
            'visibility' => 'public',
        ]);

        $response = $this->get("/events/{$event->slug}");

        $response->assertOk()
            ->assertSee('Pending Detail Event')
            ->assertSee('Menunggu Kelulusan');
    });

    it('shows 404 for draft events', function () {
        $event = Event::factory()->create([
            'status' => 'draft',
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

    it('displays image gallery slider when gallery media exists', function () {
        Storage::fake('public');
        config()->set('media-library.disk_name', 'public');

        $event = Event::factory()->create([
            'title' => 'Gallery Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
        ]);

        $event->addMedia(UploadedFile::fake()->image('gallery-1.jpg', 1200, 800))
            ->toMediaCollection('gallery');
        $event->addMedia(UploadedFile::fake()->image('gallery-2.jpg', 1200, 800))
            ->toMediaCollection('gallery');

        $response = $this->get("/events/{$event->slug}");

        $response->assertOk()
            ->assertSee('Event Gallery');
    });

    it('displays related events section', function () {
        $institution = Institution::factory()->create();
        $sharedTag = Tag::factory()->discipline()->create();

        $event = Event::factory()->for($institution)->create([
            'title' => 'Main Related Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDay(),
        ]);
        $event->attachTag($sharedTag);

        Event::factory()->for($institution)->create([
            'title' => 'Institution Related Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);

        $tagRelatedEvent = Event::factory()->create([
            'title' => 'Tag Related Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(3),
        ]);
        $tagRelatedEvent->attachTag($sharedTag);

        Event::factory()->create([
            'title' => 'Private Hidden Event',
            'status' => 'approved',
            'visibility' => 'private',
            'published_at' => now(),
            'starts_at' => now()->addDays(4),
        ]);

        $response = $this->get("/events/{$event->slug}");

        $response->assertOk()
            ->assertSee('Related Events')
            ->assertSee('Institution Related Event')
            ->assertSee('Tag Related Event')
            ->assertDontSee('Private Hidden Event');
    });

    it('renders share preview modal content', function () {
        $event = Event::factory()->create([
            'title' => 'Shareable Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
        ]);

        $response = $this->get("/events/{$event->slug}");

        $response->assertOk()
            ->assertSee('Share Preview')
            ->assertSee('Copy Link');
    });
});

describe('Event Registration', function () {
    beforeEach(function () {
        app()->setLocale('en');
    });

    it('shows registration button for events requiring registration', function () {
        $event = Event::factory()
            ->has(\App\Models\EventSettings::factory()->state(['registration_required' => true]), 'settings')
            ->create([
                'title' => 'Registration Event',
                'status' => 'approved',
                'visibility' => 'public',
                'published_at' => now(),
            ]);

        $response = $this->get("/events/{$event->slug}");

        $response->assertOk()
            ->assertSee('Register');
    });

    it('shows no registration message for open events', function () {
        $event = Event::factory()
            ->has(\App\Models\EventSettings::factory()->state(['registration_required' => false]), 'settings')
            ->create([
                'status' => 'approved',
                'visibility' => 'public',
                'published_at' => now(),
            ]);

        $response = $this->get("/events/{$event->slug}");

        $response->assertOk()
            ->assertSee('No registration required');
    });

    it('allows guest registration', function () {
        $event = Event::factory()
            ->has(\App\Models\EventSettings::factory()->state([
                'registration_required' => true,
                'registration_opens_at' => now()->subDay(),
                'registration_closes_at' => now()->addDay(),
                'capacity' => 100,
            ]), 'settings')
            ->create([
                'status' => 'approved',
                'visibility' => 'public',
                'published_at' => now(),
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
        $event = Event::factory()
            ->has(\App\Models\EventSettings::factory()->state([
                'registration_required' => true,
                'registration_opens_at' => now()->subDay(),
                'registration_closes_at' => now()->addDay(),
            ]), 'settings')
            ->create([
                'status' => 'approved',
                'visibility' => 'public',
                'published_at' => now(),
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
        $event = Event::factory()
            ->has(\App\Models\EventSettings::factory()->state([
                'registration_required' => true,
                'registration_opens_at' => now()->subDay(),
                'registration_closes_at' => now()->addDay(),
                'capacity' => 1,
            ]), 'settings')
            ->create([
                'status' => 'approved',
                'visibility' => 'public',
                'published_at' => now(),
                'registrations_count' => 1,
            ]);

        \App\Models\Registration::factory()->create([
            'event_id' => $event->id,
            'status' => 'registered',
            'name' => 'Existing Registrant',
            'email' => 'existing-capacity@example.com',
        ]);

        $response = $this->post("/events/{$event->slug}/register", [
            'name' => 'Late Registrant',
            'email' => 'late@example.com',
        ]);

        $response->assertSessionHasErrors(['registration']);
    });
});
