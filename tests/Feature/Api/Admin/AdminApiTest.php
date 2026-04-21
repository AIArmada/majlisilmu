<?php

use App\Enums\ContributionRequestStatus;
use App\Enums\ContributionRequestType;
use App\Enums\ContributionSubjectType;
use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\RegistrationMode;
use App\Models\ContributionRequest;
use App\Models\DonationChannel;
use App\Models\Event;
use App\Models\Inspiration;
use App\Models\Institution;
use App\Models\MembershipClaim;
use App\Models\ModerationReview;
use App\Models\Reference;
use App\Models\Report;
use App\Models\Series;
use App\Models\Space;
use App\Models\Speaker;
use App\Models\Subdistrict;
use App\Models\Tag;
use App\Models\User;
use App\Models\Venue;
use App\Support\Location\FederalTerritoryLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

it('rejects users without admin panel access from the admin api manifest', function () {
    $user = User::factory()->create();

    Sanctum::actingAs($user);

    $this->getJson('/api/v1/admin/manifest')
        ->assertForbidden();
});

it('lists accessible admin resources for privileged users', function () {
    $admin = adminApiUser('super_admin');

    Sanctum::actingAs($admin);

    $response = $this->getJson('/api/v1/admin/manifest')
        ->assertOk();

    expect($response->json('data.version'))->toBe('2026-04-21')
        ->and($response->json('data.docs.ui'))->toBe('https://api.majlisilmu.test/docs')
        ->and($response->json('data.docs.openapi'))->toBe('https://api.majlisilmu.test/docs.json')
        ->and($response->json('data.surface_sync.strategy'))->toBe('curated_parity')
        ->and($response->json('data.surface_sync.default_panel_only_operations'))->toContain('delete', 'restore', 'replicate', 'reorder')
        ->and($response->json('data.surface_sync.workflow_first_capabilities'))->toContain('event moderation', 'contribution review')
        ->and($response->json('data.workflow_actions.moderate_event.mcp_tool'))->toBe('admin-moderate-event')
        ->and($response->json('data.workflow_actions.triage_report.mcp_tool'))->toBe('admin-triage-report')
        ->and($response->json('data.workflow_actions.review_contribution_request.mcp_tool'))->toBe('admin-review-contribution-request')
        ->and($response->json('data.workflow_actions.review_membership_claim.mcp_tool'))->toBe('admin-review-membership-claim')
        ->and($response->json('data.write_workflow.discover_resources'))->toContain('/api/v1/admin/manifest')
        ->and($response->json('data.rules'))->toContain('Use the admin record route_key returned by admin collection or detail payloads for record-specific paths.');

    $resourceKeys = collect($response->json('data.resources'))->pluck('key')->all();

    expect($resourceKeys)->toContain('speakers', 'events', 'inspirations', 'institutions', 'references', 'reports', 'series', 'spaces', 'subdistricts', 'venues', 'tags', 'donation-channels');
});

it('allows viewer-role users who can access the admin panel to reach the admin api manifest', function () {
    $viewer = adminApiUser('viewer');

    Sanctum::actingAs($viewer);

    $this->getJson('/api/v1/admin/manifest')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'resources' => [
                    ['key'],
                ],
            ],
        ]);
});

it('does not elevate admin manifest access from bearer token abilities alone', function () {
    $nonAdmin = User::factory()->create();
    $nonAdminToken = $nonAdmin->createToken('non-admin-device', ['admin.manifest'])->plainTextToken;

    $this->withToken($nonAdminToken)
        ->getJson('/api/v1/admin/manifest')
        ->assertForbidden();
});

it('uses the authenticated token user roles for admin manifest access without token abilities', function () {
    $viewer = adminApiUser('viewer');
    $viewerToken = $viewer->createToken('viewer-device', [])->plainTextToken;

    $this->withToken($viewerToken)
        ->getJson('/api/v1/admin/manifest')
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'resources' => [
                    ['key'],
                ],
            ],
        ]);
});

it('reflects global admin role grants and removals on an existing bearer token', function () {
    setPermissionsTeamId(null);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    if (! Role::query()->where('name', 'viewer')->where('guard_name', 'web')->exists()) {
        $roleRecord = new Role;
        $roleRecord->forceFill([
            'id' => (string) Str::uuid(),
            'name' => 'viewer',
            'guard_name' => 'web',
        ])->save();
    }

    $user = User::factory()->create();
    $token = $user->createToken('role-drift-check', [])->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/admin/manifest')
        ->assertForbidden();

    $user->assignRole('viewer');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->withToken($token)
        ->getJson('/api/v1/admin/manifest')
        ->assertOk();

    $user->syncRoles([]);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->withToken($token)
        ->getJson('/api/v1/admin/manifest')
        ->assertForbidden();
});

it('returns admin speaker resource metadata and records', function () {
    $admin = adminApiUser('super_admin');
    $speaker = Speaker::factory()->create([
        'name' => 'Admin API Speaker',
    ]);
    $speakerRouteKey = (string) $speaker->getRouteKey();

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/speakers/meta')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'speakers')
        ->assertJsonPath('data.resource.pages.index', true)
        ->assertJsonPath('data.resource.abilities.view_any', true)
        ->assertJsonPath('data.resource.write_support.schema', true)
        ->assertJsonPath('data.resource.api_routes.collection', '/api/v1/admin/speakers')
        ->assertJsonPath('data.resource.api_routes.schema', '/api/v1/admin/speakers/schema')
        ->assertJsonPath('data.resource.filters.0.key', 'status')
        ->assertJsonPath('data.resource.filters.0.options.verified', 'Verified')
        ->assertJsonPath('data.resource.filters.1.key', 'is_active')
        ->assertJsonPath('data.resource.filters.2.key', 'has_events')
        ->assertJsonPath('data.resource.mcp_tools.create.arguments.validate_only', false)
        ->assertJsonPath('data.resource.mcp_tools.update.arguments.validate_only', false);

    $this->getJson('/api/v1/admin/speakers?search=Admin%20API%20Speaker')
        ->assertOk()
        ->assertJsonPath('data.0.id', $speaker->getKey())
        ->assertJsonPath('data.0.title', 'Admin API Speaker')
        ->assertJsonPath('data.0.abilities.view', true);

    $this->getJson('/api/v1/admin/speakers/'.$speakerRouteKey)
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'speakers')
        ->assertJsonPath('data.record.route_key', $speakerRouteKey)
        ->assertJsonPath('data.record.attributes.name', 'Admin API Speaker')
        ->assertJsonPath('data.record.abilities.view', true);
});

it('filters admin speaker records by explicit query parameters', function () {
    $admin = adminApiUser('super_admin');

    $speakerWithEvents = Speaker::factory()->create([
        'name' => 'Alpha Verified Speaker',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $speakerWithoutEvents = Speaker::factory()->create([
        'name' => 'Beta Verified Speaker',
        'status' => 'verified',
        'is_active' => false,
    ]);

    $pendingSpeaker = Speaker::factory()->create([
        'name' => 'Gamma Pending Speaker',
        'status' => 'pending',
        'is_active' => true,
    ]);

    Event::factory()->create([
        'title' => 'Speaker Filter Event',
    ])->speakers()->attach($speakerWithEvents->getKey());

    Sanctum::actingAs($admin);

    $verifiedResponse = $this->getJson('/api/v1/admin/speakers?filter[status]=verified')
        ->assertOk();

    $verifiedIds = collect($verifiedResponse->json('data'))->pluck('id')->all();

    expect(in_array($speakerWithEvents->getKey(), $verifiedIds, true))->toBeTrue();
    expect(in_array($speakerWithoutEvents->getKey(), $verifiedIds, true))->toBeTrue();
    expect(in_array($pendingSpeaker->getKey(), $verifiedIds, true))->toBeFalse();

    $inactiveResponse = $this->getJson('/api/v1/admin/speakers?filter[is_active]=0')
        ->assertOk();

    $inactiveIds = collect($inactiveResponse->json('data'))->pluck('id')->all();

    expect(in_array($speakerWithoutEvents->getKey(), $inactiveIds, true))->toBeTrue();

    $hasEventsResponse = $this->getJson('/api/v1/admin/speakers?filter[has_events]=true')
        ->assertOk();

    $hasEventsIds = collect($hasEventsResponse->json('data'))->pluck('id')->all();

    expect(in_array($speakerWithEvents->getKey(), $hasEventsIds, true))->toBeTrue();
    expect(in_array($speakerWithoutEvents->getKey(), $hasEventsIds, true))->toBeFalse();
});

it('filters admin event records by explicit query parameters', function () {
    $admin = adminApiUser('super_admin');

    $draftOnlineEvent = Event::factory()->create([
        'title' => 'Admin API Draft Online Event',
        'status' => 'draft',
        'event_format' => EventFormat::Online,
        'visibility' => EventVisibility::Public,
        'is_active' => true,
        'event_type' => [EventType::KuliahCeramah->value],
    ]);

    $approvedPhysicalEvent = Event::factory()->create([
        'title' => 'Admin API Approved Physical Event',
        'status' => 'approved',
        'event_format' => EventFormat::Physical,
        'visibility' => EventVisibility::Private,
        'is_active' => false,
        'event_type' => [EventType::Forum->value],
    ]);

    $cancelledHybridEvent = Event::factory()->create([
        'title' => 'Admin API Cancelled Hybrid Event',
        'status' => 'cancelled',
        'event_format' => EventFormat::Hybrid,
        'visibility' => EventVisibility::Unlisted,
        'is_active' => true,
        'event_type' => [EventType::Kenduri->value],
    ]);

    Sanctum::actingAs($admin);

    $metaResponse = $this->getJson('/api/v1/admin/events/meta')
        ->assertOk();

    expect(collect($metaResponse->json('data.resource.filters'))->pluck('key')->all())
        ->toContain('status', 'visibility', 'event_structure', 'event_format', 'event_type', 'timing_mode', 'prayer_reference', 'is_active');

    $draftResponse = $this->getJson('/api/v1/admin/events?filter[status]=draft')
        ->assertOk();

    expect($draftResponse->json('meta.pagination.total'))->toBe(1)
        ->and(collect($draftResponse->json('data'))->pluck('route_key')->all())->toContain($draftOnlineEvent->getRouteKey())
        ->and(collect($draftResponse->json('data'))->pluck('route_key')->all())->not->toContain($approvedPhysicalEvent->getRouteKey())
        ->and(collect($draftResponse->json('data'))->pluck('route_key')->all())->not->toContain($cancelledHybridEvent->getRouteKey());

    $onlineResponse = $this->getJson('/api/v1/admin/events?filter[event_format]=online')
        ->assertOk();

    expect($onlineResponse->json('meta.pagination.total'))->toBe(1)
        ->and(collect($onlineResponse->json('data'))->pluck('route_key')->all())->toContain($draftOnlineEvent->getRouteKey())
        ->and(collect($onlineResponse->json('data'))->pluck('route_key')->all())->not->toContain($approvedPhysicalEvent->getRouteKey())
        ->and(collect($onlineResponse->json('data'))->pluck('route_key')->all())->not->toContain($cancelledHybridEvent->getRouteKey());

    $privateResponse = $this->getJson('/api/v1/admin/events?filter[visibility]=private')
        ->assertOk();

    expect($privateResponse->json('meta.pagination.total'))->toBe(1)
        ->and(collect($privateResponse->json('data'))->pluck('route_key')->all())->toContain($approvedPhysicalEvent->getRouteKey())
        ->and(collect($privateResponse->json('data'))->pluck('route_key')->all())->not->toContain($draftOnlineEvent->getRouteKey())
        ->and(collect($privateResponse->json('data'))->pluck('route_key')->all())->not->toContain($cancelledHybridEvent->getRouteKey());

    $inactiveResponse = $this->getJson('/api/v1/admin/events?filter[is_active]=false')
        ->assertOk();

    expect($inactiveResponse->json('meta.pagination.total'))->toBe(1)
        ->and(collect($inactiveResponse->json('data'))->pluck('route_key')->all())->toContain($approvedPhysicalEvent->getRouteKey())
        ->and(collect($inactiveResponse->json('data'))->pluck('route_key')->all())->not->toContain($draftOnlineEvent->getRouteKey())
        ->and(collect($inactiveResponse->json('data'))->pluck('route_key')->all())->not->toContain($cancelledHybridEvent->getRouteKey());

    $eventTypeResponse = $this->getJson('/api/v1/admin/events?filter[event_type]=kuliah_ceramah')
        ->assertOk();

    expect($eventTypeResponse->json('meta.pagination.total'))->toBe(1)
        ->and(collect($eventTypeResponse->json('data'))->pluck('route_key')->all())->toContain($draftOnlineEvent->getRouteKey())
        ->and(collect($eventTypeResponse->json('data'))->pluck('route_key')->all())->not->toContain($approvedPhysicalEvent->getRouteKey())
        ->and(collect($eventTypeResponse->json('data'))->pluck('route_key')->all())->not->toContain($cancelledHybridEvent->getRouteKey());
});

it('previews admin speaker creation without persisting the record', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');

    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/v1/admin/speakers?validate_only=1', [
        'name' => 'Previewed Admin API Speaker',
        'gender' => 'male',
        'status' => 'verified',
        'is_freelance' => false,
        'is_active' => true,
        'address' => [
            'country_id' => 132,
        ],
    ])->assertOk();

    $response
        ->assertJsonPath('data.resource.key', 'speakers')
        ->assertJsonPath('data.preview.validate_only', true)
        ->assertJsonPath('data.preview.operation', 'create')
        ->assertJsonPath('data.preview.normalized_payload.address.country_id', 132)
        ->assertJsonPath('data.preview.current_record', null);

    expect(Speaker::query()->where('name', 'Previewed Admin API Speaker')->exists())->toBeFalse();
});

