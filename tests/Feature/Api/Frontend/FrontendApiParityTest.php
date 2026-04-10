<?php

use AIArmada\FilamentAuthz\Facades\Authz;
use App\Actions\Membership\AddMemberToSubject;
use App\Enums\ContactCategory;
use App\Enums\EventFormat;
use App\Enums\EventKeyPersonRole;
use App\Enums\EventType;
use App\Models\ContributionRequest;
use App\Models\District;
use App\Models\Event;
use App\Models\EventKeyPerson;
use App\Models\Institution;
use App\Models\MembershipClaim;
use App\Models\Reference;
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
use Database\Seeders\PermissionSeeder;
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
    $manifest = $this->getJson(route('api.client.manifest'))
        ->assertOk()
        ->json('data');
    $contributionUpdateFlow = $manifest['flows']['contribution_update'] ?? [];
    $membershipClaimFlow = $manifest['flows']['membership_claim'] ?? [];
    $followFlow = $manifest['flows']['follow'] ?? [];

    $submitEvent = $this->getJson(route('api.client.forms.submit-event'))
        ->assertOk()
        ->json('data');

    $submitEventFields = collect($submitEvent['fields'] ?? [])->pluck('name')->all();
    $submitEventConditionalRules = collect($submitEvent['conditional_rules'] ?? []);

    expect($submitEvent['captcha_required_when_turnstile_enabled'])->toBeTrue()
        ->and($submitEventFields)->toContain('parent_event_id', 'scoped_institution_id')
        ->and($submitEventFields)->toContain('submission_country_id')
        ->not->toContain('timezone')
        ->and($submitEventConditionalRules->pluck('field')->all())->not->toContain('live_url')
        ->and(collect($submitEvent['fields'])->firstWhere('name', 'submission_country_id')['allowed_values'])->toContain(132)
        ->and(collect($submitEvent['fields'])->firstWhere('name', 'notes')['max_length'])->toBe(1000)
        ->and(collect($submitEvent['fields'])->firstWhere('name', 'captcha_token')['required'])->toBeFalse()
        ->and(collect($submitEvent['fields'])->firstWhere('name', 'submitter_email')['required'])->toBeFalse()
        ->and($contributionUpdateFlow['endpoint_template'] ?? null)->toContain('/api/v1/contributions/subjectType/subject/suggest')
        ->and($contributionUpdateFlow['schema_endpoint_template'] ?? null)->toContain('/api/v1/forms/contributions/subjectType/subject/suggest')
        ->and($membershipClaimFlow['endpoint_template'] ?? null)->toContain('/api/v1/membership-claims/subjectType/subject')
        ->and($followFlow['state_endpoint_template'] ?? null)->toContain('/api/v1/follows/type/subject');

    $speakerContract = $this->getJson(route('api.client.forms.contributions.speakers'))
        ->assertOk()
        ->json('data');
    $speakerFields = collect($speakerContract['fields'] ?? [])->pluck('name')->all();

    expect($speakerFields)->toContain('job_title', 'cover', 'address', 'qualifications')
        ->toContain('address.country_id', 'address.state_id', 'address.district_id', 'address.subdistrict_id')
        ->not->toContain('position')
        ->not->toContain('main')
        ->not->toContain('address.line1')
        ->not->toContain('institution_id');

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
        ->and($ownerResponse->json('data.direct_edit_media_fields'))->toBe(['cover'])
        ->and($fields->pluck('name')->all())->toContain('description', 'address', 'social_media')
        ->and($fields->firstWhere('name', 'type')['allowed_values'])->toContain('masjid')
        ->and($ownerResponse->json('data.initial_state.description'))->toBe('Community institution');
});

