<?php

use App\Enums\EventKeyPersonRole;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\PrayerReference;
use App\Enums\ReferenceType;
use App\Enums\TimingMode;
use App\Models\District;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use App\Models\State;
use App\Models\Subdistrict;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('lists only active public visible statuses (approved, pending, cancelled)', function () {
    $approvedPublic = Event::factory()->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
    ]);

    $pendingPublic = Event::factory()->create([
        'status' => 'pending',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
    ]);

    $cancelledPublic = Event::factory()->create([
        'status' => 'cancelled',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
    ]);

    $draftPublic = Event::factory()->create([
        'status' => 'draft',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
    ]);

    $approvedUnlisted = Event::factory()->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Unlisted,
        'is_active' => true,
    ]);

    $inactiveApproved = Event::factory()->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => false,
    ]);

    $response = $this->getJson(route('api.events.index'));

    $response->assertOk()
        ->assertJsonPath('total', 3)
        ->assertJsonPath('meta.pagination.total', 3)
        ->assertJsonPath('meta.request_id', fn (string $requestId) => filled($requestId));

    $eventIds = collect($response->json('data'))->pluck('id')->all();

    expect($eventIds)
        ->toContain($approvedPublic->id)
        ->toContain($pendingPublic->id)
        ->toContain($cancelledPublic->id)
        ->not()->toContain($draftPublic->id)
        ->not()->toContain($approvedUnlisted->id)
        ->not()->toContain($inactiveApproved->id);
});

it('filters events by json event_type values', function () {
    $kuliah = Event::factory()->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
        'event_type' => [EventType::KuliahCeramah->value],
    ]);

    $forum = Event::factory()->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
        'event_type' => [EventType::Forum->value],
    ]);

    $response = $this->getJson('/api/v1/events?filter[event_type]=kuliah_ceramah');

    $response->assertOk();

    $eventIds = collect($response->json('data'))->pluck('id')->all();

    expect($eventIds)
        ->toContain($kuliah->id)
        ->not()->toContain($forum->id);
});

it('filters events by district_id and subdistrict_id', function () {
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
        'name' => 'API District '.uniqid(),
    ]);

    $subdistrictA = Subdistrict::query()->create([
        'country_id' => $state->country_id,
        'state_id' => $state->id,
        'district_id' => $district->id,
        'country_code' => 'MY',
        'name' => 'API Subdistrict A '.uniqid(),
    ]);

    $subdistrictB = Subdistrict::query()->create([
        'country_id' => $state->country_id,
        'state_id' => $state->id,
        'district_id' => $district->id,
        'country_code' => 'MY',
        'name' => 'API Subdistrict B '.uniqid(),
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

    $districtMatch = Event::factory()->for($venueA)->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
    ]);

    $subdistrictNonMatch = Event::factory()->for($venueB)->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
    ]);

    $districtResponse = $this->getJson('/api/v1/events?filter[district_id]='.$district->id);

    $districtResponse->assertOk();

    $districtEventIds = collect($districtResponse->json('data'))->pluck('id')->all();

    expect($districtEventIds)
        ->toContain($districtMatch->id)
        ->toContain($subdistrictNonMatch->id);

    $subdistrictResponse = $this->getJson('/api/v1/events?filter[subdistrict_id]='.$subdistrictA->id);

    $subdistrictResponse->assertOk();

    $subdistrictEventIds = collect($subdistrictResponse->json('data'))->pluck('id')->all();

    expect($subdistrictEventIds)
        ->toContain($districtMatch->id)
        ->not()->toContain($subdistrictNonMatch->id);
});

it('interprets starts_after filter in the user timezone', function () {
    $userTimezone = 'Asia/Kuala_Lumpur';
    $localFilterDate = now($userTimezone)->addDays(2)->toDateString();

    $includedStartUtc = Carbon::parse($localFilterDate.' 01:00:00', $userTimezone)->setTimezone('UTC');
    $excludedStartUtc = Carbon::parse($localFilterDate.' 23:30:00', $userTimezone)
        ->subDay()
        ->setTimezone('UTC');

    $included = Event::factory()->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
        'starts_at' => $includedStartUtc,
    ]);

    $excluded = Event::factory()->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
        'starts_at' => $excludedStartUtc,
    ]);

    $response = $this
        ->withHeader('X-Timezone', $userTimezone)
        ->getJson('/api/v1/events?filter[starts_after]='.$localFilterDate);

    $response->assertOk();

    $eventIds = collect($response->json('data'))->pluck('id')->all();

    expect($eventIds)
        ->toContain($included->id)
        ->not()->toContain($excluded->id);
});