it('previews admin speaker updates without persisting the record', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    $speaker = Speaker::factory()->create([
        'name' => 'Previewable Admin API Speaker',
        'job_title' => null,
    ]);
    $originalName = (string) $speaker->name;
    $originalJobTitle = $speaker->job_title;
    $speakerRouteKey = (string) $speaker->getRouteKey();

    Sanctum::actingAs($admin);

    $response = $this->putJson('/api/v1/admin/speakers/'.$speakerRouteKey.'?validate_only=1', [
        'name' => 'Previewed Admin API Speaker Updated',
        'gender' => 'male',
        'status' => 'verified',
        'is_freelance' => true,
        'job_title' => 'Imam',
        'is_active' => true,
        'allow_public_event_submission' => true,
        'address' => [
            'country_id' => 132,
        ],
        'clear_cover' => true,
    ])->assertOk();

    $response
        ->assertJsonPath('data.resource.key', 'speakers')
        ->assertJsonPath('data.preview.validate_only', true)
        ->assertJsonPath('data.preview.operation', 'update')
        ->assertJsonPath('data.preview.current_record.route_key', $speakerRouteKey)
        ->assertJsonPath('data.preview.normalized_payload.job_title', 'Imam')
        ->assertJsonPath('data.preview.destructive_media_fields.0', 'clear_cover')
        ->assertJsonPath('data.preview.warnings.0.field', 'clear_cover');

    expect(Speaker::query()->findOrFail($speaker->getKey())->name)->toBe($originalName)
        ->and(Speaker::query()->findOrFail($speaker->getKey())->job_title)->toBe($originalJobTitle);
});

it('returns autofill hints and conditional requirements in admin api dry runs', function () {
    ensureAdminApiMalaysiaCountryExists();
    $admin = adminApiUser('super_admin');

    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/admin/events?validate_only=1&apply_defaults=1', [
        'title' => 'Admin API AI Feedback Preview',
        'event_date' => '2026-06-10',
    ])->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonPath('error.details.feedback.validate_only', true)
        ->assertJsonPath('error.details.feedback.apply_defaults', true)
        ->assertJsonPath('error.details.feedback.normalized_payload.timezone', 'Asia/Kuala_Lumpur')
        ->assertJsonPath('error.details.feedback.normalized_payload.event_format', EventFormat::Physical->value)
        ->assertJsonPath('error.details.feedback.normalized_payload.prayer_time', EventPrayerTime::LainWaktu->value)
        ->assertJsonPath('error.details.feedback.issues.0.field', 'custom_time')
        ->assertJsonPath('error.details.feedback.issues.0.required_because.prayer_time', EventPrayerTime::LainWaktu->value);
});

it('returns remediation details for validate-only admin api create validation failures', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');

    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/v1/admin/speakers?validate_only=1', [
        'name' => 'Remediation Preview API Speaker',
    ])->assertUnprocessable();

    $fixPlan = collect($response->json('error.details.fix_plan'))->keyBy('field');
    $remainingBlockers = collect($response->json('error.details.remaining_blockers'))->keyBy('field');

    $response
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonPath('error.details.normalized_payload_preview.name', 'Remediation Preview API Speaker')
        ->assertJsonPath('error.details.normalized_payload_preview.gender', 'male')
        ->assertJsonPath('error.details.normalized_payload_preview.address.country_id', 132)
        ->assertJsonPath('error.details.can_retry', false);

    expect($fixPlan->get('gender'))
        ->toMatchArray([
            'action' => 'set_field',
            'field' => 'gender',
            'value' => 'male',
            'auto_apply_safe' => true,
        ])
        ->and($fixPlan->get('status'))->toMatchArray([
            'action' => 'choose_one',
            'field' => 'status',
            'options' => ['pending', 'verified', 'rejected'],
            'auto_apply_safe' => false,
        ])
        ->and($fixPlan->get('address'))->toMatchArray([
            'action' => 'set_field',
            'field' => 'address',
            'value' => [
                'country_id' => 132,
                'state_id' => null,
                'district_id' => null,
                'subdistrict_id' => null,
            ],
            'auto_apply_safe' => true,
        ])
        ->and($remainingBlockers->get('status'))->toMatchArray([
            'field' => 'status',
            'type' => 'required_choice',
            'options' => ['pending', 'verified', 'rejected'],
        ]);
});

it('returns structured enum suggestions for admin api validation errors', function () {
    $admin = adminApiUser('super_admin');

    Sanctum::actingAs($admin);

    $response = $this->postJson('/api/v1/admin/events', [
        'title' => 'Admin API Invalid Enum Preview',
        'event_date' => '2026-06-10',
        'custom_time' => '8:30 PM',
        'event_format' => 'physicl',
    ])->assertUnprocessable()
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonPath('error.details.feedback.validate_only', false)
        ->assertJsonPath('error.details.feedback.apply_defaults', false);

    $issue = collect($response->json('error.details.feedback.issues'))
        ->firstWhere('field', 'event_format');

    expect($issue)
        ->toBeArray()
        ->and($issue['closest_valid_value'] ?? null)->toBe(EventFormat::Physical->value)
        ->and($issue['suggested'] ?? null)->toBe(EventFormat::Physical->value)
        ->and(data_get($issue, 'allowed_values.0'))->toBe(EventFormat::Physical->value);
});

it('returns retryable remediation details for validate-only admin api update validation failures', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    $speaker = Speaker::factory()->create([
        'name' => 'Retryable Admin API Speaker',
        'gender' => 'male',
        'status' => 'verified',
    ]);
    $speakerRouteKey = (string) $speaker->getRouteKey();

    Sanctum::actingAs($admin);

    $response = $this->putJson('/api/v1/admin/speakers/'.$speakerRouteKey.'?validate_only=1', [
        'name' => 'Retryable Admin API Speaker Updated',
    ])->assertUnprocessable();

    $fixPlan = collect($response->json('error.details.fix_plan'))->keyBy('field');

    $response
        ->assertJsonPath('error.code', 'validation_error')
        ->assertJsonPath('error.details.normalized_payload_preview.name', 'Retryable Admin API Speaker Updated')
        ->assertJsonPath('error.details.normalized_payload_preview.gender', $speaker->gender)
        ->assertJsonPath('error.details.normalized_payload_preview.status', $speaker->status)
        ->assertJsonCount(0, 'error.details.remaining_blockers')
        ->assertJsonPath('error.details.can_retry', true);

    expect($fixPlan->get('gender'))->toMatchArray([
        'action' => 'set_field',
        'field' => 'gender',
        'value' => $speaker->gender,
        'auto_apply_safe' => true,
    ])->and($fixPlan->get('status'))->toMatchArray([
        'action' => 'set_field',
        'field' => 'status',
        'value' => $speaker->status,
        'auto_apply_safe' => true,
    ]);
});

