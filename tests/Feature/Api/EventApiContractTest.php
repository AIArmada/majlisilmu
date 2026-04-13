<?php

use App\Enums\EventKeyPersonRole;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Models\District;
use App\Models\Event;
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