it('filters events by prayer_time keyword', function () {
    $maghribEvent = Event::factory()->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
        'timing_mode' => 'prayer_relative',
        'prayer_reference' => 'maghrib',
        'prayer_display_text' => 'Selepas Maghrib',
        'starts_at' => now()->addDays(2),
    ]);

    $subuhEvent = Event::factory()->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
        'timing_mode' => 'prayer_relative',
        'prayer_reference' => 'fajr',
        'prayer_display_text' => 'Selepas Subuh',
        'starts_at' => now()->addDays(2),
    ]);

    $response = $this->getJson('/api/v1/events?filter[prayer_time]=Selepas+Maghrib');

    $response->assertOk();

    $eventIds = collect($response->json('data'))->pluck('id')->all();

    expect($eventIds)
        ->toContain($maghribEvent->id)
        ->not()->toContain($subuhEvent->id);
});

it('filters events by grouped prayer buckets for before and after prayer labels', function (string $group, PrayerReference $reference, string $beforeLabel, string $afterLabel) {
    $otherPrayerReference = $reference === PrayerReference::Maghrib
        ? PrayerReference::Asr
        : PrayerReference::Maghrib;
    $otherPrayerLabel = $otherPrayerReference === PrayerReference::Asr
        ? 'Selepas Asar'
        : 'Selepas Maghrib';

    $beforeEvent = Event::factory()->create([
        'title' => "{$beforeLabel} Match",
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
        'starts_at' => now()->addDays(2),
        'timing_mode' => TimingMode::PrayerRelative,
        'prayer_reference' => $reference,
        'prayer_display_text' => $beforeLabel,
    ]);

    $afterEvent = Event::factory()->create([
        'title' => "{$afterLabel} Match",
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
        'starts_at' => now()->addDays(3),
        'timing_mode' => TimingMode::PrayerRelative,
        'prayer_reference' => $reference,
        'prayer_display_text' => $afterLabel,
    ]);

    $otherPrayerEvent = Event::factory()->create([
        'title' => 'Other Prayer Event',
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
        'starts_at' => now()->addDays(4),
        'timing_mode' => TimingMode::PrayerRelative,
        'prayer_reference' => $otherPrayerReference,
        'prayer_display_text' => $otherPrayerLabel,
    ]);

    $response = $this->getJson('/api/v1/events?filter[prayer_time]='.$group);

    $response->assertOk();

    $eventIds = collect($response->json('data'))->pluck('id')->all();

    expect($eventIds)
        ->toContain($beforeEvent->id)
        ->toContain($afterEvent->id)
        ->not()->toContain($otherPrayerEvent->id);
})->with([
    'subuh' => ['subuh', PrayerReference::Fajr, 'Sebelum Subuh', 'Selepas Subuh'],
    'jumaat' => ['jumaat', PrayerReference::FridayPrayer, 'Sebelum Jumaat', 'Selepas Jumaat'],
    'zuhur' => ['zuhur', PrayerReference::Dhuhr, 'Sebelum Zuhur', 'Selepas Zuhur'],
    'asar' => ['asar', PrayerReference::Asr, 'Sebelum Asar', 'Selepas Asar'],
    'maghrib' => ['maghrib', PrayerReference::Maghrib, 'Sebelum Maghrib', 'Selepas Maghrib'],
    'isya' => ['isya', PrayerReference::Isha, 'Sebelum Isyak', 'Selepas Isyak'],
]);

