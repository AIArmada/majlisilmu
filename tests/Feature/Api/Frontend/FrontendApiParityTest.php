<?php

use AIArmada\FilamentAuthz\Facades\Authz;
use App\Actions\Location\NormalizeGoogleMapsInputAction;
use App\Actions\Membership\AddMemberToSubject;
use App\Enums\ContactCategory;
use App\Enums\EventFormat;
use App\Enums\EventKeyPersonRole;
use App\Enums\EventStructure;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\InspirationCategory;
use App\Enums\InstitutionType;
use App\Http\Controllers\Api\Frontend\SearchController;
use App\Models\ContributionRequest;
use App\Models\Country;
use App\Models\District;
use App\Models\DonationChannel;
use App\Models\Event;
use App\Models\EventKeyPerson;
use App\Models\Inspiration;
use App\Models\Institution;
use App\Models\MembershipClaim;
use App\Models\Reference;
use App\Models\Series;
use App\Models\Speaker;
use App\Models\State;
use App\Models\Subdistrict;
use App\Models\Tag;
use App\Models\User;
use App\Models\Venue;
use App\Support\Authz\MemberRoleScopes;
use App\Support\Authz\ScopedMemberRoleSeeder;
use App\Support\Location\PublicCountryPreference;
use App\Support\Location\PublicCountryRegistry;
use App\Support\Search\InstitutionSearchService;
use App\Support\Search\SpeakerSearchService;
use Database\Seeders\PermissionSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    fakePrayerTimesApi();
    $this->seed(PermissionSeeder::class);
    setPermissionsTeamId(null);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function assignInstitutionOwnerForFrontendApi(User $user, Institution $institution): void
{
    app(ScopedMemberRoleSeeder::class)->ensureForInstitution();
    $institution->members()->syncWithoutDetaching([$user->id]);

    Authz::withScope(app(MemberRoleScopes::class)->institution(), function () use ($user): void {
        $user->syncRoles(['owner']);
    }, $user);
}

function assignSpeakerOwnerForFrontendApi(User $user, Speaker $speaker): void
{
    app(ScopedMemberRoleSeeder::class)->ensureForSpeaker();
    $speaker->members()->syncWithoutDetaching([$user->id]);

    Authz::withScope(app(MemberRoleScopes::class)->speaker(), function () use ($user): void {
        $user->syncRoles(['owner']);
    }, $user);
}

function ensureFrontendApiMalaysiaCountryExists(): int
{
    $malaysiaId = DB::table('countries')->where('id', 132)->value('id');

    if (is_int($malaysiaId)) {
        return $malaysiaId;
    }

    return DB::table('countries')->insertGetId([
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

it('exposes corrected frontend contract metadata', function () {
    ensureFrontendApiMalaysiaCountryExists();

    $manifest = $this->getJson(route('api.client.manifest'))
        ->assertOk()
        ->json('data');
    $contributionUpdateFlow = $manifest['flows']['contribution_update'] ?? [];
    $membershipClaimFlow = $manifest['flows']['membership_claim'] ?? [];
    $followFlow = $manifest['flows']['follow'] ?? [];
    $inspirationFlow = $manifest['flows']['inspirations_random'] ?? [];
    $quickstart = $manifest['ai_quickstart']['read_order'] ?? [];

    $submitEvent = $this->getJson(route('api.client.forms.submit-event'))
        ->assertOk()
        ->json('data');

    $submitEventFields = collect($submitEvent['fields'] ?? [])->pluck('name')->all();
    $submitEventConditionalRules = collect($submitEvent['conditional_rules'] ?? []);

    expect($submitEvent['captcha_required_when_turnstile_enabled'])->toBeTrue()
        ->and($manifest['version'] ?? null)->toBe('2026-04-16')
        ->and($manifest['docs']['ui'] ?? null)->toBe('https://api.majlisilmu.test/docs')
        ->and($manifest['docs']['openapi'] ?? null)->toBe('https://api.majlisilmu.test/docs.json')
        ->and($manifest['routing_surfaces']['public']['manifest_endpoint'] ?? null)->toContain('/api/v1/manifest')
        ->and($manifest['routing_surfaces']['admin']['manifest_endpoint'] ?? null)->toContain('/api/v1/admin/manifest')
        ->and($manifest['rules'] ?? [])->toContain('Use admin UUID id values for admin mutation paths; do not use route_key or public slugs.')
        ->and($quickstart[0]['endpoint'] ?? null)->toBe('https://api.majlisilmu.test/docs.json')
        ->and($submitEventFields)->toContain('parent_event_id', 'scoped_institution_id')
        ->and($submitEventFields)->toContain('submission_country_id', 'submission_country_code', 'submission_country_key')
        ->not->toContain('timezone')
        ->and($submitEventConditionalRules->pluck('field')->all())->not->toContain('live_url')
        ->and(collect($submitEvent['fields'])->firstWhere('name', 'submission_country_id')['allowed_values'])
        ->toContain(data_get($submitEvent, 'defaults.submission_country_id'))
        ->and(collect($submitEvent['fields'])->firstWhere('name', 'notes')['max_length'])->toBe(1000)
        ->and(collect($submitEvent['fields'])->firstWhere('name', 'captcha_token')['required'])->toBeFalse()
        ->and(collect($submitEvent['fields'])->firstWhere('name', 'submitter_email')['required'])->toBeFalse()
        ->and($contributionUpdateFlow['endpoint_template'] ?? null)->toContain('/api/v1/contributions/subjectType/subject/suggest')
        ->and($contributionUpdateFlow['schema_endpoint_template'] ?? null)->toContain('/api/v1/forms/contributions/subjectType/subject/suggest')
        ->and($membershipClaimFlow['endpoint_template'] ?? null)->toContain('/api/v1/membership-claims/subjectType/subject')
        ->and($followFlow['state_endpoint_template'] ?? null)->toContain('/api/v1/follows/type/subject')
        ->and($inspirationFlow['endpoint'] ?? null)->toContain('/api/v1/inspirations/random');

    $speakerContract = $this->getJson(route('api.client.forms.contributions.speakers'))
        ->assertOk()
        ->json('data');
    $speakerFields = collect($speakerContract['fields'] ?? [])->pluck('name')->all();

    expect($speakerFields)->toContain('job_title', 'avatar', 'cover', 'address', 'address.country_id', 'address.country_code', 'address.country_key', 'qualifications', 'institution_id', 'institution_position')
        ->not->toContain('address.line1')
        ->not->toContain('address.google_maps_url')
        ->not->toContain('position')
        ->not->toContain('main');

    $institutionContract = $this->getJson(route('api.client.forms.contributions.institutions'))
        ->assertOk()
        ->json('data');
    $institutionFields = collect($institutionContract['fields'] ?? [])->pluck('name')->all();

    expect($institutionFields)->not->toContain('logo');
});

it('exposes authenticated contribution update contracts and permission-gated direct edit media support', function () {
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'description' => 'Community institution',
    ]);

    assignInstitutionOwnerForFrontendApi($owner, $institution);

    Sanctum::actingAs($visitor);

    $visitorResponse = $this->getJson(route('api.client.forms.contributions.suggest', [
        'subjectType' => 'institusi',
        'subject' => $institution->slug,
    ]))->assertOk();

    Sanctum::actingAs($owner);

    $ownerResponse = $this->getJson(route('api.client.forms.contributions.suggest', [
        'subjectType' => 'institusi',
        'subject' => $institution->slug,
    ]))->assertOk();

    $fields = collect($ownerResponse->json('data.fields'));

    expect($visitorResponse->json('data.can_direct_edit'))->toBeFalse()
        ->and($visitorResponse->json('data.direct_edit_media_fields'))->toBe([])
        ->and($ownerResponse->json('data.can_direct_edit'))->toBeTrue()
        ->and($ownerResponse->json('data.direct_edit_media_fields'))->toBe(['cover', 'gallery'])
        ->and($fields->pluck('name')->all())->toContain('description', 'address', 'social_media')
        ->and($fields->firstWhere('name', 'type')['allowed_values'])->toContain('masjid')
        ->and($ownerResponse->json('data.initial_state.description'))->toBe('Community institution');
});

it('does not grant institution direct edit access from bearer token abilities alone', function () {
    $visitor = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'description' => 'Community institution',
    ]);

    $visitorToken = $visitor->createToken('visitor-device', ['institution.update'])->plainTextToken;

    $response = $this->withToken($visitorToken)
        ->getJson(route('api.client.forms.contributions.suggest', [
            'subjectType' => 'institusi',
            'subject' => $institution->slug,
        ]))->assertOk();

    expect($response->json('data.can_direct_edit'))->toBeFalse()
        ->and($response->json('data.direct_edit_media_fields'))->toBe([]);
});

it('allows institution direct edit access over bearer tokens without token abilities', function () {
    $owner = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'description' => 'Community institution',
    ]);

    assignInstitutionOwnerForFrontendApi($owner, $institution);

    $ownerToken = $owner->createToken('owner-device', [])->plainTextToken;

    $response = $this->withToken($ownerToken)
        ->getJson(route('api.client.forms.contributions.suggest', [
            'subjectType' => 'institusi',
            'subject' => $institution->slug,
        ]))->assertOk();

    expect($response->json('data.can_direct_edit'))->toBeTrue()
        ->and($response->json('data.direct_edit_media_fields'))->toBe(['cover', 'gallery'])
        ->and($response->json('data.initial_state.description'))->toBe('Community institution');
});

it('reflects scoped institution role grants and removals on an existing bearer token', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'description' => 'Community institution',
    ]);

    $token = $user->createToken('institution-role-drift-check', [])->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.client.forms.contributions.suggest', [
            'subjectType' => 'institusi',
            'subject' => $institution->slug,
        ]))
        ->assertOk()
        ->assertJsonPath('data.can_direct_edit', false);

    assignInstitutionOwnerForFrontendApi($user, $institution);

    $this->withToken($token)
        ->getJson(route('api.client.forms.contributions.suggest', [
            'subjectType' => 'institusi',
            'subject' => $institution->slug,
        ]))
        ->assertOk()
        ->assertJsonPath('data.can_direct_edit', true);

    Authz::withScope(app(MemberRoleScopes::class)->institution(), function () use ($user): void {
        $user->syncRoles([]);
    }, $user);

    $this->withToken($token)
        ->getJson(route('api.client.forms.contributions.suggest', [
            'subjectType' => 'institusi',
            'subject' => $institution->slug,
        ]))
        ->assertOk()
        ->assertJsonPath('data.can_direct_edit', false);
});

