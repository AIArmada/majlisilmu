<?php

use AIArmada\Signals\Models\SignalEvent;
use App\Filament\Resources\SlugRedirects\Pages\ListSlugRedirects;
use App\Forms\VenueFormSchema;
use App\Models\Country;
use App\Models\District;
use App\Models\Event;
use App\Models\Reference;
use App\Models\SlugRedirect;
use App\Models\State;
use App\Models\Subdistrict;
use App\Models\User;
use App\Models\Venue;
use App\Services\ContributionEntityMutationService;
use App\Services\Signals\SignalsTracker;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('registers public slug route binders during app boot', function () {
    foreach (['event', 'institution', 'speaker', 'venue', 'reference'] as $parameter) {
        expect(app('router')->getBindingCallback($parameter))
            ->not->toBeNull();
    }
});

it('creates an institution slug redirect only after the old public path has been visited', function () {
    $proposer = User::factory()->create();
    $geography = createSlugRedirectGeography();

    $institution = app(ContributionEntityMutationService::class)->createInstitution([
        'name' => 'Masjid Lama',
        'type' => 'masjid',
        'address' => slugRedirectAddressPayload($geography),
    ], $proposer);

    $oldPath = route('institutions.show', $institution, false);
    recordVisitedPath($oldPath);

    $institution->update([
        'name' => 'Masjid Baru',
    ]);

    $redirect = SlugRedirect::query()->firstOrFail();

    expect($redirect->source_slug)->toBe('masjid-lama-shah-alam-petaling-selangor-my')
        ->and($redirect->source_path)->toBe($oldPath)
        ->and($redirect->destination_slug)->toBe('masjid-baru-shah-alam-petaling-selangor-my')
        ->and($redirect->destination_path)->toBe(route('institutions.show', $institution->fresh(), false));
});

it('creates a speaker slug redirect when a visited slug changes', function () {
    $proposer = User::factory()->create();
    $country = createSlugRedirectCountry();

    $speaker = app(ContributionEntityMutationService::class)->createSpeaker([
        'name' => 'Ustaz Lama',
        'gender' => 'male',
        'country_id' => (string) $country->getKey(),
    ], $proposer);

    $oldPath = route('speakers.show', $speaker, false);
    recordVisitedPath($oldPath);

    $speaker->update([
        'name' => 'Ustaz Baru',
    ]);

    $redirect = SlugRedirect::query()->firstOrFail();

    expect($redirect->source_path)->toBe($oldPath)
        ->and($redirect->destination_path)->toBe(route('speakers.show', $speaker->fresh(), false));
});