it('filters events by the dhuha group using morning events while excluding subuh and jumaat or zuhur buckets', function () {
    $absoluteMorningEvent = Event::factory()->create([
        'title' => 'Kuliah Dhuha Pagi',
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
        'timezone' => 'Asia/Kuala_Lumpur',
        'starts_at' => Carbon::parse('2026-04-20 09:15:00', 'Asia/Kuala_Lumpur')->utc(),
        'timing_mode' => TimingMode::Absolute,
        'prayer_reference' => null,
        'prayer_display_text' => null,
    ]);

    $relativeDhuhaEvent = Event::factory()->create([
        'title' => 'Kuliah Dhuha Khas',
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
        'starts_at' => now()->addDays(3),
        'timing_mode' => TimingMode::PrayerRelative,
        'prayer_reference' => null,
        'prayer_display_text' => 'Majlis Dhuha',
    ]);

    $subuhEvent = Event::factory()->create([
        'title' => 'Kuliah Selepas Subuh',
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
        'starts_at' => now()->addDays(2),
        'timing_mode' => TimingMode::PrayerRelative,
        'prayer_reference' => PrayerReference::Fajr,
        'prayer_display_text' => 'Selepas Subuh',
    ]);

    $jumaatEvent = Event::factory()->create([
        'title' => 'Forum Sebelum Jumaat',
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
        'starts_at' => now()->addDays(2),
        'timing_mode' => TimingMode::PrayerRelative,
        'prayer_reference' => PrayerReference::FridayPrayer,
        'prayer_display_text' => 'Sebelum Jumaat',
    ]);

    $zuhurEvent = Event::factory()->create([
        'title' => 'Kuliah Selepas Zuhur',
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
        'starts_at' => now()->addDays(2),
        'timing_mode' => TimingMode::PrayerRelative,
        'prayer_reference' => PrayerReference::Dhuhr,
        'prayer_display_text' => 'Selepas Zuhur',
    ]);

    $response = $this
        ->withHeader('X-Timezone', 'Asia/Kuala_Lumpur')
        ->getJson('/api/v1/events?filter[prayer_time]=dhuha');

    $response->assertOk();

    $eventIds = collect($response->json('data'))->pluck('id')->all();

    expect($eventIds)
        ->toContain($absoluteMorningEvent->id)
        ->toContain($relativeDhuhaEvent->id)
        ->not()->toContain($subuhEvent->id)
        ->not()->toContain($jumaatEvent->id)
        ->not()->toContain($zuhurEvent->id);
});

it('filters events by key person roles and role-specific linked speakers', function () {
    $imamSpeaker = Speaker::factory()->create(['status' => 'verified', 'is_active' => true]);
    $moderatorSpeaker = Speaker::factory()->create(['status' => 'verified', 'is_active' => true]);

    $imamEvent = Event::factory()->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
    ]);

    $imamEvent->keyPeople()->create([
        'role' => EventKeyPersonRole::Imam,
        'speaker_id' => $imamSpeaker->id,
        'order_column' => 1,
        'is_public' => true,
    ]);

    $moderatedEvent = Event::factory()->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
    ]);

    $moderatedEvent->keyPeople()->create([
        'role' => EventKeyPersonRole::Moderator,
        'speaker_id' => $moderatorSpeaker->id,
        'order_column' => 1,
        'is_public' => true,
    ]);

    $roleResponse = $this->getJson('/api/v1/events?filter[key_person_roles]=imam');

    $roleResponse->assertOk();

    $roleEventIds = collect($roleResponse->json('data'))->pluck('id')->all();

    expect($roleEventIds)
        ->toContain($imamEvent->id)
        ->not()->toContain($moderatedEvent->id);

    $speakerResponse = $this->getJson('/api/v1/events?filter[moderator_ids]='.$moderatorSpeaker->id);

    $speakerResponse->assertOk();

    $speakerEventIds = collect($speakerResponse->json('data'))->pluck('id')->all();

    expect($speakerEventIds)
        ->toContain($moderatedEvent->id)
        ->not()->toContain($imamEvent->id);
});

it('includes reference study subtitle in the generic paginated events payload', function () {
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
        'starts_at' => now()->addDays(3),
    ]);

    $bookReference = Reference::factory()->create([
        'title' => 'Al-Misbah Al-Munir',
        'type' => ReferenceType::Book->value,
        'status' => 'approved',
        'is_active' => true,
    ]);

    $event->references()->attach($bookReference->id);

    $response = $this->getJson('/api/v1/events?filter[institution_id]='.$event->institution_id.'&filter[status]=approved&include=speakers&page=1&per_page=15&sort=starts_at');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $event->id)
        ->assertJsonPath('data.0.reference_study_subtitle', 'Al-Misbah Al-Munir');
});

it('includes key person data in the event api response', function () {
    $imamSpeaker = Speaker::factory()->create(['status' => 'verified', 'is_active' => true]);

    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
    ]);

    $event->keyPeople()->create([
        'role' => EventKeyPersonRole::Imam,
        'speaker_id' => $imamSpeaker->id,
        'order_column' => 1,
        'is_public' => true,
    ]);

    $response = $this->getJson('/api/v1/events/'.$event->id);

    $response->assertOk()
        ->assertJsonPath('data.key_people.0.role', EventKeyPersonRole::Imam->value)
        ->assertJsonPath('data.key_people.0.speaker.id', $imamSpeaker->id)
        ->assertJsonPath('meta.request_id', fn (string $requestId) => filled($requestId));
});

