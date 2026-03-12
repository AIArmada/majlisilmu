<?php

use App\Enums\EventFormat;
use App\Enums\EventPrayerTime;
use App\Enums\EventType;
use App\Enums\PrayerReference;
use App\Enums\TimingMode;
use App\Livewire\Pages\Events\Index;
use App\Models\District;
use App\Models\Event;
use App\Models\EventSettings;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Registration;
use App\Models\Speaker;
use App\Models\State;
use App\Models\Subdistrict;
use App\Models\Tag;
use App\Models\Venue;
use App\Services\EventSearchService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Nnjeim\World\Models\Language;

uses(RefreshDatabase::class);

describe('Event Search Filters', function () {
    beforeEach(function () {
        $this->seed(PermissionSeeder::class);
        $this->seed(RoleSeeder::class);
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

    it('shows title-only search placeholder on events index', function () {
        $this->get('/events')
            ->assertOk()
            ->assertSee('Cari mengikut tajuk...');
    });

    it('loads the advanced filter panel only after it is opened', function () {
        Livewire::test(Index::class)
            ->assertDontSee('People & Content')
            ->call('toggleAdvancedFiltersPanel')
            ->assertSee('People & Content');
    });

    it('does not preload unrelated filter option labels into the initial events index response', function () {
        Speaker::factory()->create([
            'name' => 'Speaker Hidden Filter Payload Test',
            'status' => 'verified',
            'is_active' => true,
        ]);

        Institution::factory()->create([
            'name' => 'Institution Hidden Filter Payload Test',
            'status' => 'verified',
            'is_active' => true,
        ]);

        Venue::factory()->create([
            'name' => 'Venue Hidden Filter Payload Test',
            'status' => 'verified',
            'is_active' => true,
        ]);

        Reference::factory()->create([
            'title' => 'Reference Hidden Filter Payload Test',
            'is_active' => true,
        ]);

        Tag::factory()->discipline()->create([
            'name' => ['en' => 'Discipline Hidden Filter Payload Test', 'ms' => 'Discipline Hidden Filter Payload Test'],
            'status' => 'verified',
        ]);

        Tag::factory()->domain()->create([
            'name' => ['en' => 'Domain Hidden Filter Payload Test', 'ms' => 'Domain Hidden Filter Payload Test'],
            'status' => 'verified',
        ]);

        $this->get('/events')
            ->assertOk()
            ->assertDontSee('Speaker Hidden Filter Payload Test')
            ->assertDontSee('Institution Hidden Filter Payload Test')
            ->assertDontSee('Venue Hidden Filter Payload Test')
            ->assertDontSee('Reference Hidden Filter Payload Test')
            ->assertDontSee('Discipline Hidden Filter Payload Test')
            ->assertDontSee('Domain Hidden Filter Payload Test');
    });

    it('shows save this search link for guests when search query is active', function () {
        $response = $this->get('/events?search=halaqah');

        $response->assertOk()
            ->assertSee('Save This Search')
            ->assertSee('/carian-tersimpan?search=halaqah', false);
    });

    it('shows radius control only when a nearby location is available', function () {
        $this->get('/events')
            ->assertOk()
            ->assertDontSee('Radius (km)');

        $this->get('/events?lat=3.1390&lng=101.6869')
            ->assertOk()
            ->assertSee('Radius (km)');
    });

    it('sets default nearby radius to 15 km when location is detected', function () {
        Livewire::test(Index::class)
            ->call('setLocation', 3.0969303799671, 101.48910903397)
            ->assertSet('lat', '3.0969303799671')
            ->assertSet('lng', '101.48910903397')
            ->assertSet('radius_km', 15)
            ->assertSet('filterData.radius_km', 15)
            ->assertSet('sort', 'distance');
    });

    it('shows event location with subdistrict, district, and state on cards', function () {
        $state = State::where('country_code', 'MY')->first();

        if (! $state) {
            $countryId = DB::table('countries')->insertGetId([
                'iso2' => 'MY',
                'name' => 'Malaysia',
                'status' => 1,
                'phone_code' => '60',
                'iso3' => 'MYS',
                'region' => 'Asia',
                'subregion' => 'South-Eastern Asia',
            ]);

            $stateId = DB::table('states')->insertGetId([
                'country_id' => $countryId,
                'name' => 'Selangor',
                'country_code' => 'MY',
            ]);

            $state = State::query()->findOrFail($stateId);
        }
        $district = District::query()->create([
            'country_id' => (int) $state->country_id,
            'state_id' => (int) $state->id,
            'country_code' => 'MY',
            'name' => 'Gombak',
        ]);
        $subdistrict = Subdistrict::query()->create([
            'country_id' => (int) $state->country_id,
            'state_id' => (int) $state->id,
            'district_id' => (int) $district->id,
            'country_code' => 'MY',
            'name' => 'Taman Melawati',
        ]);

        $venue = Venue::factory()->create([
            'name' => 'Surau Taman Melawati',
            'status' => 'verified',
            'is_active' => true,
        ]);

        $venue->addressModel?->update([
            'state_id' => (int) $state->id,
            'district_id' => (int) $district->id,
            'subdistrict_id' => (int) $subdistrict->id,
        ]);

        Event::factory()
            ->for($venue)
            ->create([
                'title' => 'Lokasi Hierarki Event',
                'status' => 'approved',
                'visibility' => 'public',
                'published_at' => now(),
                'starts_at' => now()->addDays(1),
            ]);

        $response = $this->get('/events');

        $response->assertOk()
            ->assertSee('Surau Taman Melawati')
            ->assertSee('Taman Melawati, Gombak & '.$state->name);
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

    it('does not show parent programs on the public events index while still showing child events', function () {
        $institution = Institution::factory()->create([
            'name' => 'Masjid Hierarki',
            'status' => 'verified',
            'is_active' => true,
        ]);

        $parentEvent = Event::factory()->parentProgram()->for($institution)->create([
            'title' => 'Umbrella Program Hidden From Index',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
            'ends_at' => now()->addDays(5),
        ]);

        Event::factory()->childEvent($parentEvent)->for($institution)->create([
            'title' => 'Child Event Visible On Index',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);

        $this->get('/events')
            ->assertOk()
            ->assertSee('Child Event Visible On Index')
            ->assertDontSee('Umbrella Program Hidden From Index');
    });

    it('does not search events by institution name when title does not match', function () {
        $matchInstitution = Institution::factory()->create([
            'name' => 'Pusat Tarbiah Al Hikmah',
            'status' => 'verified',
            'is_active' => true,
        ]);

        $otherInstitution = Institution::factory()->create([
            'name' => 'Kompleks Ilmu An Nur',
            'status' => 'verified',
            'is_active' => true,
        ]);

        Event::factory()->for($matchInstitution)->create([
            'title' => 'Kuliah Subuh Institusi A',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        Event::factory()->for($otherInstitution)->create([
            'title' => 'Kuliah Subuh Institusi B',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        $response = $this->get('/events?search=Al%20Hikmah');

        $response->assertOk()
            ->assertDontSee('Kuliah Subuh Institusi A')
            ->assertDontSee('Kuliah Subuh Institusi B');
    });

    it('does not search events by venue name when title does not match', function () {
        $matchVenue = Venue::factory()->create([
            'name' => 'Surau Taman Melawati',
            'status' => 'verified',
            'is_active' => true,
        ]);

        $otherVenue = Venue::factory()->create([
            'name' => 'Masjid Al Falah',
            'status' => 'verified',
            'is_active' => true,
        ]);

        Event::factory()->for($matchVenue)->create([
            'title' => 'Kuliah Malam Lokasi A',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        Event::factory()->for($otherVenue)->create([
            'title' => 'Kuliah Malam Lokasi B',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        $response = $this->get('/events?search=Melawati');

        $response->assertOk()
            ->assertDontSee('Kuliah Malam Lokasi A')
            ->assertDontSee('Kuliah Malam Lokasi B');
    });

    it('does not search events by speaker name when title does not match', function () {
        $matchSpeaker = Speaker::factory()->create([
            'name' => 'Ustaz Samad Al-Bakri',
            'status' => 'verified',
            'is_active' => true,
        ]);

        $otherSpeaker = Speaker::factory()->create([
            'name' => 'Ustaz Ahmad Zain',
            'status' => 'verified',
            'is_active' => true,
        ]);

        $matchEvent = Event::factory()->create([
            'title' => 'Kuliah Speaker A',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);
        $matchEvent->speakers()->attach($matchSpeaker->id);

        $otherEvent = Event::factory()->create([
            'title' => 'Kuliah Speaker B',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);
        $otherEvent->speakers()->attach($otherSpeaker->id);

        $response = $this->get('/events?search=Samad');

        $response->assertOk()
            ->assertDontSee('Kuliah Speaker A')
            ->assertDontSee('Kuliah Speaker B');
    });

    it('supports fuzzy search with minor title typos', function () {
        $matchVenue = Venue::factory()->create([
            'name' => 'Surau Taman Melawati',
            'status' => 'verified',
            'is_active' => true,
        ]);

        $otherVenue = Venue::factory()->create([
            'name' => 'Masjid Al Irsyad',
            'status' => 'verified',
            'is_active' => true,
        ]);

        Event::factory()->for($matchVenue)->create([
            'title' => 'Kuliah Maghrib Melawati',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        Event::factory()->for($otherVenue)->create([
            'title' => 'Kuliah Maghrib Irsyad',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        $response = $this->get('/events?search=Melawti');

        $response->assertOk()
            ->assertSee('Kuliah Maghrib Melawati')
            ->assertDontSee('Kuliah Maghrib Irsyad');
    });

    it('updates event results live when search changes', function () {
        $matchVenue = Venue::factory()->create([
            'name' => 'Surau Taman Melawati',
            'status' => 'verified',
            'is_active' => true,
        ]);

        $otherVenue = Venue::factory()->create([
            'name' => 'Masjid Al Irsyad',
            'status' => 'verified',
            'is_active' => true,
        ]);

        Event::factory()->for($matchVenue)->create([
            'title' => 'Live Search Melawati',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        Event::factory()->for($otherVenue)->create([
            'title' => 'Live Search Irsyad',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        $component = Livewire::test(Index::class)
            ->set('search', 'Melawti')
            ->assertSet('search', 'Melawti');

        $eventTitles = $component->instance()
            ->events
            ->getCollection()
            ->pluck('title')
            ->all();

        expect($eventTitles)
            ->toContain('Live Search Melawati')
            ->not->toContain('Live Search Irsyad');
    });

    it('filters events by prayer_time enum value in advanced filters', function () {
        Event::factory()->create([
            'title' => 'Enum Filter Match',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
            'timing_mode' => TimingMode::PrayerRelative,
            'prayer_reference' => PrayerReference::Maghrib,
            'prayer_display_text' => 'Selepas Maghrib',
        ]);

        Event::factory()->create([
            'title' => 'Enum Filter No Match',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
            'timing_mode' => TimingMode::PrayerRelative,
            'prayer_reference' => PrayerReference::Asr,
            'prayer_display_text' => 'Selepas Asar',
        ]);

        $response = $this->get('/events?prayer_time='.EventPrayerTime::SelepasMaghrib->value);

        $response->assertOk()
            ->assertSee('Enum Filter Match')
            ->assertDontSee('Enum Filter No Match');
    });

    it('filters events by institution in advanced filters', function () {
        $includedInstitution = Institution::factory()->create(['status' => 'verified', 'is_active' => true]);
        $excludedInstitution = Institution::factory()->create(['status' => 'verified', 'is_active' => true]);

        Event::factory()->for($includedInstitution)->create([
            'title' => 'Institution Match Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);

        Event::factory()->for($excludedInstitution)->create([
            'title' => 'Institution Excluded Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);

        $response = $this->get('/events?institution_id='.$includedInstitution->id);

        $response->assertOk()
            ->assertSee('Institution Match Event')
            ->assertDontSee('Institution Excluded Event');
    });

    it('filters events by venue in advanced filters', function () {
        $includedVenue = Venue::factory()->create(['status' => 'verified', 'is_active' => true]);
        $excludedVenue = Venue::factory()->create(['status' => 'verified', 'is_active' => true]);

        Event::factory()->for($includedVenue)->create([
            'title' => 'Venue Match Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);

        Event::factory()->for($excludedVenue)->create([
            'title' => 'Venue Excluded Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);

        $response = $this->get('/events?venue_id='.$includedVenue->id);

        $response->assertOk()
            ->assertSee('Venue Match Event')
            ->assertDontSee('Venue Excluded Event');
    });

    it('filters events by selected speaker ids in advanced filters', function () {
        $includedSpeaker = Speaker::factory()->create(['status' => 'verified', 'is_active' => true]);
        $excludedSpeaker = Speaker::factory()->create(['status' => 'verified', 'is_active' => true]);

        $includedEvent = Event::factory()->create([
            'title' => 'Speaker Match Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);
        $includedEvent->speakers()->attach($includedSpeaker->id);

        $excludedEvent = Event::factory()->create([
            'title' => 'Speaker Excluded Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);
        $excludedEvent->speakers()->attach($excludedSpeaker->id);

        $query = http_build_query([
            'speaker_ids' => [$includedSpeaker->id],
        ]);

        $response = $this->get('/events?'.$query);

        $response->assertOk()
            ->assertSee('Speaker Match Event')
            ->assertDontSee('Speaker Excluded Event');
    });

    it('filters events by language', function () {
        $malay = Language::where('code', 'ms')->first() ?? Language::query()->create(['code' => 'ms', 'name' => 'Malay', 'name_native' => 'Bahasa Melayu', 'dir' => 'ltr']);
        $english = Language::where('code', 'en')->first() ?? Language::query()->create(['code' => 'en', 'name' => 'English', 'name_native' => 'English', 'dir' => 'ltr']);

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

    it('filters events by language_codes array filter', function () {
        $malay = Language::where('code', 'ms')->first() ?? Language::query()->create(['code' => 'ms', 'name' => 'Malay', 'name_native' => 'Bahasa Melayu', 'dir' => 'ltr']);
        $english = Language::where('code', 'en')->first() ?? Language::query()->create(['code' => 'en', 'name' => 'English', 'name_native' => 'English', 'dir' => 'ltr']);

        $englishEvent = Event::factory()->create([
            'title' => 'English Language Codes Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);
        $englishEvent->languages()->sync([$english->id]);

        $malayEvent = Event::factory()->create([
            'title' => 'Malay Language Codes Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);
        $malayEvent->languages()->sync([$malay->id]);

        $query = http_build_query([
            'language_codes' => ['en'],
        ]);

        $response = $this->get('/events?'.$query);

        $response->assertOk()
            ->assertSee('English Language Codes Event')
            ->assertDontSee('Malay Language Codes Event');
    });

    it('filters events by event_format array filter', function () {
        Event::factory()->create([
            'title' => 'Online Format Event',
            'status' => 'approved',
            'visibility' => 'public',
            'event_format' => EventFormat::Online,
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        Event::factory()->create([
            'title' => 'Physical Format Event',
            'status' => 'approved',
            'visibility' => 'public',
            'event_format' => EventFormat::Physical,
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        $query = http_build_query([
            'event_format' => [EventFormat::Online->value],
        ]);

        $response = $this->get('/events?'.$query);

        $response->assertOk()
            ->assertSee('Online Format Event')
            ->assertDontSee('Physical Format Event');
    });

    it('filters events by is_muslim_only toggle', function () {
        Event::factory()->create([
            'title' => 'Muslim Only Event',
            'status' => 'approved',
            'visibility' => 'public',
            'is_muslim_only' => true,
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        Event::factory()->create([
            'title' => 'Open Event',
            'status' => 'approved',
            'visibility' => 'public',
            'is_muslim_only' => false,
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        $response = $this->get('/events?is_muslim_only=1');

        $response->assertOk()
            ->assertSee('Muslim Only Event')
            ->assertDontSee('Open Event');
    });

    it('filters events by link presence and timing mode', function () {
        Event::factory()->create([
            'title' => 'Absolute With Links Event',
            'status' => 'approved',
            'visibility' => 'public',
            'timing_mode' => TimingMode::Absolute,
            'event_url' => 'https://example.com/event',
            'live_url' => 'https://youtube.com/live/test',
            'ends_at' => now()->addDays(2),
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        Event::factory()->create([
            'title' => 'Prayer Relative Without Links Event',
            'status' => 'approved',
            'visibility' => 'public',
            'timing_mode' => TimingMode::PrayerRelative,
            'prayer_display_text' => 'Selepas Maghrib',
            'event_url' => null,
            'live_url' => null,
            'ends_at' => null,
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        $query = http_build_query([
            'timing_mode' => TimingMode::Absolute->value,
            'has_event_url' => 1,
            'has_live_url' => 1,
            'has_end_time' => 1,
        ]);

        $response = $this->get('/events?'.$query);

        $response->assertOk()
            ->assertSee('Absolute With Links Event')
            ->assertDontSee('Prayer Relative Without Links Event');
    });

    it('filters absolute timing events by selected start time range', function () {
        Event::factory()->create([
            'title' => 'Evening Absolute Event',
            'status' => 'approved',
            'visibility' => 'public',
            'timing_mode' => TimingMode::Absolute,
            'published_at' => now(),
            'starts_at' => now('UTC')->addDays(2)->setTime(20, 0),
            'ends_at' => null,
        ]);

        Event::factory()->create([
            'title' => 'Morning Absolute Event',
            'status' => 'approved',
            'visibility' => 'public',
            'timing_mode' => TimingMode::Absolute,
            'published_at' => now(),
            'starts_at' => now('UTC')->addDays(2)->setTime(9, 0),
            'ends_at' => null,
        ]);

        Event::factory()->create([
            'title' => 'Evening Prayer Event',
            'status' => 'approved',
            'visibility' => 'public',
            'timing_mode' => TimingMode::PrayerRelative,
            'prayer_display_text' => 'Selepas Maghrib',
            'published_at' => now(),
            'starts_at' => now('UTC')->addDays(2)->setTime(20, 0),
            'ends_at' => null,
        ]);

        $query = http_build_query([
            'timing_mode' => TimingMode::Absolute->value,
            'starts_time_from' => '19:00',
            'starts_time_until' => '21:00',
        ]);

        $response = $this
            ->withCookie('user_timezone', 'UTC')
            ->get('/events?'.$query);

        $response->assertOk()
            ->assertSee('Evening Absolute Event')
            ->assertDontSee('Morning Absolute Event')
            ->assertDontSee('Evening Prayer Event');
    });

    it('applies absolute time range to event start time only, not event end time', function () {
        Event::factory()->create([
            'title' => 'Start In Range Event',
            'status' => 'approved',
            'visibility' => 'public',
            'timing_mode' => TimingMode::Absolute,
            'published_at' => now(),
            'starts_at' => now('UTC')->addDays(2)->setTime(20, 0),
            'ends_at' => now('UTC')->addDays(2)->setTime(22, 30),
        ]);

        Event::factory()->create([
            'title' => 'Start Out Of Range But Ends In Range Event',
            'status' => 'approved',
            'visibility' => 'public',
            'timing_mode' => TimingMode::Absolute,
            'published_at' => now(),
            'starts_at' => now('UTC')->addDays(2)->setTime(18, 0),
            'ends_at' => now('UTC')->addDays(2)->setTime(20, 30),
        ]);

        $query = http_build_query([
            'timing_mode' => TimingMode::Absolute->value,
            'starts_time_from' => '19:00',
            'starts_time_until' => '21:00',
        ]);

        $response = $this
            ->withCookie('user_timezone', 'UTC')
            ->get('/events?'.$query);

        $response->assertOk()
            ->assertSee('Start In Range Event')
            ->assertDontSee('Start Out Of Range But Ends In Range Event');
    });

    it('filters events by district', function () {
        $state = State::where('country_code', 'MY')->first();

        if (! $state) {
            $countryId = DB::table('countries')->insertGetId([
                'iso2' => 'MY',
                'name' => 'Malaysia',
                'status' => 1,
                'phone_code' => '60',
                'iso3' => 'MYS',
                'region' => 'Asia',
                'subregion' => 'South-Eastern Asia',
            ]);

            $stateId = DB::table('states')->insertGetId([
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
            $countryId = DB::table('countries')->insertGetId([
                'iso2' => 'MY',
                'name' => 'Malaysia',
                'status' => 1,
                'phone_code' => '60',
                'iso3' => 'MYS',
                'region' => 'Asia',
                'subregion' => 'South-Eastern Asia',
            ]);

            $stateId = DB::table('states')->insertGetId([
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
            'event_type' => [EventType::KuliahCeramah],
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);

        Event::factory()->create([
            'title' => 'Forum Event',
            'event_type' => [EventType::Forum],
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

    it('filters events by selected bidang ilmu', function () {
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

    it('filters events by selected kategori (domain tags)', function () {
        $aqidahTag = Tag::factory()->domain()->create([
            'name' => ['en' => 'Aqidah', 'ms' => 'Aqidah'],
        ]);

        $akhlakTag = Tag::factory()->domain()->create([
            'name' => ['en' => 'Akhlak', 'ms' => 'Akhlak'],
        ]);

        $aqidahEvent = Event::factory()->create([
            'title' => 'Aqidah Intensive',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);
        $aqidahEvent->attachTag($aqidahTag);

        $akhlakEvent = Event::factory()->create([
            'title' => 'Akhlak Session',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);
        $akhlakEvent->attachTag($akhlakTag);

        $query = http_build_query(['domain_tag_ids' => [$aqidahTag->id]]);

        $response = $this->get("/events?{$query}");

        $response->assertOk()
            ->assertSee('Aqidah Intensive')
            ->assertDontSee('Akhlak Session')
            ->assertSee('Aqidah');
    });

    it('filters events by selected sumber rujukan utama tags', function () {
        $quranTag = Tag::factory()->source()->create([
            'name' => ['en' => 'Quran', 'ms' => 'Quran'],
        ]);

        $hadithTag = Tag::factory()->source()->create([
            'name' => ['en' => 'Hadith', 'ms' => 'Hadith'],
        ]);

        $quranEvent = Event::factory()->create([
            'title' => 'Quran Study Circle',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);
        $quranEvent->attachTag($quranTag);

        $hadithEvent = Event::factory()->create([
            'title' => 'Hadith Workshop',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);
        $hadithEvent->attachTag($hadithTag);

        $query = http_build_query(['source_tag_ids' => [$quranTag->id]]);

        $response = $this->get("/events?{$query}");

        $response->assertOk()
            ->assertSee('Quran Study Circle')
            ->assertDontSee('Hadith Workshop');
    });

    it('filters events by selected tema isu tags', function () {
        $familyTag = Tag::factory()->issue()->create([
            'name' => ['en' => 'Keluarga', 'ms' => 'Keluarga'],
        ]);

        $economyTag = Tag::factory()->issue()->create([
            'name' => ['en' => 'Ekonomi', 'ms' => 'Ekonomi'],
        ]);

        $familyEvent = Event::factory()->create([
            'title' => 'Isu Keluarga Semasa',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);
        $familyEvent->attachTag($familyTag);

        $economyEvent = Event::factory()->create([
            'title' => 'Perbincangan Isu Ekonomi',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);
        $economyEvent->attachTag($economyTag);

        $query = http_build_query(['issue_tag_ids' => [$familyTag->id]]);

        $response = $this->get("/events?{$query}");

        $response->assertOk()
            ->assertSee('Isu Keluarga Semasa')
            ->assertDontSee('Perbincangan Isu Ekonomi');
    });

    it('filters events by selected rujukan kitab buku', function () {
        $riyadhRef = Reference::factory()->create([
            'title' => 'Riyadhus Solihin',
            'is_active' => true,
        ]);

        $bulughRef = Reference::factory()->create([
            'title' => 'Bulughul Maram',
            'is_active' => true,
        ]);

        $riyadhEvent = Event::factory()->create([
            'title' => 'Riyadh Session',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(1),
        ]);
        $riyadhEvent->references()->attach($riyadhRef->id);

        $bulughEvent = Event::factory()->create([
            'title' => 'Bulugh Session',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);
        $bulughEvent->references()->attach($bulughRef->id);

        $query = http_build_query(['reference_ids' => [$riyadhRef->id]]);

        $response = $this->get("/events?{$query}");

        $response->assertOk()
            ->assertSee('Riyadh Session')
            ->assertDontSee('Bulugh Session');
    });

    it('shows approved, pending, and cancelled public events', function () {
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
            'title' => 'Cancelled Event',
            'status' => 'cancelled',
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
            ->assertSee('Cancelled Event')
            ->assertSee('Pending Approval')
            ->assertSee('Dibatalkan')
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

        $institution = Institution::factory()->create();
        $venue = Venue::factory()->create();

        Event::factory()
            ->for($institution)
            ->for($venue)
            ->hasSpeakers(1)
            ->create([
                'status' => 'approved',
                'visibility' => 'public',
                'published_at' => now(),
                'starts_at' => now()->addDays(1),
                'event_type' => [EventType::KuliahCeramah],
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

    it('ignores prayer time filter when timing mode is absolute', function () {
        Event::factory()->create([
            'title' => 'Absolute Timing Event',
            'status' => 'approved',
            'visibility' => 'public',
            'timing_mode' => TimingMode::Absolute,
            'published_at' => now(),
            'starts_at' => now()->addDays(2)->setTime(20, 0),
        ]);

        Event::factory()->create([
            'title' => 'Prayer Relative Timing Event',
            'status' => 'approved',
            'visibility' => 'public',
            'timing_mode' => TimingMode::PrayerRelative,
            'prayer_display_text' => 'Selepas Maghrib',
            'published_at' => now(),
            'starts_at' => now()->addDays(2)->setTime(20, 0),
        ]);

        $response = $this->get('/events?timing_mode='.TimingMode::Absolute->value.'&prayer_time=Selepas+Maghrib');

        $response->assertOk()
            ->assertSee('Absolute Timing Event')
            ->assertDontSee('Prayer Relative Timing Event');
    });

    it('treats explicit false URL filter as active and keeps active filter chips visible', function () {
        Event::factory()->create([
            'title' => 'No URL Event',
            'status' => 'approved',
            'visibility' => 'public',
            'event_url' => null,
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);

        Event::factory()->create([
            'title' => 'Has URL Event',
            'status' => 'approved',
            'visibility' => 'public',
            'event_url' => 'https://example.com/event',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);

        $response = $this->get('/events?has_event_url=0');

        $response->assertOk()
            ->assertSee('No URL Event')
            ->assertDontSee('Has URL Event')
            ->assertSee('No Event URL')
            ->assertSee('Clear All Filters')
            ->assertSee('Save This Search');
    });

    it('filters events by held date overlap range', function () {
        Event::factory()->create([
            'title' => 'Overlap Via End Time',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(4)->setTime(20, 0),
            'ends_at' => now()->addDays(5)->setTime(9, 0),
        ]);

        Event::factory()->create([
            'title' => 'Within Held Range',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(6)->setTime(12, 0),
            'ends_at' => null,
        ]);

        Event::factory()->create([
            'title' => 'Outside Before Range',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2)->setTime(10, 0),
            'ends_at' => now()->addDays(3)->setTime(10, 0),
        ]);

        Event::factory()->create([
            'title' => 'Outside After Range',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(7)->setTime(10, 0),
            'ends_at' => now()->addDays(8)->setTime(10, 0),
        ]);

        $query = http_build_query([
            'starts_after' => now()->addDays(5)->toDateString(),
            'starts_before' => now()->addDays(6)->toDateString(),
        ]);

        $response = $this->get("/events?{$query}");

        $response->assertOk()
            ->assertSee('Overlap Via End Time')
            ->assertSee('Within Held Range')
            ->assertDontSee('Outside Before Range')
            ->assertDontSee('Outside After Range');
    });

    it('filters events by prayer_time keyword in advanced filters', function () {
        Event::factory()->create([
            'title' => 'Kuliah Selepas Maghrib',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
            'timing_mode' => TimingMode::PrayerRelative,
            'prayer_reference' => PrayerReference::Maghrib,
            'prayer_display_text' => 'Selepas Maghrib',
        ]);

        Event::factory()->create([
            'title' => 'Kuliah Selepas Subuh',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
            'timing_mode' => TimingMode::PrayerRelative,
            'prayer_reference' => PrayerReference::Fajr,
            'prayer_display_text' => 'Selepas Subuh',
        ]);

        $response = $this->get('/events?prayer_time=Selepas+Maghrib');

        $response->assertOk()
            ->assertSee('Kuliah Selepas Maghrib')
            ->assertDontSee('Kuliah Selepas Subuh');
    });

    it('interprets starts_after date in the user timezone', function () {
        $userTimezone = 'Asia/Kuala_Lumpur';
        $localFilterDate = now($userTimezone)->addDays(2)->toDateString();

        $includedStartUtc = Carbon::parse($localFilterDate.' 01:00:00', $userTimezone)->setTimezone('UTC');
        $excludedStartUtc = Carbon::parse($localFilterDate.' 23:30:00', $userTimezone)
            ->subDay()
            ->setTimezone('UTC');

        Event::factory()->create([
            'title' => 'Timezone Included Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => $includedStartUtc,
            'ends_at' => null,
        ]);

        Event::factory()->create([
            'title' => 'Timezone Excluded Event',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => $excludedStartUtc,
            'ends_at' => null,
        ]);

        $response = $this
            ->withCookie('user_timezone', $userTimezone)
            ->get('/events?starts_after='.$localFilterDate);

        $response->assertOk()
            ->assertSee('Timezone Included Event')
            ->assertDontSee('Timezone Excluded Event');
    });

    it('filters events to past only when time scope is past', function () {
        Event::factory()->create([
            'title' => 'Past Scope Match',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->subDays(2),
        ]);

        Event::factory()->create([
            'title' => 'Past Scope Non Match',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);

        $response = $this->get('/events?time_scope=past');

        $response->assertOk()
            ->assertSee('Past Scope Match')
            ->assertDontSee('Past Scope Non Match');
    });

    it('shows both past and upcoming events when time scope is all', function () {
        Event::factory()->create([
            'title' => 'All Scope Past',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->subDays(2),
        ]);

        Event::factory()->create([
            'title' => 'All Scope Upcoming',
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(2),
        ]);

        $response = $this->get('/events?time_scope=all');

        $response->assertOk()
            ->assertSee('All Scope Past')
            ->assertSee('All Scope Upcoming');
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

    it('returns institution-based events in nearby search when venue is not set', function () {
        config(['scout.driver' => 'database']);

        $nearInstitution = Institution::factory()->create();
        $nearInstitution->address()->update([
            'lat' => 3.1390,
            'lng' => 101.6869,
        ]);

        $farInstitution = Institution::factory()->create();
        $farInstitution->address()->update([
            'lat' => 3.2600,
            'lng' => 101.8600,
        ]);

        Event::factory()->for($nearInstitution)->create([
            'title' => 'Nearby Institution Event',
            'venue_id' => null,
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(4),
        ]);

        Event::factory()->for($farInstitution)->create([
            'title' => 'Far Institution Event',
            'venue_id' => null,
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(4),
        ]);

        $events = app(EventSearchService::class)->searchNearby(
            lat: 3.1390,
            lng: 101.6869,
            radiusKm: 15,
            filters: [],
            perPage: 20
        );

        $eventTitles = collect($events->items())->pluck('title')->all();

        expect($eventTitles)->toContain('Nearby Institution Event');
        expect($eventTitles)->not->toContain('Far Institution Event');

        $nearest = collect($events->items())->firstWhere('title', 'Nearby Institution Event');

        expect($nearest)->not->toBeNull()
            ->and($nearest->distance_km ?? null)->not->toBeNull();
    });

    it('ignores event-level address records in nearby search', function () {
        config(['scout.driver' => 'database']);

        $institution = Institution::factory()->create();

        $event = Event::factory()->for($institution)->create([
            'title' => 'Event Address Should Be Ignored',
            'event_format' => EventFormat::Online,
            'institution_id' => null,
            'venue_id' => null,
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now(),
            'starts_at' => now()->addDays(4),
        ]);

        $event->address()->create([
            'lat' => 3.1390,
            'lng' => 101.6869,
        ]);

        $events = app(EventSearchService::class)->searchNearby(
            lat: 3.1390,
            lng: 101.6869,
            radiusKm: 15,
            filters: [],
            perPage: 20
        );

        $eventTitles = collect($events->items())->pluck('title')->all();

        expect($eventTitles)->not->toContain('Event Address Should Be Ignored');
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
            ->assertSee('Pending Approval');
    });

    it('shows cancelled events on detail page with cancellation banner', function () {
        $event = Event::factory()->create([
            'title' => 'Cancelled Detail Event',
            'status' => 'cancelled',
            'visibility' => 'public',
        ]);

        $response = $this->get("/events/{$event->slug}");

        $response->assertOk()
            ->assertSee('Cancelled Detail Event')
            ->assertSee('Majlis Dibatalkan')
            ->assertSee('Kalendar tidak tersedia untuk majlis dibatalkan.')
            ->assertDontSee('Tambah ke Kalendar')
            ->assertSee('https://schema.org/EventCancelled');
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
            ->assertSee('Main Related Event')
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
            ->has(EventSettings::factory()->state(['registration_required' => true]), 'settings')
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
            ->has(EventSettings::factory()->state(['registration_required' => false]), 'settings')
            ->create([
                'status' => 'approved',
                'visibility' => 'public',
                'published_at' => now(),
            ]);

        $response = $this->get("/events/{$event->slug}");

        $response->assertOk()
            ->assertDontSee('Registration Required')
            ->assertDontSee('Register Now');
    });

    it('allows guest registration', function () {
        $event = Event::factory()
            ->has(EventSettings::factory()->state([
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
            ->has(EventSettings::factory()->state([
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
            ->has(EventSettings::factory()->state([
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

        Registration::factory()->create([
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