it('creates a reference slug redirect when a visited title slug changes', function () {
    $reference = Reference::query()->create([
        'title' => 'Kitab Lama',
        'type' => 'kitab',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $oldPath = route('references.show', $reference, false);
    recordVisitedPath($oldPath);

    $reference->update([
        'title' => 'Kitab Baru',
    ]);

    $redirect = SlugRedirect::query()->firstOrFail();

    expect($redirect->source_path)->toBe($oldPath)
        ->and($redirect->destination_path)->toBe(route('references.show', $reference->fresh(), false));
});

it('creates an event slug redirect when a visited dated slug changes', function () {
    $event = createSlugRedirectEvent(
        id: '00000000-0000-0000-0000-000000000071',
        title: 'Majlis Lama',
        slug: 'majlis-lama-12-4-26',
        startsAt: Carbon::parse('2026-04-12 20:00:00', 'Asia/Kuala_Lumpur')->utc(),
    );

    $oldPath = route('events.show', $event, false);
    recordVisitedPath($oldPath);

    $event->update([
        'title' => 'Majlis Baru',
    ]);

    $redirect = SlugRedirect::query()->firstOrFail();

    expect($redirect->source_path)->toBe($oldPath)
        ->and($redirect->destination_path)->toBe(route('events.show', $event->fresh(), false));
});

it('does not create redirect rows for unvisited slug changes', function () {
    $proposer = User::factory()->create();
    $geography = createSlugRedirectGeography();

    $institution = app(ContributionEntityMutationService::class)->createInstitution([
        'name' => 'Masjid Tidak Dilawat',
        'type' => 'masjid',
        'address' => slugRedirectAddressPayload($geography),
    ], $proposer);

    $institution->update([
        'name' => 'Masjid Tidak Dilawat Baru',
    ]);

    expect(SlugRedirect::query()->count())->toBe(0);
});

it('creates a venue slug redirect when a visited geographic slug changes', function () {
    $geography = createSlugRedirectGeography();

    $venueId = VenueFormSchema::createOptionUsing([
        'name' => 'Dewan Lama',
        'type' => 'dewan',
        'address' => slugRedirectAddressPayload($geography),
    ]);

    $venue = Venue::query()->findOrFail($venueId);

    $oldPath = route('venues.show', $venue, false);
    recordVisitedPath($oldPath);

    $venue->update([
        'name' => 'Dewan Baru',
    ]);

    $redirect = SlugRedirect::query()->firstOrFail();

    expect($redirect->source_path)->toBe($oldPath)
        ->and($redirect->destination_path)->toBe(route('venues.show', $venue->fresh(), false));
});

it('redirects old venue slugs to the current canonical public url', function () {
    $geography = createSlugRedirectGeography();

    $venueId = VenueFormSchema::createOptionUsing([
        'name' => 'Dewan Laluan Lama',
        'type' => 'dewan',
        'address' => slugRedirectAddressPayload($geography),
    ]);

    $venue = Venue::query()->findOrFail($venueId);

    $oldPath = route('venues.show', $venue, false);
    recordVisitedPath($oldPath);

    $venue->update([
        'name' => 'Dewan Laluan Baru',
    ]);

    $this->get($oldPath)
        ->assertRedirect(route('venues.show', $venue->fresh()));
});

it('redirects old institution slugs to the current canonical public url', function () {
    $proposer = User::factory()->create();
    $geography = createSlugRedirectGeography();

    $institution = app(ContributionEntityMutationService::class)->createInstitution([
        'name' => 'Masjid Laluan Lama',
        'type' => 'masjid',
        'address' => slugRedirectAddressPayload($geography),
    ], $proposer);

    $oldPath = route('institutions.show', $institution, false);
    recordVisitedPath($oldPath);

    $institution->update([
        'name' => 'Masjid Laluan Baru',
    ]);

    $this->get($oldPath)
        ->assertRedirect(route('institutions.show', $institution->fresh()));
});

it('shows slug redirects in the admin resource table', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(PermissionSeeder::class);

    $administrator = User::factory()->create();
    $administrator->assignRole('super_admin');

    $proposer = User::factory()->create();
    $geography = createSlugRedirectGeography();

    $institution = app(ContributionEntityMutationService::class)->createInstitution([
        'name' => 'Masjid Admin Lama',
        'type' => 'masjid',
        'address' => slugRedirectAddressPayload($geography),
    ], $proposer);

    recordVisitedPath(route('institutions.show', $institution, false));

    $institution->update([
        'name' => 'Masjid Admin Baru',
    ]);

    $redirect = SlugRedirect::query()->firstOrFail();

    Livewire::actingAs($administrator)
        ->test(ListSlugRedirects::class)
        ->assertCanSeeTableRecords([$redirect])
        ->assertSee($redirect->source_path)
        ->assertSee($redirect->destination_path);
});

function createSlugRedirectCountry(
    string $countryName = 'Malaysia',
    string $countryIso2 = 'MY',
    string $countryIso3 = 'MYS',
    int $countryId = 132,
    string $phoneCode = '60',
): Country {
    $country = Country::query()->find($countryId);

    if ($country instanceof Country) {
        return $country;
    }

    $country = new Country;
    $country->forceFill([
        'id' => $countryId,
        'name' => $countryName,
        'iso2' => $countryIso2,
        'iso3' => $countryIso3,
        'phone_code' => $phoneCode,
        'region' => 'Asia',
        'subregion' => 'South-Eastern Asia',
        'status' => 1,
    ]);
    $country->save();

    return $country;
}

/**
 * @return array{country: Country, state: State, district: District, subdistrict: Subdistrict}
 */
function createSlugRedirectGeography(): array
{
    $country = createSlugRedirectCountry();

    $state = State::query()->create([
        'country_id' => (int) $country->getKey(),
        'name' => 'Selangor',
        'country_code' => 'MY',
    ]);

    $district = District::query()->create([
        'country_id' => (int) $country->getKey(),
        'state_id' => (int) $state->getKey(),
        'country_code' => 'MY',
        'name' => 'Petaling',
    ]);

    $subdistrict = Subdistrict::query()->create([
        'country_id' => (int) $country->getKey(),
        'state_id' => (int) $state->getKey(),
        'district_id' => (int) $district->getKey(),
        'country_code' => 'MY',
        'name' => 'Shah Alam',
    ]);

    return [
        'country' => $country,
        'state' => $state,
        'district' => $district,
        'subdistrict' => $subdistrict,
    ];
}

/**
 * @param  array{country: Country, state: State, district: District, subdistrict: Subdistrict}  $geography
 * @return array<string, string>
 */
function slugRedirectAddressPayload(array $geography): array
{
    return [
        'country_id' => (string) $geography['country']->getKey(),
        'state_id' => (string) $geography['state']->getKey(),
        'district_id' => (string) $geography['district']->getKey(),
        'subdistrict_id' => (string) $geography['subdistrict']->getKey(),
    ];
}

function recordVisitedPath(string $path): void
{
    $trackedProperty = app(SignalsTracker::class)->defaultTrackedProperty();

    expect($trackedProperty)->not->toBeNull();

    SignalEvent::query()
        ->withoutOwnerScope()
        ->create([
            'tracked_property_id' => (string) $trackedProperty?->getKey(),
            'occurred_at' => now(),
            'event_name' => (string) config('signals.defaults.page_view_event_name', 'page_view'),
            'event_category' => 'page_view',
            'path' => $path,
            'url' => url($path),
            'currency' => 'MYR',
            'revenue_minor' => 0,
        ]);
}

function createSlugRedirectEvent(string $id, string $title, string $slug, Carbon $startsAt): Event
{
    return Event::unguarded(fn () => Event::query()->create([
        'id' => $id,
        'title' => $title,
        'slug' => $slug,
        'event_structure' => 'standalone',
        'starts_at' => $startsAt,
        'timezone' => 'Asia/Kuala_Lumpur',
        'event_type' => ['other'],
        'gender' => 'all',
        'age_group' => ['all_ages'],
        'children_allowed' => true,
        'event_format' => 'physical',
        'visibility' => 'public',
        'status' => 'approved',
        'is_active' => true,
    ]));
}