it('lists related records for admin resource relations', function () {
    $admin = adminApiUser('super_admin');
    $relatedTitle = 'Nested Relation Event '.Str::ulid();
    $speaker = Speaker::factory()->create([
        'name' => 'Nested Relation Speaker',
    ]);
    $event = Event::factory()->create([
        'title' => $relatedTitle,
    ]);

    $event->speakers()->attach($speaker);
    $speakerRouteKey = (string) $speaker->getRouteKey();

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/speakers/meta')
        ->assertOk()
        ->assertJson(fn ($json) => $json
            ->where('data.resource.api_routes.related_collection', '/api/v1/admin/speakers/record/relations/relation')
            ->where('data.resource.relations', fn ($relations): bool => collect($relations)->contains('events'))
            ->etc());

    $response = $this->getJson('/api/v1/admin/speakers/'.$speakerRouteKey.'/relations/events?search='.urlencode($relatedTitle))
        ->assertOk();

    $response
        ->assertJsonPath('data.0.route_key', $event->getRouteKey())
        ->assertJsonPath('data.0.title', $relatedTitle)
        ->assertJsonPath('meta.resource.key', 'speakers')
        ->assertJsonPath('meta.parent_record.route_key', $speakerRouteKey)
        ->assertJsonPath('meta.relation.name', 'events')
        ->assertJsonPath('meta.relation.related_resource.key', 'events');
});