it('normalizes event update context to public organizer values and exposes lookup metadata', function () {
    $user = User::factory()->create();
    $startsAt = now()->addDays(3)->setTime(20, 15);
    $endsAt = now()->addDays(3)->setTime(21, 30);
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $event = Event::factory()->for($institution)->create([
        'title' => 'API Contract Event',
        'slug' => 'api-contract-event',
        'status' => 'approved',
        'is_active' => true,
        'organizer_type' => Institution::class,
        'organizer_id' => $institution->getKey(),
        'institution_id' => $institution->getKey(),
        'event_type' => ['kuliah_ceramah'],
        'gender' => 'all',
        'age_group' => ['all_ages'],
        'event_format' => 'physical',
        'visibility' => 'public',
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(route('api.client.forms.contributions.suggest', [
        'subjectType' => 'majlis',
        'subject' => $event->slug,
    ]))->assertOk();

    $initialStateKeys = array_keys($response->json('data.initial_state'));
    $fields = collect($response->json('data.fields'));
    $fieldNames = $fields->pluck('name')->all();

    expect($response->json('data.initial_state.organizer_type'))->toBe('institution')
        ->and($response->json('data.initial_state.organizer_institution_id'))->toBe((string) $institution->getKey())
        ->and($response->json('data.initial_state.event_date'))->toBe($event->fresh()->starts_at?->timezone('Asia/Kuala_Lumpur')->toDateString())
        ->and($response->json('data.initial_state.custom_time'))->toBe($event->fresh()->starts_at?->timezone('Asia/Kuala_Lumpur')->format('H:i'))
        ->and($fields->firstWhere('name', 'organizer_type')['allowed_values'])->toBe(['institution', 'speaker'])
        ->and($fields->firstWhere('name', 'language_ids')['catalog'])->toContain('/api/v1/catalogs/languages')
        ->and($fields->firstWhere('name', 'speaker_ids')['catalog'])->toContain('/api/v1/catalogs/submit-speakers')
        ->and($fieldNames)->toContain(
            'event_date',
            'prayer_time',
            'custom_time',
            'end_time',
            'organizer_institution_id',
            'organizer_speaker_id',
            'location_same_as_institution',
            'location_type',
            'location_institution_id',
            'location_venue_id',
        )
        ->and($fieldNames)->not->toContain('starts_at', 'ends_at', 'organizer_id', 'institution_id', 'venue_id', 'timing_mode', 'prayer_reference', 'prayer_offset', 'prayer_display_text')
        ->and($initialStateKeys)->not->toContain('starts_at', 'ends_at', 'organizer_id', 'institution_id', 'venue_id', 'timing_mode', 'prayer_reference', 'prayer_offset', 'prayer_display_text');
});

it('exposes event direct edit media support for authorized public updaters', function () {
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $event = Event::factory()->for($institution)->create([
        'status' => 'approved',
        'is_active' => true,
        'visibility' => 'public',
        'organizer_type' => Institution::class,
        'organizer_id' => $institution->getKey(),
        'institution_id' => $institution->getKey(),
        'starts_at' => now()->addDays(4)->setTime(20, 0),
        'ends_at' => now()->addDays(4)->setTime(21, 0),
    ]);

    assignInstitutionOwnerForFrontendApi($owner, $institution);

    Sanctum::actingAs($visitor);

    $visitorResponse = $this->getJson(route('api.client.forms.contributions.suggest', [
        'subjectType' => 'majlis',
        'subject' => $event->slug,
    ]))->assertOk();

    Sanctum::actingAs($owner);

    $ownerResponse = $this->getJson(route('api.client.forms.contributions.suggest', [
        'subjectType' => 'majlis',
        'subject' => $event->slug,
    ]))->assertOk();

    expect($visitorResponse->json('data.can_direct_edit'))->toBeFalse()
        ->and($visitorResponse->json('data.direct_edit_media_fields'))->toBe([])
        ->and($ownerResponse->json('data.can_direct_edit'))->toBeTrue()
        ->and($ownerResponse->json('data.direct_edit_media_fields'))->toBe(['poster', 'gallery']);
});

it('accepts helper-shaped event timing updates on public contribution suggestions', function () {
    $owner = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $event = Event::factory()->for($institution)->create([
        'status' => 'approved',
        'is_active' => true,
        'visibility' => 'public',
        'organizer_type' => Institution::class,
        'organizer_id' => $institution->getKey(),
        'institution_id' => $institution->getKey(),
        'starts_at' => now()->addDays(2)->setTime(19, 0),
        'ends_at' => now()->addDays(2)->setTime(20, 0),
    ]);

    assignInstitutionOwnerForFrontendApi($owner, $institution);
    Sanctum::actingAs($owner);

    $targetDate = now()->addDays(5)->toDateString();

    $this->postJson(route('api.client.contributions.suggest.store', [
        'subjectType' => 'majlis',
        'subject' => $event->slug,
    ]), [
        'event_date' => $targetDate,
        'prayer_time' => 'lain_waktu',
        'custom_time' => '20:15',
        'end_time' => '21:30',
    ])
        ->assertOk()
        ->assertJsonPath('data.mode', 'direct_edit');

    $event = $event->fresh();

    expect($event->starts_at?->timezone('Asia/Kuala_Lumpur')->toDateString())->toBe($targetDate)
        ->and($event->starts_at?->timezone('Asia/Kuala_Lumpur')->format('H:i'))->toBe('20:15')
        ->and($event->ends_at?->timezone('Asia/Kuala_Lumpur')->format('H:i'))->toBe('21:30');
});

it('allows direct contribution updates to clear nullable institution fields', function () {
    $owner = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'description' => 'Old description',
    ]);

    assignInstitutionOwnerForFrontendApi($owner, $institution);
    Sanctum::actingAs($owner);

    $this->postJson(route('api.client.contributions.suggest.store', [
        'subjectType' => 'institusi',
        'subject' => $institution->slug,
    ]), [
        'description' => null,
    ])
        ->assertOk()
        ->assertJsonPath('data.mode', 'direct_edit');

    expect($institution->fresh()->description)->toBeNull();
});

it('exposes speaker avatar direct edit media support for authorized public updaters', function () {
    $owner = User::factory()->create();
    $visitor = User::factory()->create();
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    assignSpeakerOwnerForFrontendApi($owner, $speaker);

    Sanctum::actingAs($visitor);

    $visitorResponse = $this->getJson(route('api.client.forms.contributions.suggest', [
        'subjectType' => 'penceramah',
        'subject' => $speaker->slug,
    ]))->assertOk();

    Sanctum::actingAs($owner);

    $ownerResponse = $this->getJson(route('api.client.forms.contributions.suggest', [
        'subjectType' => 'penceramah',
        'subject' => $speaker->slug,
    ]))->assertOk();

    expect($visitorResponse->json('data.can_direct_edit'))->toBeFalse()
        ->and($visitorResponse->json('data.direct_edit_media_fields'))->toBe([])
        ->and($ownerResponse->json('data.can_direct_edit'))->toBeTrue()
        ->and($ownerResponse->json('data.direct_edit_media_fields'))->toBe(['avatar', 'cover', 'gallery']);
});

it('allows direct institution gallery uploads on public contribution update suggestions', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $owner = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
    ]);

    assignInstitutionOwnerForFrontendApi($owner, $institution);
    Sanctum::actingAs($owner);

    $this->post(route('api.client.contributions.suggest.store', [
        'subjectType' => 'institusi',
        'subject' => $institution->slug,
    ]), [
        'gallery' => [UploadedFile::fake()->image('institution-gallery.jpg', 1600, 900)],
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('data.mode', 'direct_edit');

    expect($institution->fresh()?->getMedia('gallery'))->toHaveCount(1);
});

it('returns only region address keys in the speaker suggest context state', function () {
    $owner = User::factory()->create();
    $countryId = ensureFrontendApiMalaysiaCountryExists();

    $state = State::query()->create([
        'country_id' => $countryId,
        'name' => 'Negeri Konteks Penceramah API',
        'country_code' => 'MY',
    ]);

    $district = District::query()->create([
        'country_id' => $countryId,
        'state_id' => (int) $state->id,
        'name' => 'Daerah Konteks Penceramah API',
        'country_code' => 'MY',
    ]);

    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $speaker->address()->update([
        'country_id' => $countryId,
        'state_id' => (int) $state->id,
        'district_id' => (int) $district->id,
        'line1' => 'Jalan Lama 1',
        'line2' => 'Taman Lama',
        'postcode' => '50000',
        'lat' => 3.139,
        'lng' => 101.6869,
        'google_maps_url' => 'https://maps.google.com/?q=3.1390,101.6869',
        'google_place_id' => 'speaker-suggest-place',
        'waze_url' => 'https://waze.com/ul?ll=3.1390,101.6869',
    ]);

    assignSpeakerOwnerForFrontendApi($owner, $speaker);
    Sanctum::actingAs($owner);

    $response = $this->getJson(route('api.client.forms.contributions.suggest', [
        'subjectType' => 'penceramah',
        'subject' => $speaker->slug,
    ]))->assertOk();

    expect($response->json('data.initial_state.address'))->toBe([
        'country_id' => $countryId,
        'state_id' => (int) $state->id,
        'district_id' => (int) $district->id,
        'subdistrict_id' => null,
    ]);
});

it('treats unchanged speaker region-only address round trips as no-op updates', function () {
    $owner = User::factory()->create();
    $countryId = ensureFrontendApiMalaysiaCountryExists();

    $state = State::query()->create([
        'country_id' => $countryId,
        'name' => 'Negeri Pusing Balik Penceramah API',
        'country_code' => 'MY',
    ]);

    $district = District::query()->create([
        'country_id' => $countryId,
        'state_id' => (int) $state->id,
        'name' => 'Daerah Pusing Balik Penceramah API',
        'country_code' => 'MY',
    ]);

    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $speaker->address()->update([
        'country_id' => $countryId,
        'state_id' => (int) $state->id,
        'district_id' => (int) $district->id,
        'line1' => 'Alamat Warisan',
        'google_maps_url' => 'https://maps.google.com/?q=3.1390,101.6869',
    ]);

    assignSpeakerOwnerForFrontendApi($owner, $speaker);
    Sanctum::actingAs($owner);

    $this->postJson(route('api.client.contributions.suggest.store', [
        'subjectType' => 'penceramah',
        'subject' => $speaker->slug,
    ]), [
        'address' => [
            'country_id' => $countryId,
            'state_id' => (int) $state->id,
            'district_id' => (int) $district->id,
            'subdistrict_id' => null,
        ],
    ])->assertOk();

    $speaker = $speaker->fresh('address');
    $expectedGoogleMapsUrl = app(NormalizeGoogleMapsInputAction::class)->handle([
        'google_maps_url' => 'https://maps.google.com/?q=3.1390,101.6869',
    ])['google_maps_url'];

    expect($speaker?->addressModel?->line1)->toBe('Alamat Warisan')
        ->and($speaker?->addressModel?->google_maps_url)->toBe($expectedGoogleMapsUrl);
});