it('serializes event detail payloads with poster metadata and included speakers', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
        'ends_at' => Carbon::parse('2026-03-14 22:15:00', 'UTC'),
    ]);

    $event->addMedia(fakeGeneratedImageUpload('event-poster.png', 1600, 900))
        ->toMediaCollection('poster');

    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $speaker->addMedia(fakeGeneratedImageUpload('speaker-avatar.png', 800, 800))
        ->toMediaCollection('avatar');

    $event->speakers()->attach($speaker->id);

    $response = $this->getJson('/api/v1/events/'.$event->id.'?include=speakers');

    $response->assertOk()
        ->assertJsonPath('data.id', $event->id)
        ->assertJsonPath('data.has_poster', true)
        ->assertJsonPath('data.speakers.0.id', $speaker->id)
        ->assertJsonPath('data.speakers.0.name', $speaker->name)
        ->assertJsonPath('data.speakers.0.formatted_name', $speaker->formatted_name)
        ->assertJsonPath('data.speakers.0.slug', $speaker->slug)
        ->assertJsonPath('data.speakers.0.avatar_url', $speaker->public_avatar_url);

    $payload = $response->json('data');

    expect(data_get($payload, 'poster_url'))->toBeString()->not->toBe('')
        ->and(data_get($payload, 'card_image_url'))->toBeString()->not->toBe('')
        ->and(data_get($payload, 'end_time_display'))->toBe('10:15 PM')
        ->and(is_array(data_get($payload, 'speakers.0')))->toBeTrue()
        ->and(array_key_exists('pivot', data_get($payload, 'speakers.0')))->toBeFalse();
});

it('serializes included institution address display fields on event detail payloads', function () {
    $countryId = DB::table('countries')->where('id', 132)->value('id');

    if (! is_int($countryId)) {
        $countryId = DB::table('countries')->insertGetId([
            'id' => 132,
            'iso2' => 'MY',
            'name' => 'Malaysia',
            'status' => 1,
            'phone_code' => '60',
            'iso3' => 'MYS',
            'region' => 'Asia',
            'subregion' => 'South-Eastern Asia',
        ]);
    }

    $state = State::where('country_code', 'MY')->first();

    if (! $state) {
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
        'name' => 'API Event District '.uniqid(),
    ]);

    $subdistrict = Subdistrict::query()->create([
        'country_id' => $state->country_id,
        'state_id' => $state->id,
        'district_id' => $district->id,
        'country_code' => 'MY',
        'name' => 'API Event Subdistrict '.uniqid(),
    ]);

    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $institution->address()->delete();
    $institution->address()->create([
        'line1' => 'No. 12 Jalan Ilmu',
        'line2' => 'Blok B',
        'postcode' => '43000',
        'country_id' => $countryId,
        'state_id' => $state->id,
        'district_id' => $district->id,
        'subdistrict_id' => $subdistrict->id,
        'google_maps_url' => 'https://maps.google.com/?q=3.1390,101.6869',
        'waze_url' => 'https://waze.com/ul?ll=3.1390,101.6869',
        'lat' => 3.139,
        'lng' => 101.6869,
    ]);

    $event = Event::factory()->for($institution)->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/v1/events/'.$event->id.'?include=institution,institution.address');

    $response->assertOk()
        ->assertJsonPath('data.institution.id', $institution->id)
        ->assertJsonPath('data.institution.address_line', $subdistrict->name.', '.$district->name.', '.$state->name)
        ->assertJsonPath('data.institution.street_address_line', 'No. 12 Jalan Ilmu, Blok B')
        ->assertJsonPath('data.institution.locality_address_line', $subdistrict->name.', 43000')
        ->assertJsonPath('data.institution.regional_address_line', $district->name.', '.$state->name)
        ->assertJsonPath('data.institution.map_url', 'https://maps.google.com/?q=3.1390,101.6869')
        ->assertJsonPath('data.institution.map_lat', 3.139)
        ->assertJsonPath('data.institution.map_lng', 101.6869)
        ->assertJsonPath('data.institution.waze_url', 'https://waze.com/ul?ll=3.1390,101.6869');
});