it('exposes tag write schema and can create and update tags through the api', function () {
    $admin = adminApiUser('super_admin');

    Sanctum::actingAs($admin);

    $schema = $this->getJson('/api/v1/admin/tags/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'tags')
        ->assertJsonPath('data.schema.resource_key', 'tags')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.content_type', 'application/json')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/tags')
        ->json('data.schema');

    expect(collect($schema['fields'] ?? [])->pluck('name')->all())
        ->toContain('name', 'name.ms', 'name.en', 'type', 'status', 'order_column');

    $createResponse = $this->postJson('/api/v1/admin/tags', [
        'name' => [
            'ms' => 'Tadabbur',
            'en' => 'Reflection',
        ],
        'type' => 'discipline',
        'status' => 'verified',
    ])->assertCreated();

    $tagRouteKey = (string) $createResponse->json('data.record.route_key');
    $tag = Tag::query()->findOrFail($tagRouteKey);

    expect($tag->type)->toBe('discipline')
        ->and($tag->status)->toBe('verified')
        ->and($tag->getTranslation('name', 'ms'))->toBe('Tadabbur')
        ->and($tag->getTranslation('slug', 'ms'))->toBe('tadabbur');

    $this->putJson('/api/v1/admin/tags/'.$tagRouteKey, [
        'name' => [
            'ms' => 'Rasuah',
            'en' => 'Corruption',
        ],
        'type' => 'issue',
        'status' => 'pending',
        'order_column' => 5,
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.type', 'issue')
        ->assertJsonPath('data.record.attributes.status', 'pending')
        ->assertJsonPath('data.record.attributes.order_column', 5);

    $tag->refresh();

    expect($tag->type)->toBe('issue')
        ->and($tag->status)->toBe('pending')
        ->and($tag->order_column)->toBe(5)
        ->and($tag->getTranslation('name', 'en'))->toBe('Corruption')
        ->and($tag->getTranslation('slug', 'en'))->toBe('corruption');
});

it('exposes event moderation schema and can request changes through the admin workflow endpoints', function () {
    $admin = adminApiUser('super_admin');
    $submitter = User::factory()->create();
    $event = Event::factory()->create([
        'status' => 'pending',
        'submitter_id' => $submitter->getKey(),
    ]);

    Sanctum::actingAs($admin);

    $schemaResponse = $this->getJson('/api/v1/admin/events/'.$event->getRouteKey().'/moderation-schema')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'events')
        ->assertJsonPath('data.record.route_key', $event->getRouteKey())
        ->assertJsonPath('data.schema.action', 'moderate_event')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/events/'.$event->getRouteKey().'/moderate');

    $actionField = collect($schemaResponse->json('data.schema.fields'))->firstWhere('name', 'action');

    expect($actionField['allowed_values'] ?? [])
        ->toContain('approve', 'request_changes', 'reject', 'cancel')
        ->not->toContain('reconsider', 'remoderate', 'revert_to_draft');

    $this->postJson('/api/v1/admin/events/'.$event->getRouteKey().'/moderate', [
        'action' => 'request_changes',
        'reason_code' => 'incomplete_info',
        'note' => 'Please add venue details before approval.',
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.status', 'needs_changes');

    $event->refresh();

    expect((string) $event->status)->toBe('needs_changes');

    $review = ModerationReview::query()->where('event_id', $event->getKey())->latest()->first();

    expect($review?->decision)->toBe('needs_changes')
        ->and($review?->reason_code)->toBe('incomplete_info');

    $this->assertDatabaseHas('notification_messages', [
        'user_id' => $submitter->getKey(),
        'trigger' => 'submission_needs_changes',
    ]);
});

it('exposes contribution request review schema and can approve requests through the admin workflow endpoints', function () {
    $admin = adminApiUser('moderator');
    $institution = Institution::factory()->create([
        'description' => 'Before review',
        'status' => 'verified',
    ]);
    $request = ContributionRequest::factory()->create([
        'type' => ContributionRequestType::Update,
        'subject_type' => ContributionSubjectType::Institution,
        'entity_type' => $institution->getMorphClass(),
        'entity_id' => $institution->getKey(),
        'status' => ContributionRequestStatus::Pending,
        'proposed_data' => [
            'description' => 'After review',
        ],
        'original_data' => [
            'description' => 'Before review',
        ],
    ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/contribution-requests/'.$request->getRouteKey().'/review-schema')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'contribution-requests')
        ->assertJsonPath('data.record.route_key', $request->getRouteKey())
        ->assertJsonPath('data.schema.action', 'review_contribution_request')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/contribution-requests/'.$request->getRouteKey().'/review')
        ->assertJsonPath('data.schema.conditional_rules.0.field', 'reason_code');

    $this->postJson('/api/v1/admin/contribution-requests/'.$request->getRouteKey().'/review', [
        'action' => 'approve',
        'reviewer_note' => 'Looks accurate.',
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.status', 'approved')
        ->assertJsonPath('data.record.attributes.reviewer_note', 'Looks accurate.');

    expect($request->fresh()?->status)->toBe(ContributionRequestStatus::Approved)
        ->and($request->fresh()?->reviewer_id)->toBe($admin->getKey())
        ->and($request->fresh()?->reviewer_note)->toBe('Looks accurate.')
        ->and($institution->fresh()?->description)->toBe('After review');
});

it('exposes report triage schema and can resolve reports through the admin workflow endpoints', function () {
    $admin = adminApiUser('moderator');
    $reporter = User::factory()->create();
    $report = Report::factory()->create([
        'reporter_id' => $reporter->getKey(),
        'status' => 'open',
        'handled_by' => null,
        'resolution_note' => null,
    ]);

    Sanctum::actingAs($admin);

    $schemaResponse = $this->getJson('/api/v1/admin/reports/'.$report->getRouteKey().'/triage-schema')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'reports')
        ->assertJsonPath('data.record.route_key', $report->getRouteKey())
        ->assertJsonPath('data.schema.action', 'triage_report')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/reports/'.$report->getRouteKey().'/triage');

    $actionField = collect($schemaResponse->json('data.schema.fields'))->firstWhere('name', 'action');

    expect($actionField['allowed_values'] ?? [])
        ->toContain('triage', 'resolve', 'dismiss')
        ->not->toContain('reopen');

    $this->postJson('/api/v1/admin/reports/'.$report->getRouteKey().'/triage', [
        'action' => 'resolve',
        'resolution_note' => 'Handled through the admin API.',
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.status', 'resolved')
        ->assertJsonPath('data.record.attributes.resolution_note', 'Handled through the admin API.');

    $report->refresh();

    expect($report->status)->toBe('resolved')
        ->and($report->handled_by)->toBe($admin->getKey())
        ->and($report->resolution_note)->toBe('Handled through the admin API.');
});

it('exposes report write schema and can create and update reports through the api', function () {
    $admin = adminApiUser('moderator');
    $reporter = User::factory()->create();
    $event = Event::factory()->create();
    $reference = Reference::factory()->verified()->create();

    Sanctum::actingAs($admin);

    $schema = $this->getJson('/api/v1/admin/reports/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'reports')
        ->assertJsonPath('data.schema.resource_key', 'reports')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.content_type', 'multipart/form-data')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/reports')
        ->json('data.schema');

    expect(collect($schema['fields'] ?? [])->pluck('name')->all())
        ->toContain('entity_type', 'entity_id', 'category', 'status', 'reporter_id', 'handled_by', 'resolution_note', 'evidence', 'clear_evidence');

    $createResponse = $this->withHeaders(['Accept' => 'application/json'])->post('/api/v1/admin/reports', [
        'entity_type' => 'event',
        'entity_id' => (string) $event->getKey(),
        'category' => 'wrong_info',
        'description' => 'Created through admin API.',
        'status' => 'open',
        'reporter_id' => (string) $reporter->getKey(),
        'evidence' => [
            fakeGeneratedImageUpload('admin-api-report-evidence.png', 640, 640),
        ],
    ])->assertCreated();

    $reportRouteKey = (string) $createResponse->json('data.record.route_key');
    $report = Report::query()->with(['entity', 'reporter', 'handler', 'media'])->findOrFail($reportRouteKey);

    expect($report->entity_type)->toBe('event')
        ->and($report->entity_id)->toBe($event->getKey())
        ->and($report->category)->toBe('wrong_info')
        ->and($report->status)->toBe('open')
        ->and($report->reporter_id)->toBe($reporter->getKey())
        ->and($report->handled_by)->toBeNull()
        ->and($report->getMedia('evidence'))->toHaveCount(1);

    $this->getJson('/api/v1/admin/reports/schema?operation=update&recordKey='.$reportRouteKey)
        ->assertOk()
        ->assertJsonPath('data.schema.method', 'PUT')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/reports/'.$reportRouteKey)
        ->assertJsonPath('data.schema.defaults.entity_type', 'event')
        ->assertJsonPath('data.schema.defaults.entity_id', (string) $event->getKey())
        ->assertJsonPath('data.schema.defaults.clear_evidence', false);

    $this->putJson('/api/v1/admin/reports/'.$reportRouteKey, [
        'entity_type' => 'reference',
        'entity_id' => (string) $reference->getKey(),
        'category' => 'fake_reference',
        'description' => 'Updated through admin API.',
        'status' => 'resolved',
        'reporter_id' => (string) $reporter->getKey(),
        'handled_by' => (string) $admin->getKey(),
        'resolution_note' => 'Resolved through admin API.',
        'clear_evidence' => true,
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.entity_type', 'reference')
        ->assertJsonPath('data.record.attributes.category', 'fake_reference')
        ->assertJsonPath('data.record.attributes.status', 'resolved')
        ->assertJsonPath('data.record.attributes.resolution_note', 'Resolved through admin API.');

    $report->refresh();

    expect($report->entity_type)->toBe('reference')
        ->and($report->entity_id)->toBe($reference->getKey())
        ->and($report->category)->toBe('fake_reference')
        ->and($report->status)->toBe('resolved')
        ->and($report->handled_by)->toBe($admin->getKey())
        ->and($report->resolution_note)->toBe('Resolved through admin API.')
        ->and($report->getMedia('evidence'))->toHaveCount(0);
});

it('exposes inspiration write schema and can create and update inspirations through the api', function () {
    $admin = adminApiUser('super_admin');

    Sanctum::actingAs($admin);

    $schema = $this->getJson('/api/v1/admin/inspirations/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'inspirations')
        ->assertJsonPath('data.schema.resource_key', 'inspirations')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.content_type', 'multipart/form-data')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/inspirations')
        ->json('data.schema');

    expect(collect($schema['fields'] ?? [])->pluck('name')->all())
        ->toContain('category', 'locale', 'title', 'content', 'source', 'main', 'clear_main');

    $createResponse = $this->withHeaders(['Accept' => 'application/json'])->post('/api/v1/admin/inspirations', [
        'category' => 'quran_quote',
        'locale' => 'ms',
        'title' => 'Admin API Inspiration',
        'content' => 'Admin API inspiration content.',
        'source' => 'Admin API Source',
        'is_active' => true,
        'main' => fakeGeneratedImageUpload('admin-api-inspiration-main.png', 1280, 720),
    ])->assertCreated();

    $inspirationRouteKey = (string) $createResponse->json('data.record.route_key');
    $inspiration = Inspiration::query()->findOrFail($inspirationRouteKey);

    expect($inspiration->getRawOriginal('category'))->toBe('quran_quote')
        ->and($inspiration->locale)->toBe('ms')
        ->and($inspiration->title)->toBe('Admin API Inspiration')
        ->and($inspiration->is_active)->toBeTrue()
        ->and($inspiration->getMedia('main'))->toHaveCount(1);

    $this->putJson('/api/v1/admin/inspirations/'.$inspirationRouteKey, [
        'category' => 'hadith_quote',
        'locale' => 'en',
        'title' => 'Admin API Inspiration Updated',
        'content' => [
            'type' => 'doc',
            'content' => [[
                'type' => 'paragraph',
                'content' => [[
                    'type' => 'text',
                    'text' => 'Updated inspiration content.',
                ]],
            ]],
        ],
        'source' => 'Updated API Source',
        'is_active' => false,
        'clear_main' => true,
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.category', 'hadith_quote')
        ->assertJsonPath('data.record.attributes.locale', 'en')
        ->assertJsonPath('data.record.attributes.title', 'Admin API Inspiration Updated')
        ->assertJsonPath('data.record.attributes.is_active', false);

    $inspiration->refresh();

    expect($inspiration->getRawOriginal('category'))->toBe('hadith_quote')
        ->and($inspiration->locale)->toBe('en')
        ->and($inspiration->title)->toBe('Admin API Inspiration Updated')
        ->and($inspiration->source)->toBe('Updated API Source')
        ->and($inspiration->is_active)->toBeFalse()
        ->and($inspiration->getMedia('main'))->toHaveCount(0);
});

it('exposes series write schema and can create and update series through the api', function () {
    $admin = adminApiUser('super_admin');
    $suffix = Str::lower((string) Str::ulid());

    Sanctum::actingAs($admin);

    $schema = $this->getJson('/api/v1/admin/series/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'series')
        ->assertJsonPath('data.schema.resource_key', 'series')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.content_type', 'multipart/form-data')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/series')
        ->json('data.schema');

    expect(collect($schema['fields'] ?? [])->pluck('name')->all())
        ->toContain('title', 'slug', 'description', 'visibility', 'languages', 'cover', 'gallery');

    $createResponse = $this->withHeaders(['Accept' => 'application/json'])->post('/api/v1/admin/series', [
        'title' => 'Admin API Series '.$suffix,
        'slug' => 'admin-api-series-'.$suffix,
        'description' => 'Series created through the admin API.',
        'visibility' => 'public',
        'is_active' => true,
        'cover' => fakeGeneratedImageUpload('series-cover.png', 1280, 720),
        'gallery' => [
            fakeGeneratedImageUpload('series-gallery.png', 1280, 720),
        ],
    ])->assertCreated();

    $seriesRouteKey = (string) $createResponse->json('data.record.route_key');
    $series = Series::query()->findOrFail($seriesRouteKey);

    expect($series->title)->toBe('Admin API Series '.$suffix)
        ->and($series->slug)->toBe('admin-api-series-'.$suffix)
        ->and($series->getMedia('cover'))->toHaveCount(1)
        ->and($series->getMedia('gallery'))->toHaveCount(1);

    $this->putJson('/api/v1/admin/series/'.$seriesRouteKey, [
        'title' => 'Admin API Series Updated '.$suffix,
        'slug' => 'admin-api-series-updated-'.$suffix,
        'description' => 'Series updated through the admin API.',
        'visibility' => 'private',
        'is_active' => false,
        'languages' => [],
        'clear_cover' => true,
        'clear_gallery' => true,
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.title', 'Admin API Series Updated '.$suffix)
        ->assertJsonPath('data.record.attributes.slug', 'admin-api-series-updated-'.$suffix)
        ->assertJsonPath('data.record.attributes.visibility', 'private')
        ->assertJsonPath('data.record.attributes.is_active', false);

    $series->refresh();

    expect($series->title)->toBe('Admin API Series Updated '.$suffix)
        ->and($series->slug)->toBe('admin-api-series-updated-'.$suffix)
        ->and($series->visibility)->toBe('private')
        ->and($series->is_active)->toBeFalse()
        ->and($series->getMedia('cover'))->toHaveCount(0)
        ->and($series->getMedia('gallery'))->toHaveCount(0);
});

it('exposes space write schema and can create and update spaces through the api', function () {
    $admin = adminApiUser('super_admin');
    $suffix = Str::lower((string) Str::ulid());
    $firstInstitution = Institution::factory()->create();
    $secondInstitution = Institution::factory()->create();

    Sanctum::actingAs($admin);

    $schema = $this->getJson('/api/v1/admin/spaces/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'spaces')
        ->assertJsonPath('data.schema.resource_key', 'spaces')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.content_type', 'application/json')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/spaces')
        ->json('data.schema');

    expect(collect($schema['fields'] ?? [])->pluck('name')->all())
        ->toContain('name', 'slug', 'capacity', 'is_active', 'institutions');

    $createResponse = $this->postJson('/api/v1/admin/spaces', [
        'name' => 'Admin API Space '.$suffix,
        'slug' => 'admin-api-space-'.$suffix,
        'capacity' => 80,
        'is_active' => true,
        'institutions' => [(string) $firstInstitution->getKey()],
    ])->assertCreated();

    $spaceRouteKey = (string) $createResponse->json('data.record.route_key');
    $space = Space::query()->findOrFail($spaceRouteKey);

    expect($space->name)->toBe('Admin API Space '.$suffix)
        ->and($space->slug)->toBe('admin-api-space-'.$suffix)
        ->and($space->capacity)->toBe(80)
        ->and($space->institutions()->pluck('institutions.id')->all())->toContain($firstInstitution->getKey());

    $this->putJson('/api/v1/admin/spaces/'.$spaceRouteKey, [
        'name' => 'Admin API Space Updated '.$suffix,
        'slug' => 'admin-api-space-updated-'.$suffix,
        'capacity' => 120,
        'is_active' => false,
        'institutions' => [(string) $secondInstitution->getKey()],
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.name', 'Admin API Space Updated '.$suffix)
        ->assertJsonPath('data.record.attributes.slug', 'admin-api-space-updated-'.$suffix)
        ->assertJsonPath('data.record.attributes.capacity', 120)
        ->assertJsonPath('data.record.attributes.is_active', false);

    $space->refresh();

    expect($space->name)->toBe('Admin API Space Updated '.$suffix)
        ->and($space->slug)->toBe('admin-api-space-updated-'.$suffix)
        ->and($space->capacity)->toBe(120)
        ->and($space->is_active)->toBeFalse()
        ->and($space->institutions()->pluck('institutions.id')->all())->toContain($secondInstitution->getKey())
        ->and($space->institutions()->pluck('institutions.id')->all())->not->toContain($firstInstitution->getKey());
});

it('exposes donation channel write schema and can create and update donation channels through the api', function () {
    $admin = adminApiUser('super_admin');
    $institution = Institution::factory()->create();

    Sanctum::actingAs($admin);

    $schema = $this->getJson('/api/v1/admin/donation-channels/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'donation-channels')
        ->assertJsonPath('data.schema.resource_key', 'donation-channels')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.content_type', 'multipart/form-data')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/donation-channels')
        ->json('data.schema');

    expect(collect($schema['fields'] ?? [])->pluck('name')->all())
        ->toContain('donatable_type', 'donatable_id', 'recipient', 'method', 'status', 'qr', 'clear_qr');

    $createResponse = $this->withHeaders(['Accept' => 'application/json'])->post('/api/v1/admin/donation-channels', [
        'donatable_type' => 'institution',
        'donatable_id' => (string) $institution->getKey(),
        'label' => 'Tabung Jumaat API',
        'recipient' => 'Masjid API',
        'method' => 'bank_account',
        'bank_code' => 'MBB',
        'bank_name' => 'Maybank',
        'account_number' => '123456789012',
        'reference_note' => 'Primary donation channel.',
        'status' => 'verified',
        'is_default' => true,
        'qr' => fakeGeneratedImageUpload('admin-api-donation-channel-qr.png', 640, 640),
    ])->assertCreated();

    $donationChannelRouteKey = (string) $createResponse->json('data.record.route_key');
    $donationChannel = DonationChannel::query()->findOrFail($donationChannelRouteKey);

    expect($donationChannel->donatable_type)->toBe('institution')
        ->and($donationChannel->donatable_id)->toBe($institution->getKey())
        ->and($donationChannel->label)->toBe('Tabung Jumaat API')
        ->and($donationChannel->recipient)->toBe('Masjid API')
        ->and($donationChannel->method)->toBe('bank_account')
        ->and($donationChannel->bank_name)->toBe('Maybank')
        ->and($donationChannel->account_number)->toBe('123456789012')
        ->and($donationChannel->duitnow_type)->toBeNull()
        ->and($donationChannel->ewallet_provider)->toBeNull()
        ->and($donationChannel->is_default)->toBeTrue()
        ->and($donationChannel->getMedia('qr'))->toHaveCount(1);

    $this->getJson('/api/v1/admin/donation-channels/schema?operation=update&recordKey='.$donationChannelRouteKey)
        ->assertOk()
        ->assertJsonPath('data.schema.method', 'PUT')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/donation-channels/'.$donationChannelRouteKey)
        ->assertJsonPath('data.schema.defaults.donatable_type', 'institution')
        ->assertJsonPath('data.schema.defaults.donatable_id', (string) $institution->getKey())
        ->assertJsonPath('data.schema.defaults.clear_qr', false);

    $this->putJson('/api/v1/admin/donation-channels/'.$donationChannelRouteKey, [
        'donatable_type' => 'institution',
        'donatable_id' => (string) $institution->getKey(),
        'label' => 'Tabung Jumaat API Updated',
        'recipient' => 'Masjid API Updated',
        'method' => 'ewallet',
        'ewallet_provider' => 'tng',
        'ewallet_handle' => '60123456789',
        'ewallet_qr_payload' => 'duitnow://payment/majlisilmu',
        'reference_note' => 'Fallback donation channel.',
        'status' => 'inactive',
        'is_default' => false,
        'clear_qr' => true,
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.label', 'Tabung Jumaat API Updated')
        ->assertJsonPath('data.record.attributes.method', 'ewallet')
        ->assertJsonPath('data.record.attributes.status', 'inactive');

    $donationChannel->refresh();

    expect($donationChannel->label)->toBe('Tabung Jumaat API Updated')
        ->and($donationChannel->recipient)->toBe('Masjid API Updated')
        ->and($donationChannel->method)->toBe('ewallet')
        ->and($donationChannel->bank_code)->toBeNull()
        ->and($donationChannel->bank_name)->toBeNull()
        ->and($donationChannel->account_number)->toBeNull()
        ->and($donationChannel->ewallet_provider)->toBe('tng')
        ->and($donationChannel->ewallet_handle)->toBe('60123456789')
        ->and($donationChannel->ewallet_qr_payload)->toBe('duitnow://payment/majlisilmu')
        ->and($donationChannel->status)->toBe('inactive')
        ->and($donationChannel->is_default)->toBeFalse()
        ->and($donationChannel->getMedia('qr'))->toHaveCount(0);
});

it('exposes membership claim review schema and can approve claims through the admin workflow endpoints', function () {
    $admin = adminApiUser('super_admin');
    $institution = Institution::factory()->create();
    $claimant = User::factory()->create();
    $claim = MembershipClaim::factory()
        ->forInstitution($institution)
        ->create([
            'claimant_id' => $claimant->getKey(),
            'status' => 'pending',
        ]);

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/membership-claims/'.$claim->getRouteKey().'/review-schema')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'membership-claims')
        ->assertJsonPath('data.record.route_key', $claim->getRouteKey())
        ->assertJsonPath('data.schema.action', 'review_membership_claim')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/membership-claims/'.$claim->getRouteKey().'/review')
        ->assertJsonPath('data.schema.conditional_rules.0.field', 'granted_role_slug');

    $this->postJson('/api/v1/admin/membership-claims/'.$claim->getRouteKey().'/review', [
        'action' => 'approve',
        'granted_role_slug' => 'admin',
        'reviewer_note' => 'Approved through admin API.',
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.status', 'approved')
        ->assertJsonPath('data.record.attributes.granted_role_slug', 'admin')
        ->assertJsonPath('data.record.attributes.reviewer_note', 'Approved through admin API.');

    expect($claim->fresh()?->status->value)->toBe('approved')
        ->and($claim->fresh()?->granted_role_slug)->toBe('admin')
        ->and($claim->fresh()?->reviewer_id)->toBe($admin->getKey())
        ->and($institution->fresh()->members()->whereKey($claimant->getKey())->exists())->toBeTrue();
});

it('exposes admin speaker write schema and can create and update speakers through the api', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $schema = $this->getJson('/api/v1/admin/speakers/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.schema.resource_key', 'speakers')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.slug_behavior', 'auto_managed')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/speakers')
        ->json('data.schema');

    $speakerFields = collect($schema['fields'] ?? [])->pluck('name')->all();

    expect(collect($schema['catalogs'] ?? [])->pluck('field')->all())
        ->toContain('address.country_id')
        ->toContain('address.state_id', 'address.district_id', 'address.subdistrict_id')
        ->and($speakerFields)->toContain('address.country_id')
        ->and($speakerFields)->not->toContain('address.country_code', 'address.country_key')
        ->and(collect($schema['conditional_rules'] ?? [])->pluck('field')->all())->not->toContain('address.country_id');

    $createResponse = $this->postJson('/api/v1/admin/speakers', [
        'name' => 'Admin API Created Speaker',
        'gender' => 'male',
        'status' => 'verified',
        'is_freelance' => false,
        'is_active' => true,
        'address' => [
            'country_id' => 132,
        ],
    ])->assertCreated();

    $speakerRouteKey = (string) $createResponse->json('data.record.route_key');
    $speaker = Speaker::query()->findOrFail($speakerRouteKey);

    expect($speaker->name)->toBe('Admin API Created Speaker')
        ->and($speaker->slug)->toBe('admin-api-created-speaker-my')
        ->and($speaker->status)->toBe('verified')
        ->and($speaker->allow_public_event_submission)->toBeTrue();

    $this->putJson('/api/v1/admin/speakers/'.$speakerRouteKey, [
        'name' => 'Admin API Updated Speaker',
        'gender' => 'male',
        'honorific' => ['dato'],
        'pre_nominal' => ['dr', 'prof_madya'],
        'post_nominal' => ['BA', 'PhD', 'HONS'],
        'status' => 'verified',
        'is_freelance' => true,
        'job_title' => 'Imam',
        'is_active' => true,
        'allow_public_event_submission' => true,
        'address' => [
            'country_id' => 132,
        ],
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.name', 'Admin API Updated Speaker')
        ->assertJsonPath('data.record.attributes.slug', 'prof-madya-dato-dr-admin-api-updated-speaker-phd-ba-hons-my')
        ->assertJsonPath('data.record.attributes.job_title', 'Imam');
});

it('requires explicit country and still prohibits detailed address fields when creating speakers through the admin api', function () {
    $admin = adminApiUser('super_admin');

    Sanctum::actingAs($admin);

    $this->postJson('/api/v1/admin/speakers', [
        'name' => 'Admin API Missing Speaker Country',
        'gender' => 'male',
        'status' => 'verified',
        'is_freelance' => false,
        'is_active' => true,
        'address' => [],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors([
            'address.country_id',
        ]);

    $this->postJson('/api/v1/admin/speakers', [
        'name' => 'Admin API Invalid Speaker Address',
        'gender' => 'male',
        'status' => 'verified',
        'is_freelance' => false,
        'is_active' => true,
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

it('returns fresh speaker address data on admin GET requests after updates', function () {
    $firstFixtures = ensureAdminApiSubdistrictFixtures();
    $secondFixtures = ensureAdminApiSubdistrictFixtures();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $createResponse = $this->postJson('/api/v1/admin/speakers', [
        'name' => 'Admin API Address Freshness Speaker',
        'gender' => 'male',
        'status' => 'verified',
        'is_freelance' => false,
        'is_active' => true,
        'address' => [
            'country_id' => $firstFixtures['country_id'],
            'state_id' => $firstFixtures['state_id'],
            'district_id' => $firstFixtures['district_id'],
        ],
    ])->assertCreated();

    $speakerRouteKey = (string) $createResponse->json('data.record.route_key');

    $this->getJson('/api/v1/admin/speakers/'.$speakerRouteKey)
        ->assertOk()
        ->assertJsonPath('data.record.attributes.address.country_id', $firstFixtures['country_id'])
        ->assertJsonMissingPath('data.record.attributes.address.line1')
        ->assertJsonMissingPath('data.record.attributes.address.google_maps_url')
        ->assertJsonPath('data.record.attributes.address.state_id', $firstFixtures['state_id'])
        ->assertJsonPath('data.record.attributes.address.district_id', $firstFixtures['district_id']);

    $this->putJson('/api/v1/admin/speakers/'.$speakerRouteKey, [
        'name' => 'Admin API Address Freshness Speaker',
        'gender' => 'male',
        'status' => 'verified',
        'is_freelance' => false,
        'is_active' => true,
        'address' => [
            'country_id' => $secondFixtures['country_id'],
            'state_id' => $secondFixtures['state_id'],
            'district_id' => $secondFixtures['district_id'],
        ],
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.address.country_id', $secondFixtures['country_id'])
        ->assertJsonMissingPath('data.record.attributes.address.line1')
        ->assertJsonMissingPath('data.record.attributes.address.google_maps_url')
        ->assertJsonPath('data.record.attributes.address.state_id', $secondFixtures['state_id'])
        ->assertJsonPath('data.record.attributes.address.district_id', $secondFixtures['district_id']);

    $this->getJson('/api/v1/admin/speakers/'.$speakerRouteKey)
        ->assertOk()
        ->assertJsonPath('data.record.attributes.address.country_id', $secondFixtures['country_id'])
        ->assertJsonMissingPath('data.record.attributes.address.line1')
        ->assertJsonMissingPath('data.record.attributes.address.google_maps_url')
        ->assertJsonPath('data.record.attributes.address.state_id', $secondFixtures['state_id'])
        ->assertJsonPath('data.record.attributes.address.district_id', $secondFixtures['district_id']);

    $this->getJson('/api/v1/admin/speakers?search=Admin%20API%20Address%20Freshness%20Speaker')
        ->assertOk()
        ->assertJsonPath('data.0.attributes.address.country_id', $secondFixtures['country_id'])
        ->assertJsonMissingPath('data.0.attributes.address.line1')
        ->assertJsonMissingPath('data.0.attributes.address.google_maps_url')
        ->assertJsonPath('data.0.attributes.address.state_id', $secondFixtures['state_id'])
        ->assertJsonPath('data.0.attributes.address.district_id', $secondFixtures['district_id']);
});

it('allows sparse venue address updates without resending the existing country through the admin api', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $createResponse = $this->postJson('/api/v1/admin/venues', [
        'name' => 'Admin API Sparse Venue Country',
        'type' => 'dewan',
        'status' => 'verified',
        'is_active' => true,
        'address' => [
            'country_id' => 132,
            'line1' => 'Alamat Asal',
        ],
    ])->assertCreated();

    $venueRouteKey = (string) $createResponse->json('data.record.route_key');

    $this->putJson('/api/v1/admin/venues/'.$venueRouteKey, [
        'name' => 'Admin API Sparse Venue Country',
        'type' => 'dewan',
        'status' => 'verified',
        'is_active' => true,
        'address' => [
            'line1' => 'Alamat Terkini Tanpa Country',
        ],
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.address.country_id', 132)
        ->assertJsonPath('data.record.attributes.address.line1', 'Alamat Terkini Tanpa Country');

    expect(Venue::query()->findOrFail($venueRouteKey)->addressModel?->country_id)->toBe(132)
        ->and(Venue::query()->findOrFail($venueRouteKey)->addressModel?->line1)->toBe('Alamat Terkini Tanpa Country');
});

it('exposes admin institution write schema and can create and update institutions through the api', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $institutionSchema = $this->getJson('/api/v1/admin/institutions/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.schema.resource_key', 'institutions')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/institutions')
        ->json('data.schema');

    expect(collect($institutionSchema['fields'] ?? [])->pluck('name')->all())
        ->toContain('address.country_id')
        ->and(collect($institutionSchema['fields'] ?? [])->pluck('name')->all())->not->toContain('address.country_code', 'address.country_key')
        ->and(collect($institutionSchema['conditional_rules'] ?? [])->pluck('field')->all())->not->toContain('address.country_id');

    $createResponse = $this->postJson('/api/v1/admin/institutions', [
        'name' => 'Admin API Institution',
        'nickname' => 'API Surau',
        'type' => 'masjid',
        'status' => 'verified',
        'is_active' => true,
        'address' => [
            'country_id' => 132,
        ],
    ])->assertCreated();

    $institutionRouteKey = (string) $createResponse->json('data.record.route_key');
    $institution = Institution::query()->findOrFail($institutionRouteKey);

    expect($institution->display_name)->toBe('Admin API Institution (API Surau)')
        ->and($institution->status)->toBe('verified')
        ->and($institution->allow_public_event_submission)->toBeTrue();

    $this->putJson('/api/v1/admin/institutions/'.$institutionRouteKey, [
        'name' => 'Admin API Institution Updated',
        'nickname' => 'API Masjid',
        'type' => 'masjid',
        'status' => 'pending',
        'is_active' => true,
        'allow_public_event_submission' => true,
        'address' => [
            'country_id' => 132,
        ],
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.name', 'Admin API Institution Updated')
        ->assertJsonPath('data.record.attributes.nickname', 'API Masjid');
});

it('preserves institution address line1 when sparse map fields are updated through the admin api', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $createResponse = $this->postJson('/api/v1/admin/institutions', [
        'name' => 'Admin API Sparse Institution',
        'type' => 'masjid',
        'status' => 'verified',
        'is_active' => true,
        'address' => [
            'country_id' => 132,
            'line1' => 'Alamat Asal Institusi',
        ],
    ])->assertCreated();

    $institutionRouteKey = (string) $createResponse->json('data.record.route_key');

    $this->putJson('/api/v1/admin/institutions/'.$institutionRouteKey, [
        'name' => 'Admin API Sparse Institution',
        'type' => 'masjid',
        'status' => 'verified',
        'address' => [
            'google_maps_url' => 'https://example.com/maps/institution',
            'lat' => 3.123456,
            'lng' => 101.654321,
        ],
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.address.line1', 'Alamat Asal Institusi')
        ->assertJsonPath('data.record.attributes.address.google_maps_url', fn (string $url): bool => str_contains($url, 'google.com/maps/search'))
        ->assertJsonPath('data.record.attributes.address.lat', 3.123456)
        ->assertJsonPath('data.record.attributes.address.lng', 101.654321);

    $institution = Institution::query()->findOrFail($institutionRouteKey);

    expect($institution->addressModel)->not->toBeNull()
        ->and($institution->addressModel?->line1)->toBe('Alamat Asal Institusi')
        ->and($institution->addressModel?->google_maps_url)->toContain('google.com/maps/search')
        ->and($institution->addressModel?->lat)->toBe(3.123456)
        ->and($institution->addressModel?->lng)->toBe(101.654321);
});

it('requires a record key when requesting an admin update schema', function () {
    $admin = adminApiUser('super_admin');

    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/institutions/schema?operation=update')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['recordKey'])
        ->assertJsonPath('error.code', 'validation_error');
});

it('exposes admin venue write schema and can create and update venues through the api', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/venues/meta')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'venues')
        ->assertJsonPath('data.resource.write_support.schema', true)
        ->assertJsonPath('data.resource.write_support.store', true)
        ->assertJsonPath('data.resource.write_support.update', true)
        ->assertJsonPath('data.resource.api_routes.collection', '/api/v1/admin/venues')
        ->assertJsonPath('data.resource.api_routes.schema', '/api/v1/admin/venues/schema');

    $this->getJson('/api/v1/admin/venues/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.schema.resource_key', 'venues')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/venues')
        ->assertJsonPath('data.schema.content_type', 'multipart/form-data')
        ->assertJsonPath('data.schema.defaults.type', 'dewan')
        ->assertJsonPath('data.schema.defaults.is_active', true)
        ->assertJsonPath('data.schema.catalogs.0.field', 'address.country_id');

    $createResponse = $this->postJson('/api/v1/admin/venues', [
        'name' => 'Admin API Venue',
        'type' => 'dewan',
        'status' => 'verified',
        'is_active' => true,
        'facilities' => ['parking', 'oku'],
        'address' => [
            'country_id' => 132,
            'line1' => 'Dewan Serbaguna API',
        ],
        'contacts' => [
            [
                'category' => 'phone',
                'value' => '0312345678',
                'type' => 'main',
                'is_public' => true,
            ],
        ],
        'social_media' => [
            [
                'platform' => 'website',
                'url' => 'https://example.com/venues/admin-api-venue',
            ],
        ],
    ])->assertCreated();

    $venueRouteKey = (string) $createResponse->json('data.record.route_key');
    $venue = Venue::query()->with(['address', 'contacts', 'socialMedia'])->findOrFail($venueRouteKey);

    expect($venue->name)->toBe('Admin API Venue')
        ->and($venue->slug)->toBe('admin-api-venue-my')
        ->and($venue->status)->toBe('verified')
        ->and($venue->is_active)->toBeTrue()
        ->and($venue->facilities)->toBe([
            'parking' => true,
            'oku' => true,
        ])
        ->and($venue->addressModel?->country_id)->toBe(132)
        ->and($venue->contacts)->toHaveCount(1)
        ->and($venue->contacts->first()?->value)->toBe('0312345678')
        ->and($venue->socialMedia)->toHaveCount(1)
        ->and($venue->socialMedia->first()?->platform)->toBe('website');

    $this->putJson('/api/v1/admin/venues/'.$venueRouteKey, [
        'name' => 'Admin API Venue Updated',
        'type' => 'auditorium',
        'status' => 'pending',
        'is_active' => false,
        'facilities' => ['women_section', 'ablution_area'],
        'address' => [
            'country_id' => 132,
            'line1' => 'Auditorium API Baharu',
        ],
        'contacts' => [
            [
                'category' => 'whatsapp',
                'value' => '60123456789',
                'type' => 'work',
                'is_public' => false,
            ],
        ],
        'social_media' => [
            [
                'platform' => 'facebook',
                'url' => 'https://facebook.com/admin-api-venue-updated',
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.name', 'Admin API Venue Updated')
        ->assertJsonPath('data.record.attributes.slug', 'admin-api-venue-updated-my')
        ->assertJsonPath('data.record.attributes.type', 'auditorium')
        ->assertJsonPath('data.record.attributes.is_active', false);

    $venue->refresh()->load(['address', 'contacts', 'socialMedia']);

    expect($venue->name)->toBe('Admin API Venue Updated')
        ->and($venue->slug)->toBe('admin-api-venue-updated-my')
        ->and($venue->getRawOriginal('type'))->toBe('auditorium')
        ->and($venue->status)->toBe('pending')
        ->and($venue->is_active)->toBeFalse()
        ->and($venue->facilities)->toBe([
            'women_section' => true,
            'ablution_area' => true,
        ])
        ->and($venue->addressModel?->line1)->toBe('Auditorium API Baharu')
        ->and($venue->contacts)->toHaveCount(1)
        ->and($venue->contacts->first()?->getRawOriginal('category'))->toBe('whatsapp')
        ->and($venue->socialMedia)->toHaveCount(1)
        ->and($venue->socialMedia->first()?->getRawOriginal('platform'))->toBe('facebook');

    $this->putJson('/api/v1/admin/venues/'.$venueRouteKey, [
        'name' => 'Admin API Venue Updated',
        'type' => 'auditorium',
        'status' => 'pending',
        'is_active' => false,
        'address' => [
            'google_maps_url' => 'https://example.com/venues/admin-api-venue-updated',
            'lat' => 3.147,
            'lng' => 101.694,
        ],
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.address.line1', 'Auditorium API Baharu')
        ->assertJsonPath('data.record.attributes.address.google_maps_url', fn (string $url): bool => str_contains($url, 'google.com/maps/search'))
        ->assertJsonPath('data.record.attributes.address.lat', 3.147)
        ->assertJsonPath('data.record.attributes.address.lng', 101.694);
});

it('lists admin geography catalogs and exposes catalog metadata through admin write schemas', function () {
    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $fixtures = ensureAdminApiSubdistrictFixtures();

    $subdistrictId = DB::table('subdistricts')->insertGetId([
        'country_id' => $fixtures['country_id'],
        'state_id' => $fixtures['state_id'],
        'district_id' => $fixtures['district_id'],
        'name' => 'Admin API Catalog Subdistrict',
        'country_code' => 'MY',
    ]);

    $this->getJson('/api/v1/admin/catalogs/countries')
        ->assertOk()
        ->assertJsonFragment([
            'id' => $fixtures['country_id'],
            'label' => 'Malaysia',
            'iso2' => 'MY',
            'key' => 'malaysia',
        ]);

    $this->getJson('/api/v1/admin/catalogs/states?country_id='.$fixtures['country_id'])
        ->assertOk()
        ->assertJsonFragment([
            'id' => $fixtures['state_id'],
        ]);

    $this->getJson('/api/v1/admin/catalogs/districts?state_id='.$fixtures['state_id'])
        ->assertOk()
        ->assertJsonFragment([
            'id' => $fixtures['district_id'],
        ]);

    $this->getJson('/api/v1/admin/catalogs/subdistricts?district_id='.$fixtures['district_id'])
        ->assertOk()
        ->assertJsonFragment([
            'id' => $subdistrictId,
            'label' => 'Admin API Catalog Subdistrict',
        ]);

    $institutionSchema = $this->getJson('/api/v1/admin/institutions/schema?operation=create')
        ->assertOk()
        ->json('data.schema.catalogs');

    $institutionCatalogs = collect(is_array($institutionSchema) ? $institutionSchema : [])->keyBy('field');

    expect($institutionCatalogs->get('address.country_id')['endpoint'] ?? null)->toBe('/api/v1/admin/catalogs/countries')
        ->and($institutionCatalogs->get('address.state_id')['query']['country_id'] ?? null)->toBe('{address.country_id}')
        ->and($institutionCatalogs->get('address.district_id')['query']['state_id'] ?? null)->toBe('{address.state_id}')
        ->and($institutionCatalogs->get('address.subdistrict_id')['query']['district_id'] ?? null)->toBe('{address.district_id}');

    $subdistrictSchema = $this->getJson('/api/v1/admin/subdistricts/schema?operation=create')
        ->assertOk()
        ->json('data.schema.catalogs');

    $subdistrictCatalogs = collect(is_array($subdistrictSchema) ? $subdistrictSchema : [])->keyBy('field');

    expect($subdistrictCatalogs->get('country_id')['endpoint'] ?? null)->toBe('/api/v1/admin/catalogs/countries')
        ->and($subdistrictCatalogs->get('state_id')['query']['country_id'] ?? null)->toBe('{country_id}')
        ->and($subdistrictCatalogs->get('district_id')['query']['state_id'] ?? null)->toBe('{state_id}');
});

it('exposes admin reference write schema and can create and update references through the api', function () {
    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $this->getJson('/api/v1/admin/references/meta')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'references')
        ->assertJsonPath('data.resource.write_support.schema', true)
        ->assertJsonPath('data.resource.write_support.store', true)
        ->assertJsonPath('data.resource.write_support.update', true)
        ->assertJsonPath('data.resource.api_routes.collection', '/api/v1/admin/references')
        ->assertJsonPath('data.resource.api_routes.schema', '/api/v1/admin/references/schema');

    $this->getJson('/api/v1/admin/references/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.schema.resource_key', 'references')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/references')
        ->assertJsonPath('data.schema.content_type', 'multipart/form-data')
        ->assertJsonPath('data.schema.defaults.type', 'book');

    $createResponse = $this->postJson('/api/v1/admin/references', [
        'title' => 'Admin API Reference',
        'author' => 'Admin API Author',
        'type' => 'book',
        'publication_year' => '2024',
        'publisher' => 'Admin API Press',
        'description' => 'Admin API reference description.',
        'is_canonical' => true,
        'status' => 'verified',
        'is_active' => true,
        'social_media' => [
            [
                'platform' => 'website',
                'url' => 'https://example.com/references/admin-api-reference',
            ],
        ],
    ])->assertCreated();

    $referenceRouteKey = (string) $createResponse->json('data.record.route_key');
    $reference = Reference::query()->with('socialMedia')->where('slug', $referenceRouteKey)->firstOrFail();
    $referenceId = (string) $reference->getKey();

    expect($reference->title)->toBe('Admin API Reference')
        ->and($reference->slug)->toBe('admin-api-reference')
        ->and($reference->is_canonical)->toBeTrue()
        ->and($reference->status)->toBe('verified')
        ->and($reference->is_active)->toBeTrue()
        ->and($reference->socialMedia)->toHaveCount(1)
        ->and($reference->socialMedia->first()?->platform)->toBe('website');

    $this->getJson('/api/v1/admin/references/'.$referenceRouteKey)
        ->assertOk()
        ->assertJsonPath('data.record.route_key', $referenceRouteKey)
        ->assertJsonPath('data.record.attributes.slug', 'admin-api-reference');

    $this->getJson('/api/v1/admin/references/'.$referenceId)->assertNotFound();

    $this->getJson('/api/v1/admin/references/schema?operation=update&recordKey='.$referenceRouteKey)
        ->assertOk()
        ->assertJsonPath('data.schema.method', 'PUT')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/references/'.$referenceRouteKey)
        ->assertJsonPath('data.schema.defaults.title', 'Admin API Reference');

    $this->putJson('/api/v1/admin/references/'.$referenceRouteKey, [
        'title' => 'Admin API Reference Updated',
        'author' => 'Admin API Editor',
        'type' => 'article',
        'publication_year' => null,
        'publisher' => 'Admin API Review',
        'description' => 'Updated admin API reference description.',
        'is_canonical' => false,
        'status' => 'pending',
        'is_active' => false,
        'social_media' => [
            [
                'platform' => 'youtube',
                'url' => 'https://youtube.com/watch?v=admin-api-reference-updated',
            ],
        ],
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.title', 'Admin API Reference Updated')
        ->assertJsonPath('data.record.attributes.slug', 'admin-api-reference-updated')
        ->assertJsonPath('data.record.attributes.type', 'article');

    $reference->refresh()->load('socialMedia');

    expect($reference->title)->toBe('Admin API Reference Updated')
        ->and($reference->slug)->toBe('admin-api-reference-updated')
        ->and($reference->type)->toBe('article')
        ->and($reference->publication_year)->toBeNull()
        ->and($reference->publisher)->toBe('Admin API Review')
        ->and($reference->is_canonical)->toBeFalse()
        ->and($reference->status)->toBe('pending')
        ->and($reference->is_active)->toBeFalse()
        ->and($reference->socialMedia)->toHaveCount(1)
        ->and($reference->socialMedia->first()?->platform)->toBe('youtube');

    $this->getJson('/api/v1/admin/references/'.$reference->getRouteKey())
        ->assertOk()
        ->assertJsonPath('data.record.route_key', 'admin-api-reference-updated')
        ->assertJsonPath('data.record.attributes.slug', 'admin-api-reference-updated');
});

it('exposes admin subdistrict write schema and can create and update subdistricts through the api', function () {
    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $fixtures = ensureAdminApiSubdistrictFixtures();

    $this->getJson('/api/v1/admin/subdistricts/meta')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'subdistricts')
        ->assertJsonPath('data.resource.write_support.schema', true)
        ->assertJsonPath('data.resource.write_support.store', true)
        ->assertJsonPath('data.resource.write_support.update', true)
        ->assertJsonPath('data.resource.api_routes.collection', '/api/v1/admin/subdistricts')
        ->assertJsonPath('data.resource.api_routes.schema', '/api/v1/admin/subdistricts/schema');

    $this->getJson('/api/v1/admin/subdistricts/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.schema.resource_key', 'subdistricts')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/subdistricts')
        ->assertJsonPath('data.schema.content_type', 'application/json')
        ->assertJsonPath('data.schema.conditional_rules.0.field', 'district_id');

    $createResponse = $this->postJson('/api/v1/admin/subdistricts', [
        'country_id' => $fixtures['country_id'],
        'state_id' => $fixtures['federal_state_id'],
        'district_id' => null,
        'name' => 'Admin API Federal Territory Subdistrict',
    ])->assertCreated();

    $subdistrictRouteKey = (string) $createResponse->json('data.record.route_key');
    $subdistrict = Subdistrict::query()->findOrFail($subdistrictRouteKey);

    expect((int) $subdistrict->country_id)->toBe($fixtures['country_id'])
        ->and((int) $subdistrict->state_id)->toBe($fixtures['federal_state_id'])
        ->and($subdistrict->district_id)->toBeNull()
        ->and($subdistrict->country_code)->toBe('MY');

    $this->getJson('/api/v1/admin/subdistricts/schema?operation=update&recordKey='.$subdistrictRouteKey)
        ->assertOk()
        ->assertJsonPath('data.schema.method', 'PUT')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/subdistricts/'.$subdistrictRouteKey)
        ->assertJsonPath('data.schema.defaults.country_id', $fixtures['country_id'])
        ->assertJsonPath('data.schema.defaults.state_id', $fixtures['federal_state_id'])
        ->assertJsonPath('data.schema.defaults.district_id', null)
        ->assertJsonPath('data.schema.defaults.name', 'Admin API Federal Territory Subdistrict');

    $this->putJson('/api/v1/admin/subdistricts/'.$subdistrictRouteKey, [
        'country_id' => $fixtures['country_id'],
        'state_id' => $fixtures['state_id'],
        'district_id' => $fixtures['district_id'],
        'name' => 'Admin API Updated Subdistrict',
    ])->assertOk()
        ->assertJsonPath('data.record.attributes.name', 'Admin API Updated Subdistrict')
        ->assertJsonPath('data.record.attributes.district_id', $fixtures['district_id']);

    $subdistrict->refresh();

    expect((int) $subdistrict->state_id)->toBe($fixtures['state_id'])
        ->and((int) $subdistrict->district_id)->toBe($fixtures['district_id'])
        ->and($subdistrict->name)->toBe('Admin API Updated Subdistrict');
});

it('clamps admin collection per_page values to the supported maximum', function () {
    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    Speaker::factory()->count(110)->create();

    $this->getJson('/api/v1/admin/speakers?per_page=500')
        ->assertOk()
        ->assertJsonPath('meta.pagination.per_page', 100)
        ->assertJsonCount(100, 'data');
});

it('requires district_id for non-federal-territory subdistrict writes', function () {
    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $fixtures = ensureAdminApiSubdistrictFixtures();

    $this->postJson('/api/v1/admin/subdistricts', [
        'country_id' => $fixtures['country_id'],
        'state_id' => $fixtures['state_id'],
        'district_id' => null,
        'name' => 'Admin API Invalid Subdistrict',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['district_id']);
});

it('exposes admin event write schema and can create and update events through the api', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $reference = Reference::factory()->verified()->create();
    $series = Series::factory()->create();
    $domainTag = Tag::factory()->domain()->verified()->create();
    $disciplineTag = Tag::factory()->discipline()->verified()->create();
    $sourceTag = Tag::factory()->source()->verified()->create();

    $this->getJson('/api/v1/admin/events/meta')
        ->assertOk()
        ->assertJsonPath('data.resource.key', 'events')
        ->assertJsonPath('data.resource.write_support.schema', true)
        ->assertJsonPath('data.resource.write_support.store', true)
        ->assertJsonPath('data.resource.write_support.update', true)
        ->assertJsonPath('data.resource.api_routes.collection', '/api/v1/admin/events')
        ->assertJsonPath('data.resource.api_routes.schema', '/api/v1/admin/events/schema');

    $this->getJson('/api/v1/admin/events/schema?operation=create')
        ->assertOk()
        ->assertJsonPath('data.schema.resource_key', 'events')
        ->assertJsonPath('data.schema.method', 'POST')
        ->assertJsonPath('data.schema.endpoint', '/api/v1/admin/events')
        ->assertJsonPath('data.schema.content_type', 'multipart/form-data')
        ->assertJsonPath('data.schema.defaults.live_url', null);

    $createResponse = $this->postJson('/api/v1/admin/events', adminApiEventPayload([
        'institution' => $institution,
        'speaker' => $speaker,
        'reference' => $reference,
        'series' => $series,
        'domain_tag' => $domainTag,
        'discipline_tag' => $disciplineTag,
    ]))->assertCreated();

    $eventRouteKey = (string) $createResponse->json('data.record.route_key');
    $event = Event::query()
        ->with(['settings', 'references', 'series', 'tags', 'keyPeople'])
        ->findOrFail($eventRouteKey);

    expect($event->title)->toBe('Admin API Event Created')
        ->and($event->live_url)->toBeNull()
        ->and($event->organizer_type)->toBe(Institution::class)
        ->and($event->organizer_id)->toBe($institution->getKey())
        ->and($event->starts_at?->copy()->timezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i'))->toBe('2026-05-20 20:00')
        ->and($event->settings?->registration_required)->toBeTrue()
        ->and($event->settings?->registration_mode)->toBe(RegistrationMode::Event)
        ->and($event->references->pluck('id')->all())->toContain($reference->getKey())
        ->and($event->series->pluck('id')->all())->toContain($series->getKey())
        ->and($event->tags->pluck('id')->all())->toContain($domainTag->getKey(), $disciplineTag->getKey())
        ->and($event->keyPeople)->toHaveCount(2);

    $this->putJson('/api/v1/admin/events/'.$eventRouteKey, adminApiEventPayload([
        'institution' => $institution,
        'speaker' => $speaker,
        'reference' => $reference,
        'series' => $series,
        'domain_tag' => $domainTag,
        'discipline_tag' => $disciplineTag,
    ], [
        'title' => 'Admin API Event Updated',
        'event_date' => '2026-06-01',
        'prayer_time' => EventPrayerTime::SelepasMaghrib->value,
        'custom_time' => null,
        'end_time' => '22:30',
        'live_url' => 'https://youtube.com/watch?v=admin-api-event-live',
        'organizer_type' => Speaker::class,
        'organizer_id' => $speaker->getKey(),
        'institution_id' => null,
        'references' => [],
        'series' => [],
        'domain_tags' => [],
        'discipline_tags' => [],
        'source_tags' => [(string) $sourceTag->getKey()],
        'speakers' => [],
        'other_key_people' => [],
        'registration_required' => false,
    ]))->assertOk()
        ->assertJsonPath('data.record.attributes.title', 'Admin API Event Updated')
        ->assertJsonPath('data.record.attributes.live_url', 'https://youtube.com/watch?v=admin-api-event-live');

    $event->refresh()->load(['settings', 'references', 'series', 'tags', 'keyPeople']);

    expect($event->title)->toBe('Admin API Event Updated')
        ->and($event->live_url)->toBe('https://youtube.com/watch?v=admin-api-event-live')
        ->and($event->organizer_type)->toBe(Speaker::class)
        ->and($event->organizer_id)->toBe($speaker->getKey())
        ->and($event->starts_at?->copy()->timezone('Asia/Kuala_Lumpur')->format('Y-m-d H:i'))->toBe('2026-06-01 20:00')
        ->and($event->settings?->registration_required)->toBeFalse()
        ->and($event->references)->toHaveCount(0)
        ->and($event->series)->toHaveCount(0)
        ->and($event->tags->pluck('id')->all())->toContain($sourceTag->getKey())
        ->and($event->tags->pluck('id')->all())->not->toContain($domainTag->getKey(), $disciplineTag->getKey())
        ->and($event->keyPeople)->toHaveCount(0)
        ->and($event->slug)->toContain($speaker->slug);
});

it('rejects admin event writes that omit required speakers for speaker-led event types', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $reference = Reference::factory()->verified()->create();
    $series = Series::factory()->create();
    $domainTag = Tag::factory()->domain()->verified()->create();
    $disciplineTag = Tag::factory()->discipline()->verified()->create();

    $this->postJson('/api/v1/admin/events', adminApiEventPayload([
        'institution' => $institution,
        'speaker' => $speaker,
        'reference' => $reference,
        'series' => $series,
        'domain_tag' => $domainTag,
        'discipline_tag' => $disciplineTag,
    ], [
        'event_type' => [EventType::KuliahCeramah->value],
        'speakers' => [],
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['speakers']);
});

it('rejects admin event writes with organizer ids that do not match the organizer type', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $reference = Reference::factory()->verified()->create();
    $series = Series::factory()->create();
    $domainTag = Tag::factory()->domain()->verified()->create();
    $disciplineTag = Tag::factory()->discipline()->verified()->create();

    $this->postJson('/api/v1/admin/events', adminApiEventPayload([
        'institution' => $institution,
        'speaker' => $speaker,
        'reference' => $reference,
        'series' => $series,
        'domain_tag' => $domainTag,
        'discipline_tag' => $disciplineTag,
    ], [
        'organizer_type' => Institution::class,
        'organizer_id' => (string) $speaker->getKey(),
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['organizer_id']);
});

it('rejects admin event writes with conflicting location selections', function () {
    ensureAdminApiMalaysiaCountryExists();

    $admin = adminApiUser('super_admin');
    Sanctum::actingAs($admin);

    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $otherInstitution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $reference = Reference::factory()->verified()->create();
    $series = Series::factory()->create();
    $domainTag = Tag::factory()->domain()->verified()->create();
    $disciplineTag = Tag::factory()->discipline()->verified()->create();
    $venue = Venue::factory()->create();
    $space = Space::factory()->create();
    $otherInstitution->spaces()->attach($space);

    $this->postJson('/api/v1/admin/events', adminApiEventPayload([
        'institution' => $institution,
        'speaker' => $speaker,
        'reference' => $reference,
        'series' => $series,
        'domain_tag' => $domainTag,
        'discipline_tag' => $disciplineTag,
    ], [
        'venue_id' => (string) $venue->getKey(),
        'space_id' => (string) $space->getKey(),
    ]))->assertUnprocessable()
        ->assertJsonValidationErrors(['institution_id', 'venue_id', 'space_id']);
});

function ensureAdminApiMalaysiaCountryExists(): int
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

/**
 * @return array{country_id: int, state_id: int, district_id: int, federal_state_id: int}
 */
function ensureAdminApiSubdistrictFixtures(): array
{
    $countryId = ensureAdminApiMalaysiaCountryExists();
    $suffix = Str::lower(Str::random(8));

    $stateId = DB::table('states')->insertGetId([
        'country_id' => $countryId,
        'name' => 'Admin API Negeri '.$suffix,
        'country_code' => 'MY',
    ]);

    $districtId = DB::table('districts')->insertGetId([
        'country_id' => $countryId,
        'state_id' => $stateId,
        'name' => 'Admin API Daerah '.$suffix,
        'country_code' => 'MY',
    ]);

    $federalStateId = DB::table('states')
        ->where('country_id', $countryId)
        ->where('name', 'Kuala Lumpur')
        ->value('id');

    if (! is_int($federalStateId)) {
        $federalStateId = DB::table('states')->insertGetId([
            'country_id' => $countryId,
            'name' => 'Kuala Lumpur',
            'country_code' => 'MY',
        ]);
    }

    FederalTerritoryLocation::flushStateIdCache();

    return [
        'country_id' => $countryId,
        'state_id' => $stateId,
        'district_id' => $districtId,
        'federal_state_id' => $federalStateId,
    ];
}

function adminApiUser(string $role): User
{
    setPermissionsTeamId(null);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    if (! Role::query()->where('name', $role)->where('guard_name', 'web')->exists()) {
        $roleRecord = new Role;
        $roleRecord->forceFill([
            'id' => (string) Str::uuid(),
            'name' => $role,
            'guard_name' => 'web',
        ])->save();
    }

    $user = User::factory()->create();
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/**
 * @param  array{
 *     institution: Institution,
 *     speaker: Speaker,
 *     reference: Reference,
 *     series: Series,
 *     domain_tag: Tag,
 *     discipline_tag: Tag
 * }  $fixtures
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function adminApiEventPayload(array $fixtures, array $overrides = []): array
{
    return array_replace([
        'title' => 'Admin API Event Created',
        'event_date' => '2026-05-20',
        'prayer_time' => EventPrayerTime::LainWaktu->value,
        'custom_time' => '20:00',
        'end_time' => '22:00',
        'timezone' => 'Asia/Kuala_Lumpur',
        'event_format' => EventFormat::Hybrid->value,
        'visibility' => EventVisibility::Public->value,
        'event_url' => 'https://example.com/events/admin-api-event-created',
        'live_url' => null,
        'recording_url' => 'https://example.com/recordings/admin-api-event-created',
        'gender' => EventGenderRestriction::All->value,
        'age_group' => [EventAgeGroup::AllAges->value],
        'children_allowed' => true,
        'is_muslim_only' => true,
        'event_type' => [EventType::Other->value],
        'domain_tags' => [(string) $fixtures['domain_tag']->getKey()],
        'discipline_tags' => [(string) $fixtures['discipline_tag']->getKey()],
        'source_tags' => [],
        'issue_tags' => [],
        'references' => [(string) $fixtures['reference']->getKey()],
        'organizer_type' => Institution::class,
        'organizer_id' => (string) $fixtures['institution']->getKey(),
        'institution_id' => (string) $fixtures['institution']->getKey(),
        'series' => [(string) $fixtures['series']->getKey()],
        'speakers' => [(string) $fixtures['speaker']->getKey()],
        'other_key_people' => [
            [
                'role' => 'moderator',
                'name' => 'Admin API Moderator',
                'is_public' => true,
                'notes' => 'Will host the session.',
            ],
        ],
        'registration_required' => true,
        'registration_mode' => RegistrationMode::Event->value,
        'is_active' => true,
    ], $overrides);
}