it('normalizes event update context to public organizer values and exposes lookup metadata', function () {
    $user = User::factory()->create();
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
        'event_type' => ['kuliah_ceramah'],
        'gender' => 'all',
        'age_group' => ['all_ages'],
        'event_format' => 'physical',
        'visibility' => 'public',
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(route('api.client.forms.contributions.suggest', [
        'subjectType' => 'majlis',
        'subject' => $event->slug,
    ]))->assertOk();

    $fields = collect($response->json('data.fields'));

    expect($response->json('data.initial_state.organizer_type'))->toBe('institution')
        ->and($fields->firstWhere('name', 'organizer_type')['allowed_values'])->toBe(['institution', 'speaker'])
        ->and($fields->firstWhere('name', 'language_ids')['catalog'])->toContain('/api/v1/catalogs/languages')
        ->and($fields->firstWhere('name', 'speaker_ids')['catalog'])->toContain('/api/v1/catalogs/submit-speakers');
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
        'organizer_id' => $speaker->getKey(),
        'live_url' => null,
    ])
        ->assertOk()
        ->assertJsonPath('data.mode', 'direct_edit');

    expect($event->fresh()->organizer_type)->toBe(Speaker::class)
        ->and($event->fresh()->organizer_id)->toBe($speaker->getKey())
        ->and($event->fresh()->live_url)->toBeNull();
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

    expect(data_get($submitEvent, 'defaults.submission_country_id'))->toBe($singaporeId)
        ->and(collect($submitEvent['fields'])->firstWhere('name', 'submission_country_id')['allowed_values'])->toContain($singaporeId);
});

it('creates institution contribution requests through the frontend api', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson(route('api.client.contributions.institutions.store'), [
        'type' => 'masjid',
        'name' => 'Masjid API',
        'nickname' => 'API',
        'description' => '<p>Institution description</p>',
        'address' => [
            'country_id' => 132,
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

it('requires address.country_id when creating speakers through the frontend api', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson(route('api.client.contributions.speakers.store'), [
        'name' => 'Frontend API Missing Country Speaker',
        'gender' => 'male',
        'address' => [],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['address.country_id']);
});

it('accepts a region-only speaker address payload through the frontend api', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->postJson(route('api.client.contributions.speakers.store'), [
        'name' => 'Frontend API Region Speaker',
        'gender' => 'male',
        'address' => [
            'country_id' => 132,
            'state_id' => null,
            'district_id' => null,
            'subdistrict_id' => null,
        ],
    ])->assertCreated()
        ->assertJsonPath('message', __('Thank you. Your speaker submission has been received. We will notify you if it is approved or rejected.'))
        ->assertJsonPath('data.speaker.name', 'Frontend API Region Speaker');

    $speaker = Speaker::query()->where('name', 'Frontend API Region Speaker')->firstOrFail();

    expect($speaker->addressModel)->not->toBeNull()
        ->and($speaker->addressModel?->country_id)->toBe(132)
        ->and($speaker->addressModel?->line1)->toBeNull()
        ->and($speaker->addressModel?->google_maps_url)->toBeNull();
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

it('uses the same stable public directory ordering in the frontend institution api', function () {
    $firstInstitution = Institution::factory()->create([
        'name' => 'Adam Institution API',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $secondInstitution = Institution::factory()->create([
        'name' => 'Zaid Institution API',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $response = $this->getJson(route('api.client.institutions.index', [
        'search' => 'Institution API',
        'per_page' => 12,
    ]))->assertOk();

    $expectedOrder = [$firstInstitution->id, $secondInstitution->id];

    usort($expectedOrder, static function (string $left, string $right): int {
        $leftParts = Institution::publicDirectorySortParts($left);
        $rightParts = Institution::publicDirectorySortParts($right);

        $primaryComparison = $leftParts['primary'] <=> $rightParts['primary'];

        if ($primaryComparison !== 0) {
            return $primaryComparison;
        }

        return $leftParts['secondary'] <=> $rightParts['secondary'];
    });

    expect(collect($response->json('data'))
        ->pluck('id')
        ->intersect([$firstInstitution->id, $secondInstitution->id])
        ->values()
        ->all())->toBe($expectedOrder);
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

it('still accepts legacy timezone-only event submissions through the frontend api', function () {
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

    $this->postJson(route('api.client.submit-event.store'), [
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
        'timezone' => 'Asia/Kuala_Lumpur',
    ])
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
