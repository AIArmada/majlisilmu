<?php

use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Models\District;
use App\Models\Event;
use App\Models\State;
use App\Models\Subdistrict;
use App\Models\Venue;

it('lists only active public approved or pending events', function () {
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

    $response->assertOk();

    $eventIds = collect($response->json('data'))->pluck('id')->all();

    expect($eventIds)
        ->toContain($approvedPublic->id)
        ->toContain($pendingPublic->id)
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
