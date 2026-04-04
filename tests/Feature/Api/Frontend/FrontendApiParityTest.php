<?php

use App\Actions\Membership\AddMemberToSubject;
use App\Enums\ContactCategory;
use App\Models\ContributionRequest;
use App\Models\Event;
use App\Models\Institution;
use App\Models\MembershipClaim;
use App\Models\Reference;
use App\Models\Speaker;
use App\Models\Tag;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    fakePrayerTimesApi();
});

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

    expect($submitEvent['captcha_required_when_turnstile_enabled'])->toBeTrue()
        ->and($submitEventFields)->toContain('parent_event_id', 'scoped_institution_id')
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
        ->not->toContain('position')
        ->not->toContain('main')
        ->not->toContain('institution_id');

    $institutionContract = $this->getJson(route('api.client.forms.contributions.institutions'))
        ->assertOk()
        ->json('data');
    $institutionFields = collect($institutionContract['fields'] ?? [])->pluck('name')->all();

    expect($institutionFields)->not->toContain('logo');
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
        ->assertJsonPath('data.institution.name', 'Masjid API');

    $institution = Institution::query()->where('name', 'Masjid API')->firstOrFail();

    expect($institution->status)->toBe('pending')
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
        'timezone' => 'Asia/Kuala_Lumpur',
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
        'timezone' => 'Asia/Kuala_Lumpur',
        'submitter_name' => 'Guest Submitter',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['submitter_email', 'submitter_phone']);
});

it('requires a live url for online frontend event submissions', function () {
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
        'timezone' => 'Asia/Kuala_Lumpur',
        'submitter_name' => 'Guest Submitter',
        'submitter_email' => 'guest@example.test',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['live_url']);
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
        'timezone' => 'Asia/Kuala_Lumpur',
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
