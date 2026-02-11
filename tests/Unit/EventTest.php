<?php

use App\Models\District;
use App\Models\Event;
use App\Models\State;
use App\Models\Subdistrict;
use App\Models\Venue;
use App\States\EventStatus\Approved;
use App\States\EventStatus\Draft;
use App\States\EventStatus\Pending;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('active scope filters approved and pending public events', function () {
    // Active events (approved + pending)
    $approvedEvent = Event::factory()->create([
        'status' => Approved::class,
        'visibility' => 'public',
        'is_active' => true,
    ]);

    $pendingEvent = Event::factory()->create([
        'status' => Pending::class,
        'visibility' => 'public',
        'is_active' => true,
    ]);

    // Inactive events
    $draftEvent = Event::factory()->create([
        'status' => Draft::class,
        'visibility' => 'public',
        'is_active' => true,
    ]);

    $privateEvent = Event::factory()->create([
        'status' => Approved::class,
        'visibility' => 'private',
        'is_active' => true,
    ]);

    $deactivatedEvent = Event::factory()->create([
        'status' => Approved::class,
        'visibility' => 'public',
        'is_active' => false,
    ]);

    $results = Event::active()->get();

    expect($results)->toHaveCount(2)
        ->and($results->pluck('id')->toArray())->toContain($approvedEvent->id)
        ->and($results->pluck('id')->toArray())->toContain($pendingEvent->id)
        ->and($results->pluck('id')->toArray())->not->toContain($draftEvent->id)
        ->and($results->pluck('id')->toArray())->not->toContain($privateEvent->id)
        ->and($results->pluck('id')->toArray())->not->toContain($deactivatedEvent->id);
});

it('searchable payload includes is_active and subdistrict_id location fields', function () {
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
        'name' => 'Search Payload District '.uniqid(),
    ]);

    $subdistrict = Subdistrict::query()->create([
        'country_id' => $state->country_id,
        'state_id' => $state->id,
        'district_id' => $district->id,
        'country_code' => 'MY',
        'name' => 'Search Payload Subdistrict '.uniqid(),
    ]);

    $venue = Venue::factory()->create();
    $venue->address()->update([
        'state_id' => $state->id,
        'district_id' => $district->id,
        'subdistrict_id' => $subdistrict->id,
    ]);

    $event = Event::factory()->for($venue)->create([
        'status' => 'approved',
        'visibility' => 'public',
        'is_active' => true,
    ]);

    $payload = $event->toSearchableArray();

    expect($payload)
        ->toHaveKey('is_active', true)
        ->and($payload)->toHaveKey('state_id', $state->id)
        ->and($payload)->toHaveKey('district_id', $district->id)
        ->and($payload)->toHaveKey('subdistrict_id', $subdistrict->id);
});