it('preserves hidden speaker address details during region-only direct updates', function () {
    $owner = User::factory()->create();
    $countryId = ensureFrontendApiMalaysiaCountryExists();

    $state = State::query()->create([
        'country_id' => $countryId,
        'name' => 'Negeri Kekal Butiran Penceramah API',
        'country_code' => 'MY',
    ]);

    $district = District::query()->create([
        'country_id' => $countryId,
        'state_id' => (int) $state->id,
        'name' => 'Daerah Kekal Butiran Penceramah API',
        'country_code' => 'MY',
    ]);

    $updatedDistrict = District::query()->create([
        'country_id' => $countryId,
        'state_id' => (int) $state->id,
        'name' => 'Daerah Baharu Penceramah API',
        'country_code' => 'MY',
    ]);

    $speaker = Speaker::factory()->create([
        'name' => 'Penceramah Lama API',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $speaker->address()->update([
        'country_id' => $countryId,
        'state_id' => (int) $state->id,
        'district_id' => (int) $district->id,
        'line1' => 'Alamat Warisan',
        'google_maps_url' => 'https://maps.google.com/?q=3.1390,101.6869',
    ]);

    assignSpeakerOwnerForFrontendApi($owner, $speaker);
    Sanctum::actingAs($owner);

    $this->postJson(route('api.client.contributions.suggest.store', [
        'subjectType' => 'penceramah',
        'subject' => $speaker->slug,
    ]), [
        'name' => 'Penceramah Dikemas Kini API',
        'address' => [
            'country_id' => $countryId,
            'state_id' => (int) $state->id,
            'district_id' => (int) $updatedDistrict->id,
            'subdistrict_id' => null,
        ],
    ])->assertOk()
        ->assertJsonPath('data.mode', 'direct_edit');

    $speaker = $speaker->fresh('address');

    $expectedGoogleMapsUrl = app(NormalizeGoogleMapsInputAction::class)->handle([
        'google_maps_url' => 'https://maps.google.com/?q=3.1390,101.6869',
    ])['google_maps_url'];

    expect($speaker?->name)->toBe('Penceramah Dikemas Kini API')
        ->and($speaker?->addressModel?->district_id)->toBe((int) $updatedDistrict->id)
        ->and($speaker?->addressModel?->line1)->toBe('Alamat Warisan')
        ->and($speaker?->addressModel?->google_maps_url)->toBe($expectedGoogleMapsUrl);
});

it('allows direct speaker avatar uploads on public contribution update suggestions', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $owner = User::factory()->create();
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    assignSpeakerOwnerForFrontendApi($owner, $speaker);
    Sanctum::actingAs($owner);

    $this->post(route('api.client.contributions.suggest.store', [
        'subjectType' => 'penceramah',
        'subject' => $speaker->slug,
    ]), [
        'avatar' => UploadedFile::fake()->image('speaker-avatar.jpg', 1200, 1200),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('data.mode', 'direct_edit');

    $speaker = $speaker->fresh(['media']);

    expect($speaker?->hasMedia('avatar'))->toBeTrue()
        ->and($speaker?->public_avatar_url)->not->toBe('');
});

it('allows direct speaker cover uploads on public contribution update suggestions', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $coverUpload = fakeGeneratedImageUpload('speaker-cover.png', 1200, 1200);

    $owner = User::factory()->create();
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    assignSpeakerOwnerForFrontendApi($owner, $speaker);
    Sanctum::actingAs($owner);

    $this->post(route('api.client.contributions.suggest.store', [
        'subjectType' => 'penceramah',
        'subject' => $speaker->slug,
    ]), [
        'cover' => $coverUpload,
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('data.mode', 'direct_edit');

    $speaker = $speaker->fresh(['media']);

    expect($speaker?->hasMedia('cover'))->toBeTrue()
        ->and($speaker?->getFirstMediaUrl('cover'))->not->toBe('');
});

it('allows direct speaker gallery uploads on public contribution update suggestions', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $owner = User::factory()->create();
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    assignSpeakerOwnerForFrontendApi($owner, $speaker);
    Sanctum::actingAs($owner);

    $this->post(route('api.client.contributions.suggest.store', [
        'subjectType' => 'penceramah',
        'subject' => $speaker->slug,
    ]), [
        'gallery' => [UploadedFile::fake()->image('speaker-gallery.jpg', 1200, 1200)],
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('data.mode', 'direct_edit');

    expect($speaker->fresh()?->getMedia('gallery'))->toHaveCount(1);
});

it('rejects unsupported speaker media files on public contribution update suggestions', function () {
    $owner = User::factory()->create();
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    assignSpeakerOwnerForFrontendApi($owner, $speaker);
    Sanctum::actingAs($owner);

    $this->post(route('api.client.contributions.suggest.store', [
        'subjectType' => 'penceramah',
        'subject' => $speaker->slug,
    ]), [
        'poster' => UploadedFile::fake()->image('speaker-poster.jpg'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['files']);
});

it('maps public event organizer values back to persistence classes during direct updates', function () {
    $owner = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $event = Event::factory()->for($institution)->create([
        'status' => 'approved',
        'is_active' => true,
        'organizer_type' => Institution::class,
        'organizer_id' => $institution->getKey(),
        'starts_at' => now()->setTimezone('Asia/Kuala_Lumpur')->startOfDay()->addHours(10)->utc(),
        'ends_at' => now()->setTimezone('Asia/Kuala_Lumpur')->startOfDay()->addHours(12)->utc(),
        'timezone' => 'Asia/Kuala_Lumpur',
        'live_url' => 'https://live.example.test/watch',
        'event_type' => ['kuliah_ceramah'],
        'gender' => 'all',
        'age_group' => ['all_ages'],
        'event_format' => 'physical',
        'visibility' => 'public',
    ]);

    assignInstitutionOwnerForFrontendApi($owner, $institution);
    Sanctum::actingAs($owner);

    $this->postJson(route('api.client.contributions.suggest.store', [
        'subjectType' => 'majlis',
        'subject' => $event->slug,
    ]), [
        'organizer_type' => 'speaker',
        'organizer_speaker_id' => $speaker->getKey(),
        'live_url' => null,
    ])
        ->assertOk()
        ->assertJsonPath('data.mode', 'direct_edit');

    expect($event->fresh()->organizer_type)->toBe(Speaker::class)
        ->and($event->fresh()->organizer_id)->toBe($speaker->getKey())
        ->and($event->fresh()->live_url)->toBeNull();
});

it('allows direct event poster and gallery uploads on public contribution update suggestions', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $owner = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $event = Event::factory()->for($institution)->create([
        'status' => 'approved',
        'is_active' => true,
        'visibility' => 'public',
        'organizer_type' => Institution::class,
        'organizer_id' => $institution->getKey(),
        'institution_id' => $institution->getKey(),
    ]);

    assignInstitutionOwnerForFrontendApi($owner, $institution);
    Sanctum::actingAs($owner);

    $this->post(route('api.client.contributions.suggest.store', [
        'subjectType' => 'majlis',
        'subject' => $event->slug,
    ]), [
        'poster' => UploadedFile::fake()->image('event-poster.jpg', 1200, 1600),
        'gallery' => [UploadedFile::fake()->image('event-gallery.jpg', 1200, 800)],
    ], [
        'Accept' => 'application/json',
    ])
        ->assertOk()
        ->assertJsonPath('data.mode', 'direct_edit');

    $event = $event->fresh(['media']);

    expect($event?->getMedia('poster'))->toHaveCount(1)
        ->and($event?->getMedia('gallery'))->toHaveCount(1);
});

it('searches speakers api by formatted title parts used on the public directory', function () {
    $matchingSpeaker = Speaker::factory()->create([
        'name' => 'Aisyah Binti Hassan',
        'pre_nominal' => ['syeikhul_maqari'],
        'status' => 'verified',
        'is_active' => true,
    ]);

    $otherSpeaker = Speaker::factory()->create([
        'name' => 'Fatimah Binti Omar',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $response = $this->getJson('/api/v1/speakers?search='.urlencode('syeikhul maqari'))
        ->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())
        ->toContain((string) $matchingSpeaker->id)
        ->not->toContain((string) $otherSpeaker->id);
});

it('falls back to local speaker and institution search on the frontend unified search api when typesense fails', function () {
    $institution = Institution::factory()->create([
        'name' => 'Masjid Nur Hikmah',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $speaker = Speaker::factory()->create([
        'name' => 'Nur Hikmah Hassan',
        'honorific' => null,
        'pre_nominal' => [],
        'post_nominal' => [],
        'qualifications' => [],
        'status' => 'verified',
        'is_active' => true,
    ]);

    app(SpeakerSearchService::class)->syncSpeakerRecord($speaker);
    config()->set('scout.driver', 'typesense');

    $this->app->bind(SpeakerSearchService::class, fn (): SpeakerSearchService => new class extends SpeakerSearchService
    {
        protected function shouldUseScoutSearch(): bool
        {
            return true;
        }

        protected function searchIdsWithScout(string $search, array $options = []): array
        {
            throw new RuntimeException('Typesense unavailable');
        }

        protected function logScoutFallback(string $message, Throwable $exception, string $search): void {}
    });

    $this->app->bind(InstitutionSearchService::class, fn (): InstitutionSearchService => new class extends InstitutionSearchService
    {
        protected function shouldUseScoutSearch(): bool
        {
            return true;
        }

        protected function searchIdsWithScout(string $search, array $options = []): array
        {
            throw new RuntimeException('Typesense unavailable');
        }

        protected function logScoutFallback(string $message, Throwable $exception, string $search): void {}
    });

    $response = $this->getJson('/api/v1/search?search='.urlencode('Nur Hikmah'))
        ->assertOk();

    expect(collect($response->json('data.speakers.items'))->pluck('id')->all())
        ->toContain((string) $speaker->id)
        ->and(collect($response->json('data.institutions.items'))->pluck('id')->all())
        ->toContain((string) $institution->id);
});

it('falls back to local speaker directory search when typesense fails', function () {
    $speaker = Speaker::factory()->create([
        'name' => 'Aisyah Binti Hassan',
        'honorific' => null,
        'pre_nominal' => ['syeikhul_maqari'],
        'post_nominal' => [],
        'qualifications' => [],
        'status' => 'verified',
        'is_active' => true,
    ]);

    app(SpeakerSearchService::class)->syncSpeakerRecord($speaker);
    config()->set('scout.driver', 'typesense');

    $this->app->bind(SpeakerSearchService::class, fn (): SpeakerSearchService => new class extends SpeakerSearchService
    {
        protected function shouldUseScoutSearch(): bool
        {
            return true;
        }

        protected function searchIdsWithScout(string $search, array $options = []): array
        {
            throw new RuntimeException('Typesense unavailable');
        }

        protected function logScoutFallback(string $message, Throwable $exception, string $search): void {}
    });

    $this->getJson('/api/v1/speakers?search='.urlencode('syeikhul maqari'))
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $speaker->id);
});

it('falls back to database institution directory search when typesense fails', function () {
    $institution = Institution::factory()->create([
        'name' => 'Masjid Sultan Salahuddin Abdul Aziz Shah',
        'nickname' => 'Masjid Biru',
        'status' => 'verified',
        'is_active' => true,
    ]);

    config()->set('scout.driver', 'typesense');

    $this->app->bind(InstitutionSearchService::class, fn (): InstitutionSearchService => new class extends InstitutionSearchService
    {
        protected function shouldUseScoutSearch(): bool
        {
            return true;
        }

        protected function searchIdsWithScout(string $search, array $options = []): array
        {
            throw new RuntimeException('Typesense unavailable');
        }

        protected function logScoutFallback(string $message, Throwable $exception, string $search): void {}
    });

    $this->getJson('/api/v1/institutions?search='.urlencode('Masjid Biru'))
        ->assertOk()
        ->assertJsonPath('data.0.id', (string) $institution->id);
});

it('serializes event list payloads with card image metadata for mobile clients', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $event = Event::factory()->for($institution)->create([
        'title' => 'Poster API Event',
        'status' => 'approved',
        'visibility' => 'public',
        'is_active' => true,
        'starts_at' => now()->addDays(2),
    ]);

    $event->addMedia(UploadedFile::fake()->image('event-poster.jpg', 1200, 1600))
        ->toMediaCollection('poster');

    $item = Closure::bind(
        fn (): array => $this->eventListData($event->fresh(['institution.media', 'venue', 'speakers.media', 'keyPeople.speaker'])),
        app(SearchController::class),
        SearchController::class,
    )();

    expect(data_get($item, 'has_poster'))->toBeTrue()
        ->and(data_get($item, 'card_image_url'))->toBeString()->not->toBe('');
});

it('returns card image metadata on public events index responses', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $posterEvent = Event::factory()->for($institution)->create([
        'title' => 'Home Poster Event',
        'status' => 'approved',
        'visibility' => 'public',
        'is_active' => true,
        'starts_at' => now()->addDay(),
    ]);

    $posterEvent->addMedia(UploadedFile::fake()->image('home-poster.jpg', 1200, 1600))
        ->toMediaCollection('poster');

    $placeholderEvent = Event::factory()->create([
        'title' => 'Home Placeholder Event',
        'status' => 'approved',
        'visibility' => 'public',
        'is_active' => true,
        'starts_at' => now()->addDays(2),
    ]);

    $posterResponse = $this->getJson('/api/v1/events?include=institution,venue,speakers&filter[search]=Home%20Poster%20Event&per_page=1')
        ->assertOk();
    $placeholderResponse = $this->getJson('/api/v1/events?include=institution,venue,speakers&filter[search]=Home%20Placeholder%20Event&per_page=1')
        ->assertOk();

    expect($posterResponse->json('data.0.has_poster'))->toBeTrue()
        ->and($posterResponse->json('data.0.card_image_url'))->toBeString()->not->toBe('')
        ->and($posterResponse->json('data.0.poster_url'))->toBeString()->not->toBe('')
        ->and($placeholderResponse->json('data.0.has_poster'))->toBeFalse()
        ->and($placeholderResponse->json('data.0.card_image_url'))->toContain('images/placeholders/event.png')
        ->and($placeholderResponse->json('data.0.poster_url'))->toBeNull();
});

it('serializes institution directory payloads with card media aliases for mobile clients', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');
    $malaysiaId = ensureFrontendApiMalaysiaCountryExists();
    $user = User::factory()->create();

    $institution = Institution::factory()->create([
        'name' => 'Masjid API Directory',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $institution->address()->create([
        'country_id' => $malaysiaId,
    ]);

    $institution->addMedia(UploadedFile::fake()->image('directory-logo.jpg', 640, 640))
        ->toMediaCollection('logo');

    $institution->addMedia(UploadedFile::fake()->image('directory-cover.jpg', 1600, 900))
        ->toMediaCollection('cover');

    Event::factory()->for($institution)->create([
        'is_active' => true,
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(3),
    ]);

    $user->follow($institution);

    $directoryInstitution = $institution->fresh(['address.state', 'address.district', 'address.subdistrict', 'media']);

    expect($directoryInstitution)->not->toBeNull();

    $directoryInstitution?->loadCount(['events' => function (Builder $query): void {
        $query
            ->where('events.is_active', true)
            ->whereIn('events.status', Event::PUBLIC_STATUSES)
            ->where('events.visibility', EventVisibility::Public)
            ->where('events.event_structure', '!=', EventStructure::ParentProgram->value);
    }]);

    $item = Closure::bind(
        fn (): array => $this->institutionListData($directoryInstitution, $user),
        app(SearchController::class),
        SearchController::class,
    )();

    expect(data_get($item, 'display_name'))->toBe($institution->display_name)
        ->and(data_get($item, 'events_count'))->toBe(1)
        ->and(data_get($item, 'event_count'))->toBe(1)
        ->and(data_get($item, 'public_image_url'))->toBeString()->not->toBe('')
        ->and(data_get($item, 'logo_url'))->toBeString()->not->toBe('')
        ->and(data_get($item, 'cover_url'))->toBeString()->not->toBe('')
        ->and(data_get($item, 'image_url'))->toBeString()->not->toBe('')
        ->and(data_get($item, 'public_image_url'))->toBe(data_get($item, 'image_url'))
        ->and(data_get($item, 'image_url'))->toBe(data_get($item, 'cover_url'))
        ->and(data_get($item, 'image_url'))->not->toBe(data_get($item, 'logo_url'))
        ->and(data_get($item, 'is_following'))->toBeTrue()
        ->and(array_key_exists('location', $item))->toBeTrue()
        ->and(array_key_exists('location_text', $item))->toBeTrue();
});

it('returns authenticated follow state in the frontend institution api', function () {
    $user = User::factory()->create();

    $institution = Institution::factory()->create([
        'name' => 'Masjid Follow Institution',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $user->follow($institution);

    Sanctum::actingAs($user);

    $this->getJson(route('api.client.institutions.index', ['search' => 'Masjid Follow Institution']))
        ->assertOk()
        ->assertJsonPath('data.0.is_following', true);
});

it('returns the total followed institution count for the full institution query, not just the current page', function () {
    $user = User::factory()->create();

    $followedInstitutions = Institution::factory()->count(3)->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    Institution::factory()->create([
        'name' => 'Unfollowed Institution',
        'status' => 'verified',
        'is_active' => true,
    ]);

    foreach ($followedInstitutions as $institution) {
        $user->follow($institution);
    }

    Sanctum::actingAs($user);

    $this->getJson(route('api.client.institutions.index', ['per_page' => 1]))
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 4)
        ->assertJsonPath('meta.following.total', 3);
});

it('supports server-side filtering to only followed institutions in the frontend institution api', function () {
    $user = User::factory()->create();

    $followedInstitution = Institution::factory()->create([
        'name' => 'Followed Institution Only',
        'status' => 'verified',
        'is_active' => true,
    ]);

    Institution::factory()->create([
        'name' => 'Not Followed Institution',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $user->follow($followedInstitution);

    Sanctum::actingAs($user);

    $this->getJson(route('api.client.institutions.index', ['following' => 1]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', $followedInstitution->slug)
        ->assertJsonPath('data.0.is_following', true)
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('meta.following.total', 1);
});

it('returns enum-backed institution type filters and supports server-side type filtering in the frontend institution api', function () {
    $masjid = Institution::factory()->create([
        'name' => 'Masjid Type Filter Match',
        'status' => 'verified',
        'is_active' => true,
        'type' => InstitutionType::Masjid,
    ]);

    Institution::factory()->create([
        'name' => 'Surau Type Filter Miss',
        'status' => 'verified',
        'is_active' => true,
        'type' => InstitutionType::Surau,
    ]);

    $response = $this->getJson(route('api.client.institutions.index', ['type' => InstitutionType::Masjid->value]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', $masjid->slug)
        ->assertJsonPath('meta.pagination.total', 1);

    expect($response->json('meta.types'))->toBe(array_map(
        static fn (InstitutionType $type): array => [
            'value' => $type->value,
            'label' => $type->getLabel(),
        ],
        InstitutionType::cases(),
    ));
});

it('bumps the institution directory cache version when institution records change', function () {
    $institution = Institution::factory()->create([
        'name' => 'Institution Cache Version',
        'nickname' => 'ICV',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $initialVersion = $this->getJson(route('api.client.institutions.index'))
        ->assertOk()
        ->json('meta.cache.version');

    $institution->update([
        'nickname' => 'ICV Updated',
    ]);

    $updatedVersion = $this->getJson(route('api.client.institutions.index'))
        ->assertOk()
        ->json('meta.cache.version');

    expect($initialVersion)->toBeString()->not->toBe('')
        ->and($updatedVersion)->toBeString()->not->toBe('')
        ->and($updatedVersion)->not->toBe($initialVersion);
});

it('serves the institution directory cache metadata without requiring country timestamps', function () {
    ensureFrontendApiMalaysiaCountryExists();

    Institution::factory()->create([
        'name' => 'Institution Cache Metadata',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $response = $this->getJson(route('api.client.institutions.index'))
        ->assertOk();

    expect($response->json('meta.cache.version'))->toBeString()->not->toBe('');
});

it('serves the speaker directory cache metadata without requiring country timestamps', function () {
    ensureFrontendApiMalaysiaCountryExists();

    Speaker::factory()->create([
        'name' => 'Speaker Cache Metadata',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $response = $this->getJson(route('api.client.speakers.index'))
        ->assertOk();

    expect($response->json('meta.cache.version'))->toBeString()->not->toBe('');
});

it('bumps the institution directory cache version when institution media changes', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $institution = Institution::factory()->create([
        'name' => 'Institution Cache Media',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $initialVersion = $this->getJson(route('api.client.institutions.index'))
        ->assertOk()
        ->json('meta.cache.version');

    $institution->addMedia(UploadedFile::fake()->image('institution-cache-logo.jpg', 800, 800))
        ->toMediaCollection('logo');

    $updatedVersion = $this->getJson(route('api.client.institutions.index'))
        ->assertOk()
        ->json('meta.cache.version');

    expect($updatedVersion)->toBeString()->not->toBe('')
        ->and($updatedVersion)->not->toBe($initialVersion);
});

it('bumps the institution directory cache version when institution addresses change', function () {
    $malaysiaId = ensureFrontendApiMalaysiaCountryExists();

    $institution = Institution::factory()->create([
        'name' => 'Institution Cache Address',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $initialVersion = $this->getJson(route('api.client.institutions.index'))
        ->assertOk()
        ->json('meta.cache.version');

    $institution->address()->create([
        'country_id' => $malaysiaId,
        'line1' => 'Alamat Direktori Baharu',
    ]);

    $updatedVersion = $this->getJson(route('api.client.institutions.index'))
        ->assertOk()
        ->json('meta.cache.version');

    expect($updatedVersion)->toBeString()->not->toBe('')
        ->and($updatedVersion)->not->toBe($initialVersion);
});

it('bumps the institution directory cache version when public institution events change', function () {
    $institution = Institution::factory()->create([
        'name' => 'Institution Cache Event',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $initialVersion = $this->getJson(route('api.client.institutions.index'))
        ->assertOk()
        ->json('meta.cache.version');

    Event::factory()->for($institution)->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'event_structure' => EventStructure::Standalone,
        'is_active' => true,
    ]);

    $updatedVersion = $this->getJson(route('api.client.institutions.index'))
        ->assertOk()
        ->json('meta.cache.version');

    expect($updatedVersion)->toBeString()->not->toBe('')
        ->and($updatedVersion)->not->toBe($initialVersion);
});

it('keeps placeholder institution imagery when no real media exists', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');
    $malaysiaId = ensureFrontendApiMalaysiaCountryExists();

    $institution = Institution::factory()->create([
        'name' => 'Masjid Tanpa Media',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $institution->address()->create([
        'country_id' => $malaysiaId,
    ]);

    $directoryInstitution = $institution->fresh(['address.state', 'address.district', 'address.subdistrict', 'media']);

    expect($directoryInstitution)->not->toBeNull();

    $item = Closure::bind(
        fn (): array => $this->institutionListData($directoryInstitution),
        app(SearchController::class),
        SearchController::class,
    )();

    expect(data_get($item, 'cover_url'))->toBeNull()
        ->and(data_get($item, 'logo_url'))->toBeString()->toContain('/images/placeholders/institution.png')
        ->and(data_get($item, 'public_image_url'))->toBe(data_get($item, 'logo_url'))
        ->and(data_get($item, 'image_url'))->toBe(data_get($item, 'logo_url'));
});

it('exposes the institution public image url in the frontend institution detail payload', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $institution = Institution::factory()->create([
        'name' => 'Masjid Detail API',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $institution->addMedia(UploadedFile::fake()->image('detail-logo.jpg', 800, 800))
        ->toMediaCollection('logo');

    $response = $this->getJson(route('api.client.institutions.show', ['institutionKey' => $institution->slug]))
        ->assertOk();

    expect($response->json('data.institution.media.public_image_url'))->toBe($institution->public_image_url)
        ->and($response->json('data.institution.media.cover_url'))->toBeNull()
        ->and($response->json('data.institution.media.public_image_url'))->toBe($response->json('data.institution.media.logo_url'));
});

it('returns institution follow state on the authenticated detail route response', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'name' => 'Masjid Follow State API',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $user->follow($institution);

    Sanctum::actingAs($user);

    $this->getJson(route('api.client.institutions.show', ['institutionKey' => $institution->slug]))
        ->assertOk()
        ->assertJsonPath('data.institution.followers_count', 1)
        ->assertJsonPath('data.institution.is_following', true);
});

it('returns institution follow state on the detail route for bearer token requests', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'name' => 'Masjid Bearer Follow State API',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $user->follow($institution);

    $token = $user->createToken('mobile-app')->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.client.institutions.show', ['institutionKey' => $institution->slug]))
        ->assertOk()
        ->assertJsonPath('data.institution.followers_count', 1)
        ->assertJsonPath('data.institution.is_following', true);
});

it('serializes institution detail payloads with address and donation metadata for mobile clients', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $countryId = ensureFrontendApiMalaysiaCountryExists();
    $user = User::factory()->create();

    $state = State::query()->create([
        'country_id' => $countryId,
        'name' => 'Pahang Detail API',
        'country_code' => 'MY',
    ]);

    $district = District::query()->create([
        'country_id' => $countryId,
        'state_id' => (int) $state->id,
        'name' => 'Temerloh Detail API',
        'country_code' => 'MY',
    ]);

    $subdistrict = Subdistrict::query()->create([
        'country_id' => $countryId,
        'state_id' => (int) $state->id,
        'district_id' => (int) $district->id,
        'name' => 'Lanchang Detail API',
        'country_code' => 'MY',
    ]);

    $institution = Institution::factory()->create([
        'name' => 'Masjid Detail DTO',
        'status' => 'verified',
        'is_active' => true,
        'description' => 'Institution detail serializer coverage',
    ]);

    $institution->addMedia(UploadedFile::fake()->image('detail-dto-logo.jpg', 800, 800))
        ->toMediaCollection('logo');
    $institution->addMedia(UploadedFile::fake()->image('detail-dto-cover.jpg', 1600, 900))
        ->toMediaCollection('cover');

    $institution->address()->updateOrCreate([], [
        'country_id' => $countryId,
        'state_id' => (int) $state->id,
        'district_id' => (int) $district->id,
        'subdistrict_id' => (int) $subdistrict->id,
        'line1' => 'Jalan Masjid 1',
        'postcode' => '28000',
        'lat' => 3.4501,
        'lng' => 102.4194,
        'google_maps_url' => 'https://maps.google.com/?q=3.4501,102.4194',
        'waze_url' => 'https://waze.com/ul?ll=3.4501,102.4194',
    ]);

    $donationChannel = $institution->donationChannels()->create(DonationChannel::factory()->raw([
        'label' => 'Tabung Utama',
        'recipient' => 'Masjid Detail DTO',
        'method' => 'bank_account',
        'bank_name' => 'Maybank',
        'bank_code' => 'MBB',
        'account_number' => '1234567890',
        'status' => 'verified',
        'is_default' => true,
    ]));

    $donationChannel->addMedia(UploadedFile::fake()->image('institution-qr.png', 600, 600))
        ->toMediaCollection('qr');

    $user->follow($institution);

    $detailInstitution = $institution->fresh([
        'media',
        'address.state',
        'address.city',
        'address.district',
        'address.subdistrict',
        'address.country',
        'contacts',
        'socialMedia',
        'donationChannels.media',
    ]);

    expect($detailInstitution)->not->toBeNull();

    $item = Closure::bind(
        fn (): array => $this->institutionDetailData($detailInstitution, $user),
        app(SearchController::class),
        SearchController::class,
    )();

    expect(data_get($item, 'address.country_id'))->toBe($countryId)
        ->and(data_get($item, 'country.iso2'))->toBe('MY')
        ->and(data_get($item, 'country.key'))->toBe('malaysia')
        ->and(data_get($item, 'map_lat'))->toBe(3.4501)
        ->and(data_get($item, 'map_lng'))->toBe(102.4194)
        ->and(data_get($item, 'is_following'))->toBeTrue()
        ->and(data_get($item, 'speaker_count'))->toBe(0)
        ->and(data_get($item, 'media.public_image_url'))->toBeString()->not->toBe('')
        ->and(data_get($item, 'media.logo_url'))->toBeString()->not->toBe('')
        ->and(data_get($item, 'media.cover_url'))->toBeString()->not->toBe('')
        ->and(data_get($item, 'donation_channels'))->toHaveCount(1)
        ->and(data_get($item, 'donation_channels.0.label'))->toBe('Tabung Utama')
        ->and(data_get($item, 'donation_channels.0.method_display'))->toBe('Bank Account')
        ->and(data_get($item, 'donation_channels.0.is_default'))->toBeTrue()
        ->and(data_get($item, 'donation_channels.0.qr_url'))->toBeString()->not->toBe('')
        ->and(data_get($item, 'waze_url'))->toContain('waze.com');
});

it('exposes 7-item institution detail lists with address display lines and qr urls', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $malaysiaId = ensureFrontendApiMalaysiaCountryExists();

    $stateId = DB::table('states')->insertGetId([
        'country_id' => $malaysiaId,
        'country_code' => 'MY',
        'name' => 'Selangor',
    ]);
    $state = State::query()->findOrFail($stateId);
    $district = District::query()->create([
        'country_id' => $malaysiaId,
        'state_id' => (int) $state->id,
        'country_code' => 'MY',
        'name' => 'Petaling',
    ]);
    $subdistrict = Subdistrict::query()->create([
        'country_id' => $malaysiaId,
        'state_id' => (int) $state->id,
        'district_id' => (int) $district->id,
        'country_code' => 'MY',
        'name' => 'Shah Alam',
    ]);

    $institution = Institution::factory()->create([
        'name' => 'Masjid Detail Payload',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $institution->address()->update([
        'line1' => 'Persiaran Masjid',
        'line2' => 'Seksyen 14',
        'postcode' => '40000',
        'country_id' => $malaysiaId,
        'state_id' => (int) $state->id,
        'district_id' => (int) $district->id,
        'subdistrict_id' => (int) $subdistrict->id,
        'lat' => 3.0733,
        'lng' => 101.5185,
        'google_maps_url' => 'https://www.google.com/maps/search/?api=1&query=3.0733%2C101.5185',
        'waze_url' => 'https://www.waze.com/ul?ll=3.0733,101.5185&navigate=yes',
    ]);

    $channel = $institution->donationChannels()->create([
        'method' => 'bank_account',
        'bank_code' => 'BIMB',
        'bank_name' => 'Bank Islam',
        'account_number' => '123456789012',
        'recipient' => 'Tabung Masjid Detail',
        'label' => 'QR Infaq',
        'status' => 'verified',
    ]);

    $channel->addMedia(UploadedFile::fake()->image('qr.png', 300, 300))
        ->toMediaCollection('qr');

    foreach (range(1, 8) as $index) {
        Event::factory()->create([
            'institution_id' => $institution->id,
            'status' => 'approved',
            'visibility' => EventVisibility::Public->value,
            'is_active' => true,
            'published_at' => now()->subDay(),
            'starts_at' => now()->addDays($index),
        ]);
    }

    foreach (range(1, 8) as $index) {
        Event::factory()->create([
            'institution_id' => $institution->id,
            'status' => 'approved',
            'visibility' => EventVisibility::Public->value,
            'is_active' => true,
            'published_at' => now()->subDays(2),
            'starts_at' => now()->subDays($index),
        ]);
    }

    $response = $this->getJson(route('api.client.institutions.show', [
        'institutionKey' => $institution->slug,
        'upcoming_per_page' => 7,
        'past_per_page' => 7,
    ]))->assertOk();

    expect($response->json('data.institution.street_address_line'))->toBe('Persiaran Masjid, Seksyen 14')
        ->and($response->json('data.institution.locality_address_line'))->toBe('Shah Alam, 40000')
        ->and($response->json('data.institution.regional_address_line'))->toBe('Petaling, Selangor')
        ->and($response->json('data.institution.donation_channels.0.qr_url'))->toBeString()
        ->and($response->json('data.institution.donation_channels.0.qr_full_url'))->toBe($channel->getFirstMediaUrl('qr'))
        ->and(count($response->json('data.upcoming_events')))->toBe(7)
        ->and(count($response->json('data.past_events')))->toBe(7)
        ->and($response->json('data.upcoming_total'))->toBe(8)
        ->and($response->json('data.past_total'))->toBe(8);
});

it('rejects unsupported files on public contribution update suggestions', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
    ]);

    Sanctum::actingAs($user);

    $this->post(route('api.client.contributions.suggest.store', [
        'subjectType' => 'institusi',
        'subject' => $institution->slug,
    ]), [
        'cover' => UploadedFile::fake()->image('institution-cover.jpg'),
    ], [
        'Accept' => 'application/json',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['files']);
});

it('uses the inferred preferred country for submit-event contract defaults', function () {
    config()->set('public-countries.countries.singapore.enabled', true);

    $singaporeId = DB::table('countries')->insertGetId([
        'iso2' => 'SG',
        'name' => 'Singapore',
        'status' => 1,
        'phone_code' => '65',
        'iso3' => 'SGP',
        'region' => 'Asia',
        'subregion' => 'South-Eastern Asia',
    ]);

    app()->forgetInstance(PublicCountryRegistry::class);
    app()->forgetInstance(PublicCountryPreference::class);

    $submitEvent = $this->withHeader('X-Timezone', 'Asia/Singapore')
        ->getJson(route('api.client.forms.submit-event'))
        ->assertOk()
        ->json('data');

    $submitInstitution = $this->withHeader('X-Timezone', 'Asia/Singapore')
        ->getJson(route('api.client.forms.contributions.institutions'))
        ->assertOk()
        ->json('data');

    expect(data_get($submitEvent, 'defaults.submission_country_id'))->toBe($singaporeId)
        ->and(collect($submitEvent['fields'])->firstWhere('name', 'submission_country_id')['allowed_values'])->toContain($singaporeId)
        ->and(data_get($submitInstitution, 'defaults.address.country_id'))->toBe($singaporeId);
});

it('creates institution contribution requests through the frontend api', function () {
    $user = User::factory()->create();
    $countryId = ensureFrontendApiMalaysiaCountryExists();
    Sanctum::actingAs($user);

    $this->postJson(route('api.client.contributions.institutions.store'), [
        'type' => 'masjid',
        'name' => 'Masjid API',
        'nickname' => 'API',
        'description' => '<p>Institution description</p>',
        'address' => [
            'country_id' => $countryId,
            'google_maps_url' => 'https://maps.google.com/?q=masjid+api',
        ],
    ])
        ->assertCreated()
        ->assertJsonPath('message', __('Thank you. Your institution submission has been received. We will notify you if it is approved or rejected.'))
        ->assertJsonPath('data.institution.name', 'Masjid API');

    $institution = Institution::query()->where('name', 'Masjid API')->firstOrFail();

    expect($institution->status)->toBe('pending')
        ->and($institution->members()->whereKey($user->id)->exists())->toBeFalse()
        ->and(ContributionRequest::query()->where('entity_id', $institution->getKey())->exists())->toBeTrue();
});

it('creates speaker contribution requests through the frontend api with an explicit country alias', function () {
    $user = User::factory()->create();

    config()->set('public-countries.countries.singapore.enabled', true);

    $singaporeId = DB::table('countries')->insertGetId([
        'iso2' => 'SG',
        'name' => 'Singapore',
        'status' => 1,
        'phone_code' => '65',
        'iso3' => 'SGP',
        'region' => 'Asia',
        'subregion' => 'South-Eastern Asia',
    ]);

    app()->forgetInstance(PublicCountryRegistry::class);
    app()->forgetInstance(PublicCountryPreference::class);

    Sanctum::actingAs($user);

    $this->withHeader('X-Timezone', 'Asia/Singapore')
        ->postJson(route('api.client.contributions.speakers.store'), [
            'name' => 'Frontend API Scoped Country Speaker',
            'gender' => 'male',
            'address' => [
                'country_code' => 'SG',
                'state_id' => null,
            ],
        ])->assertCreated()
        ->assertJsonPath('data.speaker.name', 'Frontend API Scoped Country Speaker');

    $speaker = Speaker::query()
        ->with('address')
        ->where('name', 'Frontend API Scoped Country Speaker')
        ->firstOrFail();

    expect($speaker->addressModel?->country_id)->toBe($singaporeId)
        ->and($speaker->slug)->toEndWith('-sg')
        ->and(ContributionRequest::query()->where('entity_id', $speaker->getKey())->exists())->toBeTrue();
});

it('requires explicit country and still prohibits detailed address fields when creating speakers through the frontend api', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson(route('api.client.contributions.speakers.store'), [
        'name' => 'Frontend API Missing Speaker Country',
        'gender' => 'male',
        'address' => [
            'state_id' => null,
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['address.country_id']);

    $this->postJson(route('api.client.contributions.speakers.store'), [
        'name' => 'Frontend API Invalid Speaker Address',
        'gender' => 'male',
        'address' => [
            'country_id' => 132,
            'line1' => 'Alamat Lama',
            'google_maps_url' => 'https://maps.google.com/?q=1,1',
        ],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'address.line1',
            'address.google_maps_url',
        ]);
});

it('returns profile-quality speaker avatar urls from the frontend search api', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $speaker = Speaker::factory()->create([
        'name' => 'Kazim Elias',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $speaker->addMedia(UploadedFile::fake()->image('kazim.jpg', 1200, 1200))
        ->toMediaCollection('avatar');

    $this->getJson(route('api.client.speakers.index', ['search' => 'kazim']))
        ->assertOk()
        ->assertJsonPath('data.0.avatar_url', $speaker->public_avatar_url);
});

it('exposes explicit country data and country filters on frontend institution and speaker reads', function () {
    $countryId = ensureFrontendApiMalaysiaCountryExists();

    $institution = Institution::factory()->create([
        'name' => 'Country Filter Institution',
        'status' => 'verified',
        'is_active' => true,
    ]);
    $institution->address()->update([
        'country_id' => $countryId,
    ]);

    $speaker = Speaker::factory()->create([
        'name' => 'Country Filter Speaker',
        'status' => 'verified',
        'is_active' => true,
    ]);
    $speaker->address()->update([
        'country_id' => $countryId,
    ]);

    $this->getJson(route('api.client.institutions.index', ['country_code' => 'MY', 'search' => 'Country Filter Institution']))
        ->assertOk()
        ->assertJsonPath('data.0.country.iso2', 'MY')
        ->assertJsonPath('data.0.country.key', 'malaysia');

    $this->getJson(route('api.client.speakers.index', ['country_key' => 'malaysia', 'search' => 'Country Filter Speaker']))
        ->assertOk()
        ->assertJsonPath('data.0.country.iso2', 'MY')
        ->assertJsonPath('data.0.country.key', 'malaysia');
});

it('returns authenticated follow state in the frontend speaker api', function () {
    $user = User::factory()->create();

    $speaker = Speaker::factory()->create([
        'name' => 'Kazim Follow Speaker',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $user->follow($speaker);

    Sanctum::actingAs($user);

    $this->getJson(route('api.client.speakers.index', ['search' => 'Kazim Follow Speaker']))
        ->assertOk()
        ->assertJsonPath('data.0.is_following', true);
});

it('returns the total followed speaker count for the full speaker query, not just the current page', function () {
    $user = User::factory()->create();

    $followedSpeakers = Speaker::factory()->count(3)->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $unfollowedSpeaker = Speaker::factory()->create([
        'name' => 'Unfollowed Speaker',
        'status' => 'verified',
        'is_active' => true,
    ]);

    foreach ($followedSpeakers as $speaker) {
        $user->follow($speaker);
    }

    Sanctum::actingAs($user);

    $this->getJson(route('api.client.speakers.index', ['per_page' => 1]))
        ->assertOk()
        ->assertJsonPath('meta.pagination.total', 4)
        ->assertJsonPath('meta.following.total', 3);
});

it('supports server-side filtering to only followed speakers in the frontend speaker api', function () {
    $user = User::factory()->create();

    $followedSpeaker = Speaker::factory()->create([
        'name' => 'Followed Speaker Only',
        'status' => 'verified',
        'is_active' => true,
    ]);

    Speaker::factory()->create([
        'name' => 'Not Followed Speaker',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $user->follow($followedSpeaker);

    Sanctum::actingAs($user);

    $this->getJson(route('api.client.speakers.index', ['following' => 1]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.slug', $followedSpeaker->slug)
        ->assertJsonPath('data.0.is_following', true)
        ->assertJsonPath('meta.pagination.total', 1)
        ->assertJsonPath('meta.following.total', 1);
});

it('serializes speaker directory payloads with country and follow metadata for mobile clients', function () {
    $countryId = ensureFrontendApiMalaysiaCountryExists();
    $user = User::factory()->create();

    $speaker = Speaker::factory()->create([
        'name' => 'Speaker Directory DTO',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $speaker->address()->updateOrCreate([], [
        'country_id' => $countryId,
    ]);

    $user->follow($speaker);

    $directorySpeaker = $speaker->fresh();

    expect($directorySpeaker)->not->toBeNull();

    $item = Closure::bind(
        fn (): array => $this->speakerListData($directorySpeaker, $user),
        app(SearchController::class),
        SearchController::class,
    )();

    expect(data_get($item, 'formatted_name'))->toBe($speaker->formatted_name)
        ->and(data_get($item, 'avatar_url'))->toBeString()->not->toBe('')
        ->and(data_get($item, 'country.id'))->toBe($countryId)
        ->and(data_get($item, 'country.key'))->toBe('malaysia')
        ->and(data_get($item, 'is_following'))->toBeTrue();
});

it('bumps the speaker directory cache version when speaker records change', function () {
    $speaker = Speaker::factory()->create([
        'name' => 'Speaker Cache Version',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $initialVersion = $this->getJson(route('api.client.speakers.index'))
        ->assertOk()
        ->json('meta.cache.version');

    $speaker->update([
        'job_title' => 'Pensyarah Kanan',
    ]);

    $updatedVersion = $this->getJson(route('api.client.speakers.index'))
        ->assertOk()
        ->json('meta.cache.version');

    expect($initialVersion)->toBeString()->not->toBe('')
        ->and($updatedVersion)->toBeString()->not->toBe('')
        ->and($updatedVersion)->not->toBe($initialVersion);
});

it('bumps the speaker directory cache version when speaker media changes', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $speaker = Speaker::factory()->create([
        'name' => 'Speaker Cache Media',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $initialVersion = $this->getJson(route('api.client.speakers.index'))
        ->assertOk()
        ->json('meta.cache.version');

    $speaker->addMedia(UploadedFile::fake()->image('speaker-cache-media.jpg', 1200, 1200))
        ->toMediaCollection('avatar');

    $updatedVersion = $this->getJson(route('api.client.speakers.index'))
        ->assertOk()
        ->json('meta.cache.version');

    expect($updatedVersion)->toBeString()->not->toBe('')
        ->and($updatedVersion)->not->toBe($initialVersion);
});

it('bumps the speaker directory cache version when speaker addresses change', function () {
    $countryId = ensureFrontendApiMalaysiaCountryExists();

    $speaker = Speaker::factory()->create([
        'name' => 'Speaker Cache Address',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $initialVersion = $this->getJson(route('api.client.speakers.index'))
        ->assertOk()
        ->json('meta.cache.version');

    $speaker->address()->create([
        'country_id' => $countryId,
        'state_id' => null,
        'district_id' => null,
        'subdistrict_id' => null,
    ]);

    $updatedVersion = $this->getJson(route('api.client.speakers.index'))
        ->assertOk()
        ->json('meta.cache.version');

    expect($updatedVersion)->toBeString()->not->toBe('')
        ->and($updatedVersion)->not->toBe($initialVersion);
});

it('bumps public directory cache versions when country metadata changes', function () {
    $countryId = ensureFrontendApiMalaysiaCountryExists();

    $institution = Institution::factory()->create([
        'name' => 'Institution Cache Country Metadata',
        'status' => 'verified',
        'is_active' => true,
    ]);
    $institution->address()->updateOrCreate([], ['country_id' => $countryId]);

    $speaker = Speaker::factory()->create([
        'name' => 'Speaker Cache Country Metadata',
        'status' => 'verified',
        'is_active' => true,
    ]);
    $speaker->address()->updateOrCreate([], ['country_id' => $countryId]);

    $initialInstitutionVersion = $this->getJson(route('api.client.institutions.index'))
        ->assertOk()
        ->json('meta.cache.version');
    $initialSpeakerVersion = $this->getJson(route('api.client.speakers.index'))
        ->assertOk()
        ->json('meta.cache.version');

    Country::query()->whereKey($countryId)->update([
        'name' => 'Malaysia Baru',
    ]);

    $updatedInstitutionVersion = $this->getJson(route('api.client.institutions.index'))
        ->assertOk()
        ->json('meta.cache.version');
    $updatedSpeakerVersion = $this->getJson(route('api.client.speakers.index'))
        ->assertOk()
        ->json('meta.cache.version');

    expect($updatedInstitutionVersion)->toBeString()->not->toBe('')
        ->and($updatedInstitutionVersion)->not->toBe($initialInstitutionVersion)
        ->and($updatedSpeakerVersion)->toBeString()->not->toBe('')
        ->and($updatedSpeakerVersion)->not->toBe($initialSpeakerVersion);
});

it('bumps the speaker directory cache version when speaker event participation changes', function () {
    $speaker = Speaker::factory()->create([
        'name' => 'Speaker Cache Event',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $initialVersion = $this->getJson(route('api.client.speakers.index'))
        ->assertOk()
        ->json('meta.cache.version');

    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'event_structure' => EventStructure::Standalone,
        'is_active' => true,
        'starts_at' => now()->addDays(3)->setTime(19, 0),
    ]);

    EventKeyPerson::factory()
        ->for($event, 'event')
        ->for($speaker, 'speaker')
        ->create();

    $updatedVersion = $this->getJson(route('api.client.speakers.index'))
        ->assertOk()
        ->json('meta.cache.version');

    expect($updatedVersion)->toBeString()->not->toBe('')
        ->and($updatedVersion)->not->toBe($initialVersion);
});

it('uses the same stable public directory ordering in the frontend speaker api', function () {
    $firstSpeaker = Speaker::factory()->create([
        'name' => 'Adam Speaker API',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $secondSpeaker = Speaker::factory()->create([
        'name' => 'Zaid Speaker API',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $response = $this->getJson(route('api.client.speakers.index', [
        'search' => 'Speaker API',
        'per_page' => 12,
    ]))->assertOk();

    $expectedOrder = [$firstSpeaker->id, $secondSpeaker->id];

    usort($expectedOrder, static function (string $left, string $right): int {
        $leftParts = Speaker::publicDirectorySortParts($left);
        $rightParts = Speaker::publicDirectorySortParts($right);

        $primaryComparison = $leftParts['primary'] <=> $rightParts['primary'];

        if ($primaryComparison !== 0) {
            return $primaryComparison;
        }

        return $leftParts['secondary'] <=> $rightParts['secondary'];
    });

    expect(collect($response->json('data'))
        ->pluck('id')
        ->intersect([$firstSpeaker->id, $secondSpeaker->id])
        ->values()
        ->all())->toBe($expectedOrder);
});

it('uses an explicit mobile directory seed for speaker ordering', function () {
    $seedPairs = collect(['mobile-seed-a', 'mobile-seed-b', 'mobile-seed-c', 'mobile-seed-d'])
        ->crossJoin(['mobile-seed-a', 'mobile-seed-b', 'mobile-seed-c', 'mobile-seed-d'])
        ->first(function (array $pair): bool {
            return $pair[0] !== $pair[1]
                && Speaker::publicDirectoryOrderOffset($pair[0]) !== Speaker::publicDirectoryOrderOffset($pair[1]);
        });

    expect($seedPairs)->not->toBeNull();

    [$firstSeed, $secondSeed] = $seedPairs;

    $firstOffset = Speaker::publicDirectoryOrderOffset($firstSeed);
    $secondOffset = Speaker::publicDirectoryOrderOffset($secondSeed);

    $speakerId = static function (string $firstChar, string $secondChar) use ($firstOffset, $secondOffset): string {
        $characters = array_fill(0, 32, '0');
        $characters[$firstOffset - 1] = $firstChar;
        $characters[$secondOffset - 1] = $secondChar;
        $normalized = implode('', $characters);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($normalized, 0, 8),
            substr($normalized, 8, 4),
            substr($normalized, 12, 4),
            substr($normalized, 16, 4),
            substr($normalized, 20, 12),
        );
    };

    $speakers = collect([
        ['id' => $speakerId('f', '0'), 'name' => 'Speaker Seed A'],
        ['id' => $speakerId('0', 'f'), 'name' => 'Speaker Seed B'],
        ['id' => $speakerId('8', '1'), 'name' => 'Speaker Seed C'],
        ['id' => $speakerId('1', '8'), 'name' => 'Speaker Seed D'],
    ])->map(fn (array $attributes) => Speaker::factory()->create([
        ...$attributes,
        'status' => 'verified',
        'is_active' => true,
    ]));

    $firstResponse = $this->getJson(route('api.client.speakers.index', [
        'per_page' => 12,
        'directory_seed' => $firstSeed,
    ]))->assertOk();

    $secondResponse = $this->getJson(route('api.client.speakers.index', [
        'per_page' => 12,
        'directory_seed' => $secondSeed,
    ]))->assertOk();

    $speakerIds = $speakers->pluck('id')->all();
    $expectedFirstOrder = $speakerIds;
    $expectedSecondOrder = $speakerIds;

    usort($expectedFirstOrder, static function (string $left, string $right) use ($firstSeed): int {
        $leftParts = Speaker::publicDirectorySortParts($left, $firstSeed);
        $rightParts = Speaker::publicDirectorySortParts($right, $firstSeed);

        $primaryComparison = $leftParts['primary'] <=> $rightParts['primary'];

        if ($primaryComparison !== 0) {
            return $primaryComparison;
        }

        return $leftParts['secondary'] <=> $rightParts['secondary'];
    });

    usort($expectedSecondOrder, static function (string $left, string $right) use ($secondSeed): int {
        $leftParts = Speaker::publicDirectorySortParts($left, $secondSeed);
        $rightParts = Speaker::publicDirectorySortParts($right, $secondSeed);

        $primaryComparison = $leftParts['primary'] <=> $rightParts['primary'];

        if ($primaryComparison !== 0) {
            return $primaryComparison;
        }

        return $leftParts['secondary'] <=> $rightParts['secondary'];
    });

    expect(collect($firstResponse->json('data'))->pluck('id')->intersect($speakerIds)->values()->all())
        ->toBe($expectedFirstOrder)
        ->and(collect($secondResponse->json('data'))->pluck('id')->intersect($speakerIds)->values()->all())
        ->toBe($expectedSecondOrder)
        ->and($expectedFirstOrder)->not->toBe($expectedSecondOrder);
});

it('returns random inspiration payloads with category and media metadata for mobile clients', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    Inspiration::factory()->category(InspirationCategory::QuranQuote)->create([
        'locale' => 'en',
        'title' => 'English Inspiration',
        'content' => 'English content should be filtered out.',
    ]);

    $inspiration = Inspiration::factory()->category(InspirationCategory::IslamicComic)->create([
        'locale' => 'ms',
        'title' => 'Komik API',
        'content' => 'Renungan komik untuk klien mudah alih.',
        'source' => 'Sirah API',
        'is_active' => true,
    ]);

    $inspiration->addMedia(UploadedFile::fake()->image('inspiration.jpg', 1200, 900))
        ->toMediaCollection('main');

    $response = $this->getJson(route('api.client.inspirations.random', ['locale' => 'ms']))
        ->assertOk();

    expect($response->json('data.id'))->toBe($inspiration->id)
        ->and($response->json('data.title'))->toBe('Komik API')
        ->and($response->json('data.content'))->toContain('Renungan komik untuk klien mudah alih')
        ->and($response->json('data.content_html'))->toContain('Renungan komik untuk klien mudah alih')
        ->and($response->json('data.preview_text'))->not->toBe('')
        ->and($response->json('data.source'))->toBe('Sirah API')
        ->and($response->json('data.category.value'))->toBe(InspirationCategory::IslamicComic->value)
        ->and($response->json('data.category.label'))->toBe(InspirationCategory::IslamicComic->label())
        ->and($response->json('data.category.color'))->toBe(InspirationCategory::IslamicComic->color())
        ->and($response->json('data.category.is_comic'))->toBeTrue()
        ->and($response->json('data.media.has_media'))->toBeTrue()
        ->and($response->json('data.media.thumb_url'))->not->toBe('')
        ->and($response->json('data.media.full_url'))->not->toBe('')
        ->and($response->json('meta.locale'))->toBe('ms');
});

it('submits and cancels membership claims through the frontend api', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    $storeResponse = $this->post(route('api.client.membership-claims.store', [
        'subjectType' => 'institusi',
        'subject' => $institution->slug,
    ]), [
        'justification' => 'I manage this institution.',
        'evidence' => [UploadedFile::fake()->image('evidence.jpg')],
    ], [
        'Accept' => 'application/json',
    ])->assertCreated();

    $claimId = $storeResponse->json('data.claim.id');

    expect(MembershipClaim::query()->whereKey($claimId)->exists())->toBeTrue();

    $this->deleteJson(route('api.client.membership-claims.cancel', ['claimId' => $claimId]))
        ->assertOk()
        ->assertJsonPath('data.claim.status', 'cancelled');
});

it('enforces institution workspace member management permissions', function () {
    $institution = Institution::factory()->create();
    $admin = User::factory()->create();
    $viewer = User::factory()->create();
    $newMember = User::factory()->create();

    app(AddMemberToSubject::class)->handle($institution, $admin, 'admin');
    app(AddMemberToSubject::class)->handle($institution, $viewer, 'viewer');

    Sanctum::actingAs($viewer);

    $this->postJson(route('api.client.institution-workspace.members.store', ['institutionId' => $institution->getKey()]), [
        'email' => $newMember->email,
        'role_id' => 'viewer',
    ])->assertForbidden();

    Sanctum::actingAs($admin);

    $this->postJson(route('api.client.institution-workspace.members.store', ['institutionId' => $institution->getKey()]), [
        'email' => $newMember->email,
        'role_id' => 'viewer',
    ])
        ->assertCreated()
        ->assertJsonPath('data.member.email', $newMember->email);

    expect($institution->members()->whereKey($newMember->getKey())->exists())->toBeTrue();
});

it('forbids institution member management over bearer tokens for viewers even with token abilities', function () {
    $institution = Institution::factory()->create();
    $viewer = User::factory()->create();
    $newMember = User::factory()->create();

    app(AddMemberToSubject::class)->handle($institution, $viewer, 'viewer');

    $viewerToken = $viewer->createToken('viewer-device', ['institution.manage-members'])->plainTextToken;

    $this->withToken($viewerToken)
        ->postJson(route('api.client.institution-workspace.members.store', ['institutionId' => $institution->getKey()]), [
            'email' => $newMember->email,
            'role_id' => 'viewer',
        ])->assertForbidden();
});

it('allows institution member management over bearer tokens for admins without token abilities', function () {
    $institution = Institution::factory()->create();
    $admin = User::factory()->create();
    $newMember = User::factory()->create();

    app(AddMemberToSubject::class)->handle($institution, $admin, 'admin');

    $adminToken = $admin->createToken('admin-device', [])->plainTextToken;

    $this->withToken($adminToken)
        ->postJson(route('api.client.institution-workspace.members.store', ['institutionId' => $institution->getKey()]), [
            'email' => $newMember->email,
            'role_id' => 'viewer',
        ])
        ->assertCreated()
        ->assertJsonPath('data.member.email', $newMember->email);

    expect($institution->members()->whereKey($newMember->getKey())->exists())->toBeTrue();
});

it('submits events with media through the frontend api', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
        'allow_public_event_submission' => true,
    ]);
    $domainTag = Tag::factory()->domain()->create();
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
        'allow_public_event_submission' => true,
    ]);

    Sanctum::actingAs($user);

    $response = $this->post(route('api.client.submit-event.store'), [
        'title' => 'Frontend API Event',
        'description' => 'API description',
        'event_type' => ['kuliah_ceramah'],
        'event_date' => now()->addDay()->toDateString(),
        'prayer_time' => 'selepas_maghrib',
        'event_format' => 'physical',
        'visibility' => 'public',
        'gender' => 'all',
        'age_group' => ['all_ages'],
        'languages' => [101],
        'domain_tags' => [$domainTag->getKey()],
        'organizer_type' => 'institution',
        'organizer_institution_id' => $institution->getKey(),
        'speakers' => [$speaker->getKey()],
        'submission_country_id' => 132,
        'poster' => UploadedFile::fake()->image('poster.jpg'),
        'gallery' => [UploadedFile::fake()->image('gallery.jpg')],
    ], [
        'Accept' => 'application/json',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('data.event.title', 'Frontend API Event');

    $event = Event::query()->where('title', 'Frontend API Event')->firstOrFail();

    expect($event->getMedia('poster'))->toHaveCount(1)
        ->and($event->getMedia('gallery'))->toHaveCount(1);
});

it('requires explicit country input for frontend event submissions and accepts aliases', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
        'allow_public_event_submission' => true,
    ]);
    $domainTag = Tag::factory()->domain()->create();
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
        'allow_public_event_submission' => true,
    ]);

    Sanctum::actingAs($user);

    $payload = [
        'title' => 'Frontend API Legacy Timezone Event',
        'description' => 'API description',
        'event_type' => ['kuliah_ceramah'],
        'event_date' => now()->addDay()->toDateString(),
        'prayer_time' => 'lain_waktu',
        'custom_time' => '20:15',
        'event_format' => 'physical',
        'visibility' => 'public',
        'gender' => 'all',
        'age_group' => ['all_ages'],
        'languages' => [101],
        'domain_tags' => [$domainTag->getKey()],
        'organizer_type' => 'institution',
        'organizer_institution_id' => $institution->getKey(),
        'speakers' => [$speaker->getKey()],
    ];

    $this->postJson(route('api.client.submit-event.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['submission_country_id']);

    $this->postJson(route('api.client.submit-event.store'), array_merge($payload, [
        'submission_country_key' => 'malaysia',
    ]))
        ->assertCreated()
        ->assertJsonPath('data.event.title', 'Frontend API Legacy Timezone Event');

    $event = Event::query()->where('title', 'Frontend API Legacy Timezone Event')->firstOrFail();

    expect($event->timezone)->toBe('Asia/Kuala_Lumpur')
        ->and($event->starts_at?->timezone('Asia/Kuala_Lumpur')->format('H:i'))->toBe('20:15');
});

it('rejects unsupported or disabled submission countries for frontend event submissions', function () {
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
        'allow_public_event_submission' => true,
    ]);
    $domainTag = Tag::factory()->domain()->create();
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
        'allow_public_event_submission' => true,
    ]);

    $disabledCountryId = DB::table('countries')->insertGetId([
        'iso2' => 'SG',
        'name' => 'Singapore',
        'status' => 1,
        'phone_code' => '65',
        'iso3' => 'SGP',
        'region' => 'Asia',
        'subregion' => 'South-Eastern Asia',
    ]);

    $payload = [
        'title' => 'Frontend API Invalid Country Event',
        'description' => 'API description',
        'event_type' => ['kuliah_ceramah'],
        'event_date' => now()->addDay()->toDateString(),
        'prayer_time' => 'selepas_maghrib',
        'event_format' => 'physical',
        'visibility' => 'public',
        'gender' => 'all',
        'age_group' => ['all_ages'],
        'languages' => [101],
        'domain_tags' => [$domainTag->getKey()],
        'organizer_type' => 'institution',
        'organizer_institution_id' => $institution->getKey(),
        'speakers' => [$speaker->getKey()],
        'submitter_name' => 'Guest Submitter',
        'submitter_email' => 'guest@example.test',
    ];

    $this->postJson(route('api.client.submit-event.store'), array_merge($payload, [
        'submission_country_id' => 999999,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['submission_country_id']);

    $this->postJson(route('api.client.submit-event.store'), array_merge($payload, [
        'submission_country_id' => $disabledCountryId,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['submission_country_id']);
});

it('requires guest event submissions to include email or phone', function () {
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
        'allow_public_event_submission' => true,
    ]);
    $domainTag = Tag::factory()->domain()->create();
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
        'allow_public_event_submission' => true,
    ]);

    $this->postJson(route('api.client.submit-event.store'), [
        'title' => 'Guest Frontend API Event',
        'description' => 'API description',
        'event_type' => ['kuliah_ceramah'],
        'event_date' => now()->addDay()->toDateString(),
        'prayer_time' => 'selepas_maghrib',
        'event_format' => 'physical',
        'visibility' => 'public',
        'gender' => 'all',
        'age_group' => ['all_ages'],
        'languages' => [101],
        'domain_tags' => [$domainTag->getKey()],
        'organizer_type' => 'institution',
        'organizer_institution_id' => $institution->getKey(),
        'speakers' => [$speaker->getKey()],
        'submission_country_id' => 132,
        'submitter_name' => 'Guest Submitter',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['submitter_email', 'submitter_phone']);
});

it('allows online frontend event submissions without a live url', function () {
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
        'allow_public_event_submission' => true,
    ]);
    $domainTag = Tag::factory()->domain()->create();

    $this->postJson(route('api.client.submit-event.store'), [
        'title' => 'Online Frontend API Event',
        'description' => 'API description',
        'event_type' => ['kuliah_ceramah'],
        'event_date' => now()->addDay()->toDateString(),
        'prayer_time' => 'selepas_maghrib',
        'event_format' => 'online',
        'visibility' => 'public',
        'gender' => 'all',
        'age_group' => ['all_ages'],
        'languages' => [101],
        'domain_tags' => [$domainTag->getKey()],
        'organizer_type' => 'institution',
        'organizer_institution_id' => $institution->getKey(),
        'submission_country_id' => 132,
        'submitter_name' => 'Guest Submitter',
        'submitter_email' => 'guest@example.test',
    ])
        ->assertCreated()
        ->assertJsonPath('data.event.title', 'Online Frontend API Event');

    expect(Event::query()->where('title', 'Online Frontend API Event')->value('live_url'))->toBeNull();
});

it('requires a physical location for speaker-organized physical event submissions', function () {
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
        'allow_public_event_submission' => true,
    ]);
    $domainTag = Tag::factory()->domain()->create();

    $this->postJson(route('api.client.submit-event.store'), [
        'title' => 'Speaker Physical Event',
        'description' => 'API description',
        'event_type' => ['kuliah_ceramah'],
        'event_date' => now()->addDay()->toDateString(),
        'prayer_time' => 'selepas_maghrib',
        'event_format' => 'physical',
        'visibility' => 'public',
        'gender' => 'all',
        'age_group' => ['all_ages'],
        'languages' => [101],
        'domain_tags' => [$domainTag->getKey()],
        'organizer_type' => 'speaker',
        'organizer_speaker_id' => $speaker->getKey(),
        'submission_country_id' => 132,
        'submitter_name' => 'Guest Submitter',
        'submitter_email' => 'guest@example.test',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['location_type']);
});

it('mirrors public detail media and public contact payloads', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $speaker->contacts()->create([
        'category' => ContactCategory::Email->value,
        'value' => 'public-speaker@example.test',
        'is_public' => true,
    ]);
    $speaker->contacts()->create([
        'category' => ContactCategory::Phone->value,
        'value' => '+6011222333',
        'is_public' => false,
    ]);
    $speaker->socialMedia()->create([
        'platform' => 'website',
        'url' => 'https://speaker.example.test',
    ]);
    $speaker->addMedia(UploadedFile::fake()->image('speaker-cover.jpg'))->toMediaCollection('cover');

    $venue = Venue::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $venue->contacts()->create([
        'category' => ContactCategory::Phone->value,
        'value' => '+60312345678',
        'is_public' => true,
    ]);
    $venue->addMedia(UploadedFile::fake()->image('venue-cover.jpg'))->toMediaCollection('cover');

    $reference = Reference::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $reference->socialMedia()->create([
        'platform' => 'website',
        'url' => 'https://reference.example.test',
    ]);
    $reference->addMedia(UploadedFile::fake()->image('reference-front-cover.jpg'))->toMediaCollection('front_cover');

    $speakerResponse = $this->getJson(route('api.client.speakers.show', ['speakerKey' => $speaker->slug]))
        ->assertOk();
    $venueResponse = $this->getJson(route('api.client.venues.show', ['venueKey' => $venue->slug]))
        ->assertOk();
    $referenceResponse = $this->getJson(route('api.client.references.show', ['referenceKey' => $reference->slug]))
        ->assertOk();

    $speakerContactValues = collect($speakerResponse->json('data.speaker.contacts'))->pluck('value')->all();

    expect($speakerResponse->json('data.speaker.media.cover_url'))->not->toBeEmpty()
        ->and($speakerContactValues)->toContain('public-speaker@example.test')
        ->and($speakerContactValues)->not->toContain('+6011222333')
        ->and($speakerResponse->json('data.speaker.social_media.0.resolved_url'))->toBe('https://speaker.example.test')
        ->and($venueResponse->json('data.venue.media.cover_url'))->not->toBeEmpty()
        ->and($referenceResponse->json('data.reference.media.front_cover_url'))->not->toBeEmpty()
        ->and($referenceResponse->json('data.reference.social_media.0.resolved_url'))->toBe('https://reference.example.test');

    $speakerResponse->assertJsonMissingPath('data.speaker.media.main_url');
    $referenceResponse->assertJsonMissingPath('data.reference.media.cover_url');
});

it('serializes venue and reference detail payloads with core metadata for mobile clients', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $user = User::factory()->create();

    $venue = Venue::factory()->create([
        'name' => 'Dewan DTO API',
        'description' => 'Venue detail serializer coverage',
        'status' => 'verified',
        'is_active' => true,
    ]);
    $venue->contacts()->create([
        'category' => ContactCategory::Phone->value,
        'value' => '+60399887766',
        'is_public' => true,
    ]);
    $venue->socialMedia()->create([
        'platform' => 'website',
        'url' => 'https://venue.example.test',
    ]);
    $venue->addMedia(UploadedFile::fake()->image('venue-dto-cover.jpg', 1600, 900))->toMediaCollection('cover');

    $reference = Reference::factory()->create([
        'title' => 'Rujukan DTO API',
        'author' => 'Penulis API',
        'type' => 'book',
        'publisher' => 'Penerbit API',
        'publication_year' => 2024,
        'description' => 'Reference detail serializer coverage',
        'status' => 'verified',
        'is_active' => true,
    ]);
    $reference->socialMedia()->create([
        'platform' => 'website',
        'url' => 'https://reference-dto.example.test',
    ]);
    $reference->addMedia(UploadedFile::fake()->image('reference-front.jpg', 1200, 1600))->toMediaCollection('front_cover');
    $reference->addMedia(UploadedFile::fake()->image('reference-back.jpg', 1200, 1600))->toMediaCollection('back_cover');

    $user->follow($reference);

    $detailVenue = $venue->fresh(['media', 'contacts', 'socialMedia']);
    $detailReference = $reference->fresh(['media', 'socialMedia']);

    expect($detailVenue)->not->toBeNull()
        ->and($detailReference)->not->toBeNull();

    $venueItem = Closure::bind(
        fn (): array => $this->venueDetailData($detailVenue),
        app(SearchController::class),
        SearchController::class,
    )();

    $referenceItem = Closure::bind(
        fn (): array => $this->referenceDetailData($detailReference, $user),
        app(SearchController::class),
        SearchController::class,
    )();

    expect(data_get($venueItem, 'name'))->toBe('Dewan DTO API')
        ->and(data_get($venueItem, 'status'))->toBe('verified')
        ->and(data_get($venueItem, 'is_active'))->toBeTrue()
        ->and(data_get($venueItem, 'media.cover_url'))->toBeString()->not->toBe('')
        ->and(data_get($venueItem, 'contacts.0.value'))->toBe('+60399887766')
        ->and(data_get($venueItem, 'social_media.0.resolved_url'))->toBe('https://venue.example.test')
        ->and(data_get($referenceItem, 'title'))->toBe('Rujukan DTO API')
        ->and(data_get($referenceItem, 'author'))->toBe('Penulis API')
        ->and(data_get($referenceItem, 'publication_year'))->toBe('2024')
        ->and(data_get($referenceItem, 'is_following'))->toBeTrue()
        ->and(data_get($referenceItem, 'media.front_cover_url'))->toBeString()->not->toBe('')
        ->and(data_get($referenceItem, 'media.back_cover_url'))->toBeString()->not->toBe('')
        ->and(data_get($referenceItem, 'social_media.0.resolved_url'))->toBe('https://reference-dto.example.test');
});

it('serializes series detail payloads with follow and media metadata for mobile clients', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $user = User::factory()->create();

    $series = Series::factory()->create([
        'title' => 'Siri DTO API',
        'description' => 'Series detail serializer coverage',
        'visibility' => 'public',
        'is_active' => true,
    ]);

    $series->addMedia(UploadedFile::fake()->image('series-cover.jpg', 1600, 900))->toMediaCollection('cover');

    $user->follow($series);

    Sanctum::actingAs($user);

    $response = $this->getJson(route('api.client.series.show', ['series' => $series->slug]))
        ->assertOk();

    expect($response->json('data.series.title'))->toBe('Siri DTO API')
        ->and($response->json('data.series.visibility'))->toBe('public')
        ->and($response->json('data.series.is_following'))->toBeTrue()
        ->and($response->json('data.series.media.cover_url'))->toBeString()->not->toBe('')
        ->and($response->json('data.upcoming_total'))->toBe(0)
        ->and($response->json('data.past_total'))->toBe(0);
});

it('mirrors the public speaker page payload for app clients', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $countryId = ensureFrontendApiMalaysiaCountryExists();
    $bioText = str_repeat('Biodata speaker aplikasi ini panjang dan terperinci. ', 20);

    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
        'job_title' => 'Penasihat Dakwah',
        'is_freelance' => true,
        'bio' => [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => $bioText,
                ]],
            ]],
        ],
    ]);
    $speaker->addMedia(UploadedFile::fake()->image('speaker-avatar.jpg'))->toMediaCollection('avatar');
    $speaker->addMedia(UploadedFile::fake()->image('speaker-cover.jpg'))->toMediaCollection('cover');
    $speaker->addMedia(UploadedFile::fake()->image('speaker-gallery-1.jpg'))->toMediaCollection('gallery');
    $speaker->addMedia(UploadedFile::fake()->image('speaker-gallery-2.jpg'))->toMediaCollection('gallery');
    $speaker->update(['job_title' => 'Penasihat Dakwah']);

    $speakerState = State::query()->create([
        'country_id' => $countryId,
        'name' => 'Pahang',
        'country_code' => 'MY',
    ]);
    $speakerDistrict = District::query()->create([
        'country_id' => $countryId,
        'state_id' => (int) $speakerState->id,
        'country_code' => 'MY',
        'name' => 'Temerloh',
    ]);
    $speakerSubdistrict = Subdistrict::query()->create([
        'country_id' => $countryId,
        'state_id' => (int) $speakerState->id,
        'district_id' => (int) $speakerDistrict->id,
        'country_code' => 'MY',
        'name' => 'Temerloh',
    ]);

    $speaker->address()->update([
        'country_id' => $countryId,
        'state_id' => (int) $speakerState->id,
        'district_id' => (int) $speakerDistrict->id,
        'subdistrict_id' => (int) $speakerSubdistrict->id,
    ]);

    $institution = Institution::factory()->create([
        'name' => 'Madrasah API',
        'status' => 'verified',
        'is_active' => true,
    ]);
    $institution->addMedia(UploadedFile::fake()->image('institution-cover.jpg'))->toMediaCollection('cover');
    $speaker->institutions()->attach($institution->id, [
        'position' => 'Mudarris',
        'is_primary' => true,
    ]);

    $venue = Venue::factory()->create([
        'name' => 'Dewan Seri API',
        'status' => 'verified',
        'is_active' => true,
    ]);
    $venueSubdistrict = Subdistrict::query()->create([
        'country_id' => $countryId,
        'state_id' => (int) $speakerState->id,
        'district_id' => (int) $speakerDistrict->id,
        'country_code' => 'MY',
        'name' => 'Mentakab',
    ]);
    $venue->address()->update([
        'country_id' => $countryId,
        'state_id' => (int) $speakerState->id,
        'district_id' => (int) $speakerDistrict->id,
        'subdistrict_id' => (int) $venueSubdistrict->id,
    ]);

    $bookReference = Reference::factory()->create([
        'title' => 'Kitab API',
        'type' => 'book',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $upcomingEvent = Event::factory()->prayerRelative()->create([
        'title' => 'Majlis API Akan Datang',
        'status' => 'pending',
        'is_active' => true,
        'visibility' => 'public',
        'event_format' => 'hybrid',
        'institution_id' => $institution->id,
        'venue_id' => $venue->id,
        'starts_at' => now()->addDays(3)->setTime(19, 30),
        'ends_at' => now()->addDays(3)->setTime(21, 0),
        'event_type' => ['kuliah_ceramah'],
    ]);
    $upcomingEvent->references()->attach($bookReference->id);
    $speaker->speakerEvents()->attach($upcomingEvent->id);

    $pastEvent = Event::factory()->create([
        'title' => 'Majlis API Lepas',
        'status' => 'approved',
        'is_active' => true,
        'visibility' => 'public',
        'event_format' => 'physical',
        'institution_id' => $institution->id,
        'venue_id' => $venue->id,
        'starts_at' => now()->subDays(2)->setTime(20, 0),
        'ends_at' => now()->subDays(2)->setTime(22, 0),
        'event_type' => ['forum'],
    ]);
    $speaker->speakerEvents()->attach($pastEvent->id);

    $otherRoleEvent = Event::factory()->create([
        'title' => 'Forum API Moderator',
        'status' => 'approved',
        'is_active' => true,
        'visibility' => 'public',
        'event_format' => 'physical',
        'institution_id' => $institution->id,
        'venue_id' => $venue->id,
        'starts_at' => now()->addWeek()->setTime(20, 0),
        'ends_at' => now()->addWeek()->setTime(22, 0),
        'event_type' => ['forum'],
    ]);

    EventKeyPerson::factory()->create([
        'event_id' => $otherRoleEvent->id,
        'speaker_id' => $speaker->id,
        'role' => EventKeyPersonRole::Moderator,
        'is_public' => true,
        'order_column' => 1,
    ]);

    $response = $this->withUnencryptedCookie('user_timezone', 'Asia/Kuala_Lumpur')
        ->getJson(route('api.client.speakers.show', ['speakerKey' => $speaker->slug]))
        ->assertOk();

    expect($response->json('data.speaker.job_title'))->toBe('Penasihat Dakwah')
        ->and($response->json('data.speaker.is_freelance'))->toBeTrue()
        ->and($response->json('data.speaker.address.country_id'))->toBe($countryId)
        ->and($response->json('data.speaker.country.iso2'))->toBe('MY')
        ->and($response->json('data.speaker.country.key'))->toBe('malaysia')
        ->and($response->json('data.speaker.location'))->toBe('Temerloh, Pahang')
        ->and($response->json('data.speaker.bio_text'))->toContain('Biodata speaker aplikasi ini panjang')
        ->and($response->json('data.speaker.bio_html'))->toContain('<p')
        ->and($response->json('data.speaker.bio_excerpt'))->toBe(Str::limit($bioText, 180))
        ->and($response->json('data.speaker.should_collapse_bio'))->toBeTrue()
        ->and($response->json('data.speaker.media.avatar_url'))->not->toBeEmpty()
        ->and($response->json('data.speaker.media.share_image_url'))->not->toBeEmpty()
        ->and($response->json('data.speaker.gallery'))->toHaveCount(2)
        ->and($response->json('data.speaker.institutions.0.name'))->toBe('Madrasah API')
        ->and($response->json('data.speaker.institutions.0.position'))->toBe('Mudarris')
        ->and($response->json('data.speaker.institutions.0.is_primary'))->toBeTrue()
        ->and($response->json('data.speaker.institutions.0.public_image_url'))->not->toBeEmpty()
        ->and($response->json('data.speaker.institutions.0.public_image_url'))->toBe($response->json('data.speaker.institutions.0.chip_image_url'))
        ->and($response->json('data.speaker.institutions.0.chip_image_url'))->not->toBeEmpty()
        ->and($response->json('data.upcoming_events.0.reference_study_subtitle'))->toBe('Kitab API')
        ->and($response->json('data.upcoming_events.0.event_type_label'))->toBe(EventType::KuliahCeramah->getLabel())
        ->and($response->json('data.upcoming_events.0.event_format'))->toBe('hybrid')
        ->and($response->json('data.upcoming_events.0.event_format_label'))->toBe(EventFormat::Hybrid->getLabel())
        ->and($response->json('data.upcoming_events.0.timing_display'))->not->toBeEmpty()
        ->and($response->json('data.upcoming_events.0.location'))->toBe('Dewan Seri API, Mentakab, Temerloh, Pahang')
        ->and($response->json('data.upcoming_events.0.is_remote'))->toBeTrue()
        ->and($response->json('data.upcoming_events.0.is_pending'))->toBeTrue()
        ->and($response->json('data.upcoming_events.0.is_cancelled'))->toBeFalse()
        ->and($response->json('data.upcoming_events.0.institution.public_image_url'))->toBe($institution->public_image_url)
        ->and($response->json('data.other_role_upcoming_total'))->toBe(1)
        ->and($response->json('data.other_role_past_total'))->toBe(0)
        ->and($response->json('data.other_role_upcoming_participations.0.role'))->toBe('moderator')
        ->and($response->json('data.other_role_upcoming_participations.0.role_label'))->toBe(EventKeyPersonRole::Moderator->getLabel())
        ->and($response->json('data.other_role_upcoming_participations.0.event.title'))->toBe('Forum API Moderator');
});

it('allows following and unfollowing a speaker through the frontend api', function () {
    $user = User::factory()->create();
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    Sanctum::actingAs($user);

    $this->postJson(route('api.client.follows.store', ['type' => 'speaker', 'subject' => $speaker->slug]))
        ->assertCreated()
        ->assertJsonPath('data.is_following', true);

    expect($user->fresh()->isFollowing($speaker))->toBeTrue();

    $this->deleteJson(route('api.client.follows.destroy', ['type' => 'speaker', 'subject' => $speaker->slug]))
        ->assertOk()
        ->assertJsonPath('data.is_following', false);

    expect($user->fresh()->isFollowing($speaker))->toBeFalse();
});
