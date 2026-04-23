<?php

use AIArmada\Signals\Models\SignalEvent;
use App\Actions\Membership\AddMemberToSubject;
use App\Enums\ContributionRequestStatus;
use App\Enums\ContributionRequestType;
use App\Enums\ContributionSubjectType;
use App\Enums\EventAgeGroup;
use App\Enums\EventChangeSeverity;
use App\Enums\EventChangeStatus;
use App\Enums\EventChangeType;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\PrayerOffset;
use App\Enums\PrayerReference;
use App\Enums\RegistrationMode;
use App\Mcp\Prompts\DocumentationToolRoutingPrompt;
use App\Mcp\Resources\Docs\McpGuideResource;
use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\AdminCreateGitHubIssueTool;
use App\Mcp\Tools\Admin\AdminCreateRecordTool;
use App\Mcp\Tools\Admin\AdminDocumentationFetchTool;
use App\Mcp\Tools\Admin\AdminDocumentationSearchTool;
use App\Mcp\Tools\Admin\AdminGetContributionRequestReviewSchemaTool;
use App\Mcp\Tools\Admin\AdminGetEventModerationSchemaTool;
use App\Mcp\Tools\Admin\AdminGetMembershipClaimReviewSchemaTool;
use App\Mcp\Tools\Admin\AdminGetRecordActionsTool;
use App\Mcp\Tools\Admin\AdminGetRecordTool;
use App\Mcp\Tools\Admin\AdminGetReportTriageSchemaTool;
use App\Mcp\Tools\Admin\AdminGetResourceMetaTool;
use App\Mcp\Tools\Admin\AdminGetWriteSchemaTool;
use App\Mcp\Tools\Admin\AdminListRecordsTool;
use App\Mcp\Tools\Admin\AdminListRelatedRecordsTool;
use App\Mcp\Tools\Admin\AdminListResourcesTool;
use App\Mcp\Tools\Admin\AdminModerateEventTool;
use App\Mcp\Tools\Admin\AdminReviewContributionRequestTool;
use App\Mcp\Tools\Admin\AdminReviewMembershipClaimTool;
use App\Mcp\Tools\Admin\AdminTriageReportTool;
use App\Mcp\Tools\Admin\AdminUpdateRecordTool;
use App\Models\ContributionRequest;
use App\Models\DonationChannel;
use App\Models\Event;
use App\Models\EventChangeAnnouncement;
use App\Models\Inspiration;
use App\Models\Institution;
use App\Models\MembershipClaim;
use App\Models\ModerationReview;
use App\Models\PassportUser;
use App\Models\Reference;
use App\Models\Report;
use App\Models\Series;
use App\Models\Space;
use App\Models\Speaker;
use App\Models\Tag;
use App\Models\User;
use App\Models\Venue;
use App\Services\Signals\SignalsTracker;
use App\Support\GitHub\GitHubIssueReportContract;
use App\Support\Mcp\McpTokenManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;

it('lists accessible admin resources for admin users through the MCP server', function () {
    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminListResourcesTool::class)
        ->assertOk()
        ->assertSee(['speakers', 'events', 'institutions', 'references', 'reports', 'subdistricts', 'donation-channels'])
        ->assertStructuredContent(fn ($json) => $json
            ->has('data.resources')
            ->where('data.resources.0.key', fn (string $key): bool => filled($key))
            ->missing('data.resources.0.resource_class')
            ->etc());
});

it('can request verbose admin resource metadata through the MCP resource list tool', function () {
    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminListResourcesTool::class, [
            'verbose' => true,
            'writable_only' => true,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('data.resources', 12)
            ->where('data.resources.0.resource_class', fn (string $resourceClass): bool => str_contains($resourceClass, 'Resource'))
            ->etc());
});

it('allows viewer-role admins to use read tools', function () {
    $viewer = adminMcpUser('viewer');

    AdminServer::actingAs($viewer)
        ->tool(AdminListResourcesTool::class)
        ->assertOk()
        ->assertHasNoErrors();
});

it('hides write tools when the authenticated user has no writable admin access', function () {
    $viewer = User::factory()->create();

    AdminServer::actingAs($viewer)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'speakers',
            'operation' => 'create',
        ])
        ->assertHasErrors(['Tool [admin-get-write-schema] not found.']);
});

it('reviews membership claims through the admin MCP workflow tool', function () {
    $admin = adminMcpUser('super_admin');
    $institution = Institution::factory()->create();
    $claimant = User::factory()->create();
    $claim = MembershipClaim::factory()
        ->forInstitution($institution)
        ->create([
            'claimant_id' => $claimant->getKey(),
            'status' => 'pending',
        ]);

    AdminServer::actingAs($admin)
        ->tool(AdminReviewMembershipClaimTool::class, [
            'record_key' => $claim->getKey(),
            'action' => 'approve',
            'granted_role_slug' => 'admin',
            'reviewer_note' => 'Approved through admin MCP.',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'membership-claims')
            ->where('data.record.route_key', $claim->getRouteKey())
            ->where('data.record.attributes.status', 'approved')
            ->where('data.record.attributes.granted_role_slug', 'admin')
            ->etc());

    expect($claim->fresh()?->status->value)->toBe('approved')
        ->and($claim->fresh()?->granted_role_slug)->toBe('admin')
        ->and($claim->fresh()?->reviewer_id)->toBe($admin->getKey())
        ->and($institution->fresh()->members()->whereKey($claimant->getKey())->exists())->toBeTrue();
});

it('triages reports through the admin MCP workflow tool', function () {
    $admin = adminMcpUser('moderator');
    $report = Report::factory()->create([
        'status' => 'open',
        'handled_by' => null,
        'resolution_note' => null,
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminTriageReportTool::class, [
            'record_key' => $report->getKey(),
            'action' => 'resolve',
            'resolution_note' => 'Handled through admin MCP.',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'reports')
            ->where('data.record.route_key', $report->getRouteKey())
            ->where('data.record.attributes.status', 'resolved')
            ->where('data.record.attributes.resolution_note', 'Handled through admin MCP.')
            ->etc());

    $report->refresh();

    expect($report->status)->toBe('resolved')
        ->and($report->handled_by)->toBe($admin->getKey())
        ->and($report->resolution_note)->toBe('Handled through admin MCP.');
});

it('exposes report write schema and creates and updates reports through the admin MCP server', function () {
    $admin = adminMcpUser('moderator');
    $reporter = User::factory()->create();
    $event = Event::factory()->create();
    $reference = Reference::factory()->verified()->create();

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'reports',
            'operation' => 'create',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'reports')
            ->where('data.schema.resource_key', 'reports')
            ->where('data.schema.content_type', 'application/json')
            ->where('data.schema.tool_arguments.resource_key', 'reports')
            ->where('data.schema.fields', fn ($fields): bool => data_get(collect($fields)->firstWhere('name', 'evidence'), 'mcp_upload.shape') === 'array<file_descriptor>'
                && collect($fields)
                    ->pluck('name')
                    ->filter(fn (mixed $name): bool => is_string($name) && str_starts_with($name, 'clear_'))
                    ->isEmpty())
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminCreateRecordTool::class, [
            'resource_key' => 'reports',
            'payload' => [
                'entity_type' => 'event',
                'entity_id' => (string) $event->getKey(),
                'category' => 'wrong_info',
                'description' => 'Created through admin MCP.',
                'status' => 'open',
                'reporter_id' => (string) $reporter->getKey(),
                'evidence' => [
                    adminMcpImageDescriptor('admin-mcp-report-evidence.png'),
                ],
            ],
        ])
        ->assertOk();

    $report = Report::query()->where('description', 'Created through admin MCP.')->firstOrFail();
    $reportRouteKey = (string) $report->getKey();

    expect($report->entity_type)->toBe('event')
        ->and($report->entity_id)->toBe($event->getKey())
        ->and($report->category)->toBe('wrong_info')
        ->and($report->reporter_id)->toBe($reporter->getKey())
        ->and($report->getMedia('evidence'))->toHaveCount(1);

    AdminServer::actingAs($admin)
        ->tool(AdminUpdateRecordTool::class, [
            'resource_key' => 'reports',
            'record_key' => $reportRouteKey,
            'payload' => [
                'entity_type' => 'reference',
                'entity_id' => (string) $reference->getKey(),
                'category' => 'fake_reference',
                'description' => 'Updated through admin MCP.',
                'status' => 'resolved',
                'reporter_id' => (string) $reporter->getKey(),
                'handled_by' => (string) $admin->getKey(),
                'resolution_note' => 'Resolved through admin MCP.',
                'evidence' => [
                    adminMcpImageDescriptor('admin-mcp-report-evidence-updated.png'),
                ],
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.record.attributes.entity_type', 'reference')
            ->where('data.record.attributes.category', 'fake_reference')
            ->where('data.record.attributes.status', 'resolved')
            ->where('data.record.attributes.resolution_note', 'Resolved through admin MCP.')
            ->etc());

    $report->refresh();

    expect($report->entity_type)->toBe('reference')
        ->and($report->entity_id)->toBe($reference->getKey())
        ->and($report->category)->toBe('fake_reference')
        ->and($report->status)->toBe('resolved')
        ->and($report->handled_by)->toBe($admin->getKey())
        ->and($report->resolution_note)->toBe('Resolved through admin MCP.')
        ->and($report->getMedia('evidence'))->toHaveCount(1);
});

it('returns resource metadata, record listings, and record detail for speakers', function () {
    $admin = adminMcpUser('super_admin');
    $speaker = Speaker::factory()->create([
        'name' => 'Admin MCP Speaker',
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminGetResourceMetaTool::class, [
            'resource_key' => 'speakers',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'speakers')
            ->where('data.resource.api_routes.collection', '/api/v1/admin/speakers')
            ->where('data.resource.api_routes.schema', '/api/v1/admin/speakers/schema')
            ->where('data.resource.mcp_tools.list_records.tool', 'admin-list-records')
            ->where('data.resource.mcp_tools.list_records.arguments.resource_key', 'speakers')
            ->where('data.resource.mcp_tools.list_records.arguments.filters', 'object')
            ->where('data.resource.mcp_tools.get_record_actions.tool', 'admin-get-record-actions')
            ->where('data.resource.mcp_tools.get_record_actions.arguments.record_key', 'record')
            ->where('data.resource.mcp_tools.create.arguments.validate_only', false)
            ->where('data.resource.mcp_tools.update.arguments.validate_only', false)
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminListRecordsTool::class, [
            'resource_key' => 'speakers',
            'search' => 'Admin MCP Speaker',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.0.id', $speaker->getKey())
            ->where('data.0.title', 'Admin MCP Speaker')
            ->where('meta.resource.key', 'speakers')
            ->where('meta.pagination.page', 1)
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminGetRecordTool::class, [
            'resource_key' => 'speakers',
            'record_key' => $speaker->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'speakers')
            ->where('data.record.route_key', $speaker->getRouteKey())
            ->where('data.record.attributes.name', 'Admin MCP Speaker')
            ->etc());
});

it('returns focused next-step actions for admin records through the MCP server', function () {
    $admin = adminMcpUser('super_admin');
    $event = Event::factory()->create([
        'title' => 'Admin MCP Actionable Event',
        'status' => 'pending',
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminGetRecordActionsTool::class, [
            'resource_key' => 'events',
            'record_key' => $event->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'events')
            ->where('data.record.route_key', $event->getRouteKey())
            ->where('data.focus_actions.recommended_keys.0', 'get_event_moderation_schema')
            ->where('data.focus_actions.actions', fn ($actions): bool => array_diff(
                ['get_record', 'get_update_schema', 'update_record', 'get_event_moderation_schema', 'moderate_event'],
                collect($actions)->pluck('key')->all(),
            ) === [])
            ->where('data.focus_actions.actions', fn ($actions): bool => data_get(collect($actions)->firstWhere('key', 'get_event_moderation_schema'), 'tool') === 'admin-get-event-moderation-schema')
            ->where('data.focus_actions.actions', fn ($actions): bool => data_get(collect($actions)->firstWhere('key', 'moderate_event'), 'tool') === 'admin-moderate-event')
            ->where('data.focus_actions.actions', fn ($actions): bool => collect(data_get(collect($actions)->firstWhere('key', 'moderate_event'), 'requires', []))->contains('get_event_moderation_schema'))
            ->where('data.focus_actions.actions', fn ($actions): bool => collect(data_get(collect($actions)->firstWhere('key', 'moderate_event'), 'schema.available_actions', []))
                ->pluck('key')
                ->contains('approve'))
            ->etc());
});

it('lists related records through the admin MCP server', function () {
    $admin = adminMcpUser('super_admin');
    $relatedTitle = 'Nested MCP Event '.Str::ulid();
    $speaker = Speaker::factory()->create([
        'name' => 'Nested MCP Speaker',
    ]);
    $event = Event::factory()->create([
        'title' => $relatedTitle,
    ]);

    $event->speakers()->attach($speaker);

    AdminServer::actingAs($admin)
        ->tool(AdminGetResourceMetaTool::class, [
            'resource_key' => 'speakers',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.api_routes.related_collection', '/api/v1/admin/speakers/record/relations/relation')
            ->where('data.resource.mcp_tools.list_related_records.tool', 'admin-list-related-records')
            ->where('data.resource.mcp_tools.list_related_records.arguments.resource_key', 'speakers')
            ->where('data.resource.mcp_tools.list_related_records.arguments.record_key', 'record')
            ->where('data.resource.mcp_tools.list_related_records.arguments.relation', 'relation')
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminListRelatedRecordsTool::class, [
            'resource_key' => 'speakers',
            'record_key' => $speaker->getKey(),
            'relation' => 'events',
            'search' => $relatedTitle,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.0.route_key', $event->getRouteKey())
            ->where('data.0.title', $relatedTitle)
            ->where('meta.resource.key', 'speakers')
            ->where('meta.parent_record.route_key', $speaker->getRouteKey())
            ->where('meta.relation.name', 'events')
            ->where('meta.relation.related_resource.key', 'events')
            ->etc());
});

it('exposes timezone-aware metadata for admin event resources', function () {
    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminGetResourceMetaTool::class, [
            'resource_key' => 'events',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'events')
            ->where('data.resource.timezone_sensitive', true)
            ->where('data.resource.date_semantics.storage_timezone', 'UTC')
            ->where('data.resource.date_semantics.local_date_filter', 'starts_on_local_date')
            ->where('data.resource.mcp_tools.list_records.arguments.filters', 'object')
            ->where('data.resource.mcp_tools.list_records.arguments.starts_after', null)
            ->where('data.resource.mcp_tools.list_records.arguments.starts_before', null)
            ->where('data.resource.mcp_tools.list_records.arguments.starts_on_local_date', null)
            ->where('data.resource.mcp_tools.get_record.tool', 'admin-get-record')
            ->where('data.resource.mcp_tools.get_record_actions.tool', 'admin-get-record-actions')
            ->where('data.resource.mcp_tools.get_record.arguments.resource_key', 'events')
            ->where('data.resource.mcp_tools.get_record.arguments.record_key', 'record')
            ->etc());
});

it('filters admin event records by structured filters through the MCP server', function () {
    $admin = adminMcpUser('super_admin');

    $draftOnlineEvent = Event::factory()->create([
        'title' => 'Admin MCP Filtered Draft Online Event',
        'status' => 'draft',
        'event_format' => EventFormat::Online,
        'visibility' => EventVisibility::Public,
        'is_active' => true,
        'event_type' => [EventType::KuliahCeramah->value],
    ]);

    Event::factory()->create([
        'title' => 'Admin MCP Approved Physical Event',
        'status' => 'approved',
        'event_format' => EventFormat::Physical,
        'visibility' => EventVisibility::Private,
        'is_active' => false,
        'event_type' => [EventType::Forum->value],
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminListRecordsTool::class, [
            'resource_key' => 'events',
            'filters' => [
                'status' => 'draft',
                'event_format' => 'online',
                'event_type' => 'kuliah_ceramah',
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('data', 1)
            ->where('data.0.id', $draftOnlineEvent->getKey())
            ->where('data.0.title', 'Admin MCP Filtered Draft Online Event')
            ->where('meta.resource.key', 'events')
            ->etc());
});

it('filters admin event records by single status, boolean, visibility, and timing filters through the MCP server', function () {
    $admin = adminMcpUser('super_admin');

    $approvedActivePublicAbsolute = Event::factory()->create([
        'title' => 'Admin MCP Single Filter Approved Active Public Absolute',
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'timing_mode' => 'absolute',
        'is_active' => true,
    ]);

    $draftInactivePrivatePrayerRelative = Event::factory()->create([
        'title' => 'Admin MCP Single Filter Draft Inactive Private Prayer Relative',
        'status' => 'draft',
        'visibility' => EventVisibility::Private,
        'timing_mode' => 'prayer_relative',
        'is_active' => false,
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminListRecordsTool::class, [
            'resource_key' => 'events',
            'filters' => [
                'status' => 'approved',
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('data', 1)
            ->where('data.0.id', $approvedActivePublicAbsolute->getKey())
            ->where('data.0.title', 'Admin MCP Single Filter Approved Active Public Absolute')
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminListRecordsTool::class, [
            'resource_key' => 'events',
            'filters' => [
                'is_active' => false,
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('data', 1)
            ->where('data.0.id', $draftInactivePrivatePrayerRelative->getKey())
            ->where('data.0.title', 'Admin MCP Single Filter Draft Inactive Private Prayer Relative')
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminListRecordsTool::class, [
            'resource_key' => 'events',
            'filters' => [
                'visibility' => 'public',
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('data', 1)
            ->where('data.0.id', $approvedActivePublicAbsolute->getKey())
            ->where('data.0.title', 'Admin MCP Single Filter Approved Active Public Absolute')
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminListRecordsTool::class, [
            'resource_key' => 'events',
            'filters' => [
                'timing_mode' => 'prayer_relative',
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('data', 1)
            ->where('data.0.id', $draftInactivePrivatePrayerRelative->getKey())
            ->where('data.0.title', 'Admin MCP Single Filter Draft Inactive Private Prayer Relative')
            ->etc());
});

it('surfaces public event change projections on admin event record detail through the MCP server', function () {
    $admin = adminMcpUser('super_admin');
    $actor = User::factory()->create();
    $original = Event::factory()->create([
        'title' => 'Admin MCP Change Surface Original',
        'slug' => 'admin-mcp-change-surface-original',
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
    ]);
    $firstReplacement = Event::factory()->create([
        'title' => 'Admin MCP Change Surface First Replacement',
        'slug' => 'admin-mcp-change-surface-first-replacement',
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
    ]);
    $finalReplacement = Event::factory()->create([
        'title' => 'Admin MCP Change Surface Final Replacement',
        'slug' => 'admin-mcp-change-surface-final-replacement',
        'status' => 'approved',
        'visibility' => EventVisibility::Public,
        'is_active' => true,
    ]);

    EventChangeAnnouncement::unguarded(function () use ($actor, $original, $firstReplacement, $finalReplacement): void {
        EventChangeAnnouncement::query()->create([
            'event_id' => $original->id,
            'replacement_event_id' => $firstReplacement->id,
            'actor_id' => $actor->id,
            'type' => EventChangeType::ReplacementLinked,
            'status' => EventChangeStatus::Published,
            'severity' => EventChangeSeverity::High,
            'public_message' => 'Sila rujuk majlis pengganti pertama.',
            'changed_fields' => [],
            'published_at' => Carbon::parse('2026-05-05 12:00:00', 'UTC'),
            'created_at' => Carbon::parse('2026-05-05 12:00:00', 'UTC'),
            'updated_at' => Carbon::parse('2026-05-05 12:00:00', 'UTC'),
        ]);

        EventChangeAnnouncement::query()->create([
            'event_id' => $firstReplacement->id,
            'replacement_event_id' => $finalReplacement->id,
            'actor_id' => $actor->id,
            'type' => EventChangeType::ReplacementLinked,
            'status' => EventChangeStatus::Published,
            'severity' => EventChangeSeverity::High,
            'public_message' => 'Majlis pengganti pertama diganti pula.',
            'changed_fields' => [],
            'published_at' => Carbon::parse('2026-05-05 12:05:00', 'UTC'),
            'created_at' => Carbon::parse('2026-05-05 12:05:00', 'UTC'),
            'updated_at' => Carbon::parse('2026-05-05 12:05:00', 'UTC'),
        ]);

        EventChangeAnnouncement::query()->create([
            'event_id' => $original->id,
            'actor_id' => $actor->id,
            'type' => EventChangeType::Other,
            'status' => EventChangeStatus::Published,
            'severity' => EventChangeSeverity::Info,
            'public_message' => 'Nota terkini untuk pautan lama.',
            'changed_fields' => ['title'],
            'published_at' => Carbon::parse('2026-05-05 12:10:00', 'UTC'),
            'created_at' => Carbon::parse('2026-05-05 12:10:00', 'UTC'),
            'updated_at' => Carbon::parse('2026-05-05 12:10:00', 'UTC'),
        ]);
    });

    $finalReplacement->update([
        'visibility' => EventVisibility::Private,
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminGetRecordTool::class, [
            'resource_key' => 'events',
            'record_key' => $original->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'events')
            ->where('data.record.route_key', $original->getRouteKey())
            ->where('data.record.attributes.active_change_notice.type', EventChangeType::Other->value)
            ->where('data.record.attributes.replacement_event.id', $firstReplacement->id)
            ->where('data.record.attributes.change_announcements.1.type', EventChangeType::ReplacementLinked->value)
            ->where('data.record.attributes.change_announcements.1.replacement_event.id', $firstReplacement->id)
            ->missing('data.record.attributes.latest_published_change_announcement')
            ->missing('data.record.attributes.latest_published_replacement_announcement')
            ->missing('data.record.attributes.published_change_announcements')
            ->etc());
});

it('filters admin event records by local date through the MCP server', function () {
    $admin = adminMcpUser('super_admin');
    $admin->forceFill([
        'timezone' => 'Asia/Kuala_Lumpur',
    ])->save();

    $matchingEvent = Event::factory()->create([
        'title' => 'Admin MCP Date Match',
        'starts_at' => Carbon::parse('2026-05-01 02:00:00', 'UTC'),
        'status' => 'approved',
    ]);

    Event::factory()->create([
        'title' => 'Admin MCP Date Miss',
        'starts_at' => Carbon::parse('2026-05-02 02:00:00', 'UTC'),
        'status' => 'approved',
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminListRecordsTool::class, [
            'resource_key' => 'events',
            'starts_on_local_date' => '2026-05-01',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('data', 1)
            ->where('data.0.id', $matchingEvent->getKey())
            ->where('data.0.title', 'Admin MCP Date Match')
            ->where('data.0.attributes.starts_on_local_date', '2026-05-01')
            ->where('meta.resource.key', 'events')
            ->where('meta.search', null)
            ->etc());
});

it('combines local-date and structured filters through the MCP server', function () {
    $admin = adminMcpUser('super_admin');
    $admin->forceFill([
        'timezone' => 'Asia/Kuala_Lumpur',
    ])->save();

    $matchingEvent = Event::factory()->create([
        'title' => 'Admin MCP Date Plus Filter Match',
        'starts_at' => Carbon::parse('2026-05-06 02:00:00', 'UTC'),
        'status' => 'approved',
        'is_active' => true,
    ]);

    Event::factory()->create([
        'title' => 'Admin MCP Date Plus Filter Wrong Status',
        'starts_at' => Carbon::parse('2026-05-06 05:00:00', 'UTC'),
        'status' => 'draft',
        'is_active' => true,
    ]);

    Event::factory()->create([
        'title' => 'Admin MCP Date Plus Filter Wrong Date',
        'starts_at' => Carbon::parse('2026-05-07 02:00:00', 'UTC'),
        'status' => 'approved',
        'is_active' => true,
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminListRecordsTool::class, [
            'resource_key' => 'events',
            'starts_on_local_date' => '2026-05-06',
            'filters' => [
                'status' => 'approved',
                'is_active' => true,
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('data', 1)
            ->where('data.0.id', $matchingEvent->getKey())
            ->where('data.0.title', 'Admin MCP Date Plus Filter Match')
            ->where('data.0.attributes.starts_on_local_date', '2026-05-06')
            ->etc());
});

it('filters admin event records by local date ranges through the MCP server', function () {
    $admin = adminMcpUser('super_admin');
    $admin->forceFill([
        'timezone' => 'Asia/Kuala_Lumpur',
    ])->save();

    $beforeRange = Event::factory()->create([
        'title' => 'Admin MCP Date Range Before',
        'starts_at' => Carbon::parse('2026-05-01 02:00:00', 'UTC'),
        'status' => 'approved',
    ]);

    $withinRange = Event::factory()->create([
        'title' => 'Admin MCP Date Range Within',
        'starts_at' => Carbon::parse('2026-05-03 02:00:00', 'UTC'),
        'status' => 'approved',
    ]);

    $afterRange = Event::factory()->create([
        'title' => 'Admin MCP Date Range After',
        'starts_at' => Carbon::parse('2026-05-05 02:00:00', 'UTC'),
        'status' => 'approved',
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminListRecordsTool::class, [
            'resource_key' => 'events',
            'starts_after' => '2026-05-03',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('data', 2)
            ->where('data', fn (mixed $records): bool => collect($records)
                ->pluck('id')
                ->sort()
                ->values()
                ->all() === collect([
                    (string) $withinRange->getKey(),
                    (string) $afterRange->getKey(),
                ])->sort()->values()->all())
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminListRecordsTool::class, [
            'resource_key' => 'events',
            'starts_before' => '2026-05-03',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('data', 2)
            ->where('data', fn (mixed $records): bool => collect($records)
                ->pluck('id')
                ->sort()
                ->values()
                ->all() === collect([
                    (string) $beforeRange->getKey(),
                    (string) $withinRange->getKey(),
                ])->sort()->values()->all())
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminListRecordsTool::class, [
            'resource_key' => 'events',
            'starts_after' => '2026-05-02',
            'starts_before' => '2026-05-04',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('data', 1)
            ->where('data.0.id', $withinRange->getKey())
            ->where('data.0.title', 'Admin MCP Date Range Within')
            ->etc());
});

it('searches admin event records and combines search with local dates through the MCP server', function () {
    $admin = adminMcpUser('super_admin');
    $admin->forceFill([
        'timezone' => 'Asia/Kuala_Lumpur',
    ])->save();

    $kuliahEvent = Event::factory()->create([
        'title' => 'Admin MCP Search Kuliah Match',
        'starts_at' => Carbon::parse('2026-05-08 02:00:00', 'UTC'),
        'status' => 'approved',
    ]);

    $dhuhaDateMatch = Event::factory()->create([
        'title' => 'Admin MCP Search Dhuha Date Match',
        'starts_at' => Carbon::parse('2026-05-08 03:00:00', 'UTC'),
        'status' => 'approved',
    ]);

    Event::factory()->create([
        'title' => 'Admin MCP Search Dhuha Wrong Date',
        'starts_at' => Carbon::parse('2026-05-09 03:00:00', 'UTC'),
        'status' => 'approved',
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminListRecordsTool::class, [
            'resource_key' => 'events',
            'search' => 'Kuliah Match',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('data', 1)
            ->where('data.0.id', $kuliahEvent->getKey())
            ->where('data.0.title', 'Admin MCP Search Kuliah Match')
            ->where('meta.search', 'Kuliah Match')
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminListRecordsTool::class, [
            'resource_key' => 'events',
            'search' => 'Dhuha',
            'starts_on_local_date' => '2026-05-08',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('data', 1)
            ->where('data.0.id', $dhuhaDateMatch->getKey())
            ->where('data.0.title', 'Admin MCP Search Dhuha Date Match')
            ->where('data.0.attributes.starts_on_local_date', '2026-05-08')
            ->where('meta.search', 'Dhuha')
            ->etc());
});

it('lists admin event records without server errors through the MCP server', function () {
    $admin = adminMcpUser('super_admin');
    $admin->forceFill([
        'timezone' => 'Asia/Kuala_Lumpur',
    ])->save();

    $event = Event::factory()->create([
        'title' => 'Admin MCP Unfiltered Event',
        'starts_at' => Carbon::parse('2026-04-23 02:00:00', 'UTC'),
        'status' => 'approved',
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminListRecordsTool::class, [
            'resource_key' => 'events',
            'page' => 1,
            'per_page' => 50,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('data', 1)
            ->where('data.0.id', $event->getKey())
            ->where('data.0.title', 'Admin MCP Unfiltered Event')
            ->where('data.0.attributes.starts_on_local_date', '2026-04-23')
            ->where('meta.resource.key', 'events')
            ->where('meta.pagination.page', 1)
            ->etc());
});

it('lists admin event records after repairing legacy enum values through the MCP server', function () {
    $admin = adminMcpUser('super_admin');
    $admin->forceFill([
        'timezone' => 'Asia/Kuala_Lumpur',
    ])->save();

    $event = Event::factory()->create([
        'title' => 'Admin MCP Legacy Enum Event',
        'starts_at' => Carbon::parse('2026-04-23 02:00:00', 'UTC'),
        'status' => 'approved',
    ]);

    DB::table('events')
        ->where('id', $event->getKey())
        ->update([
            'event_type' => json_encode('Kuliah / Ceramah', JSON_THROW_ON_ERROR),
            'age_group' => json_encode(['Semua Peringkat Umur'], JSON_THROW_ON_ERROR),
            'timing_mode' => 'legacy_prayer_time',
            'prayer_reference' => 'Maghrib',
            'prayer_offset' => 'Sejurus selepas',
        ]);

    runLegacyEventEnumValueRepairMigration();

    $repairedEvent = DB::table('events')
        ->where('id', $event->getKey())
        ->first(['event_type', 'age_group', 'timing_mode', 'prayer_reference', 'prayer_offset']);

    assert($repairedEvent !== null);

    expect(json_decode((string) $repairedEvent->event_type, true))->toBe([EventType::KuliahCeramah->value])
        ->and(json_decode((string) $repairedEvent->age_group, true))->toBe([EventAgeGroup::AllAges->value])
        ->and($repairedEvent->timing_mode)->toBe('prayer_relative')
        ->and($repairedEvent->prayer_reference)->toBe(PrayerReference::Maghrib->value)
        ->and($repairedEvent->prayer_offset)->toBe(PrayerOffset::Immediately->value);

    AdminServer::actingAs($admin)
        ->tool(AdminListRecordsTool::class, [
            'resource_key' => 'events',
            'filters' => [
                'status' => 'approved',
            ],
            'starts_on_local_date' => '2026-04-23',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('data', 1)
            ->where('data.0.id', $event->getKey())
            ->where('data.0.title', 'Admin MCP Legacy Enum Event')
            ->where('data.0.attributes.event_type.0', EventType::KuliahCeramah->value)
            ->where('data.0.attributes.age_group.0', EventAgeGroup::AllAges->value)
            ->where('data.0.attributes.prayer_reference', PrayerReference::Maghrib->value)
            ->where('data.0.attributes.prayer_offset', PrayerOffset::Immediately->value)
            ->where('data.0.attributes.starts_on_local_date', '2026-04-23')
            ->where('data.0.attributes.timing_display', EventPrayerTime::SelepasMaghrib->getLabel())
            ->where('meta.resource.key', 'events')
            ->etc());
});

it('exposes tag write schema and creates and updates tags through the admin MCP server', function () {
    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'tags',
            'operation' => 'create',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'tags')
            ->where('data.schema.resource_key', 'tags')
            ->where('data.schema.content_type', 'application/json')
            ->where('data.schema.tool_arguments.resource_key', 'tags')
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminCreateRecordTool::class, [
            'resource_key' => 'tags',
            'payload' => [
                'name' => [
                    'ms' => 'Tadabbur MCP',
                    'en' => 'Reflection MCP',
                ],
                'type' => 'discipline',
                'status' => 'verified',
            ],
        ])
        ->assertOk();

    $tagRouteKey = (string) Tag::query()
        ->where('type', 'discipline')
        ->where('name->ms', 'Tadabbur MCP')
        ->value('id');

    expect($tagRouteKey)->not->toBe('');

    expect(Tag::query()->findOrFail($tagRouteKey)->type)->toBe('discipline');

    AdminServer::actingAs($admin)
        ->tool(AdminUpdateRecordTool::class, [
            'resource_key' => 'tags',
            'record_key' => $tagRouteKey,
            'payload' => [
                'name' => [
                    'ms' => 'Rasuah MCP',
                    'en' => 'Corruption MCP',
                ],
                'type' => 'issue',
                'status' => 'pending',
                'order_column' => 7,
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.record.attributes.type', 'issue')
            ->where('data.record.attributes.status', 'pending')
            ->where('data.record.attributes.order_column', 7)
            ->etc());

    $tag = Tag::query()->findOrFail($tagRouteKey);

    expect($tag->status)->toBe('pending')
        ->and($tag->order_column)->toBe(7)
        ->and($tag->getTranslation('slug', 'en'))->toBe('corruption-mcp');
});

it('moderates events through the admin MCP workflow tool', function () {
    $admin = adminMcpUser('super_admin');
    $event = Event::factory()->create([
        'status' => 'approved',
        'published_at' => now(),
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminModerateEventTool::class, [
            'record_key' => $event->getKey(),
            'action' => 'remoderate',
            'note' => 'Content needs re-review.',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'events')
            ->where('data.record.route_key', $event->getRouteKey())
            ->where('data.record.attributes.status', 'pending')
            ->etc());

    $event->refresh();

    expect((string) $event->status)->toBe('pending');

    $review = ModerationReview::query()->where('event_id', $event->getKey())->latest()->first();

    expect($review?->decision)->toBe('remoderated');
});

it('reviews contribution requests through the admin MCP workflow tool', function () {
    $admin = adminMcpUser('super_admin');
    $speaker = Speaker::factory()->create([
        'name' => 'Pending MCP Speaker',
        'status' => 'pending',
        'is_active' => true,
    ]);
    $request = ContributionRequest::factory()->create([
        'type' => ContributionRequestType::Create,
        'subject_type' => ContributionSubjectType::Speaker,
        'entity_type' => $speaker->getMorphClass(),
        'entity_id' => $speaker->getKey(),
        'status' => ContributionRequestStatus::Pending,
        'proposed_data' => [
            'name' => $speaker->name,
            'gender' => 'female',
        ],
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminReviewContributionRequestTool::class, [
            'record_key' => $request->getKey(),
            'action' => 'reject',
            'reason_code' => 'needs_more_evidence',
            'reviewer_note' => 'Need stronger sourcing.',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'contribution-requests')
            ->where('data.record.route_key', $request->getRouteKey())
            ->where('data.record.attributes.status', 'rejected')
            ->where('data.record.attributes.reason_code', 'needs_more_evidence')
            ->etc());

    expect($request->fresh()?->status)->toBe(ContributionRequestStatus::Rejected)
        ->and($request->fresh()?->reason_code)->toBe('needs_more_evidence')
        ->and($speaker->fresh()?->status)->toBe('rejected')
        ->and($speaker->fresh()?->is_active)->toBeFalse();
});

it('returns explicit admin workflow schemas through dedicated MCP schema tools', function () {
    $admin = adminMcpUser('super_admin');
    $event = Event::factory()->create([
        'status' => 'pending',
    ]);
    $report = Report::factory()->create([
        'status' => 'open',
    ]);
    $speaker = Speaker::factory()->create([
        'name' => 'Schema MCP Speaker',
        'status' => 'pending',
        'is_active' => true,
    ]);
    $request = ContributionRequest::factory()->create([
        'type' => ContributionRequestType::Create,
        'subject_type' => ContributionSubjectType::Speaker,
        'entity_type' => $speaker->getMorphClass(),
        'entity_id' => $speaker->getKey(),
        'status' => ContributionRequestStatus::Pending,
        'proposed_data' => [
            'name' => $speaker->name,
            'gender' => 'female',
        ],
    ]);
    $institution = Institution::factory()->create();
    $claimant = User::factory()->create();
    $claim = MembershipClaim::factory()
        ->forInstitution($institution)
        ->create([
            'claimant_id' => $claimant->getKey(),
            'status' => 'pending',
        ]);

    AdminServer::actingAs($admin)
        ->tool(AdminGetEventModerationSchemaTool::class, [
            'record_key' => $event->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'events')
            ->where('data.record.route_key', $event->getRouteKey())
            ->where('data.schema.action', 'moderate_event')
            ->where('data.schema.defaults.action', 'approve')
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminGetReportTriageSchemaTool::class, [
            'record_key' => $report->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'reports')
            ->where('data.record.route_key', $report->getRouteKey())
            ->where('data.schema.action', 'triage_report')
            ->where('data.schema.defaults.action', 'triage')
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminGetContributionRequestReviewSchemaTool::class, [
            'record_key' => $request->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'contribution-requests')
            ->where('data.record.route_key', $request->getRouteKey())
            ->where('data.schema.action', 'review_contribution_request')
            ->where('data.schema.defaults.action', 'approve')
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminGetMembershipClaimReviewSchemaTool::class, [
            'record_key' => $claim->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'membership-claims')
            ->where('data.record.route_key', $claim->getRouteKey())
            ->where('data.schema.action', 'review_membership_claim')
            ->where('data.schema.defaults.action', 'approve')
            ->etc());
});

it('exposes series write schema and creates and updates series through the admin MCP server', function () {
    $admin = adminMcpUser('super_admin');
    $suffix = Str::lower((string) Str::ulid());

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'series',
            'operation' => 'create',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'series')
            ->where('data.schema.resource_key', 'series')
            ->where('data.schema.content_type', 'application/json')
            ->where('data.schema.tool_arguments.resource_key', 'series')
            ->where('data.schema.fields', fn ($fields): bool => data_get(collect($fields)->firstWhere('name', 'cover'), 'mcp_upload.shape') === 'file_descriptor'
                && data_get(collect($fields)->firstWhere('name', 'gallery'), 'mcp_upload.shape') === 'array<file_descriptor>')
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminCreateRecordTool::class, [
            'resource_key' => 'series',
            'payload' => [
                'title' => 'Admin MCP Series '.$suffix,
                'slug' => 'admin-mcp-series-'.$suffix,
                'description' => 'Series created through MCP.',
                'visibility' => 'public',
                'is_active' => true,
                'cover' => adminMcpImageDescriptor('series-cover.png'),
                'gallery' => [
                    adminMcpImageDescriptor('series-gallery.png'),
                ],
            ],
        ])
        ->assertOk();

    $seriesRouteKey = (string) Series::query()->where('slug', 'admin-mcp-series-'.$suffix)->value('id');

    expect($seriesRouteKey)->not->toBe('');

    $series = Series::query()->findOrFail($seriesRouteKey);

    expect($series->getMedia('cover'))->toHaveCount(1)
        ->and($series->getMedia('gallery'))->toHaveCount(1);

    AdminServer::actingAs($admin)
        ->tool(AdminUpdateRecordTool::class, [
            'resource_key' => 'series',
            'record_key' => $seriesRouteKey,
            'payload' => [
                'title' => 'Admin MCP Series Updated '.$suffix,
                'slug' => 'admin-mcp-series-updated-'.$suffix,
                'description' => 'Series updated through MCP.',
                'visibility' => 'private',
                'is_active' => false,
                'languages' => [],
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.record.attributes.title', 'Admin MCP Series Updated '.$suffix)
            ->where('data.record.attributes.slug', 'admin-mcp-series-updated-'.$suffix)
            ->where('data.record.attributes.visibility', 'private')
            ->where('data.record.attributes.is_active', false)
            ->etc());

    $series->refresh();

    expect($series->title)->toBe('Admin MCP Series Updated '.$suffix)
        ->and($series->slug)->toBe('admin-mcp-series-updated-'.$suffix)
        ->and($series->visibility)->toBe('private')
        ->and($series->is_active)->toBeFalse();
});

it('exposes space write schema and creates and updates spaces through the admin MCP server', function () {
    $admin = adminMcpUser('super_admin');
    $suffix = Str::lower((string) Str::ulid());
    $firstInstitution = Institution::factory()->create();
    $secondInstitution = Institution::factory()->create();

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'spaces',
            'operation' => 'create',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'spaces')
            ->where('data.schema.resource_key', 'spaces')
            ->where('data.schema.content_type', 'application/json')
            ->where('data.schema.tool_arguments.resource_key', 'spaces')
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminCreateRecordTool::class, [
            'resource_key' => 'spaces',
            'payload' => [
                'name' => 'Admin MCP Space '.$suffix,
                'slug' => 'admin-mcp-space-'.$suffix,
                'capacity' => 40,
                'is_active' => true,
                'institutions' => [(string) $firstInstitution->getKey()],
            ],
        ])
        ->assertOk();

    $spaceRouteKey = (string) Space::query()->where('slug', 'admin-mcp-space-'.$suffix)->value('id');

    expect($spaceRouteKey)->not->toBe('');

    $space = Space::query()->findOrFail($spaceRouteKey);

    expect($space->institutions()->pluck('institutions.id')->all())->toContain($firstInstitution->getKey());

    AdminServer::actingAs($admin)
        ->tool(AdminUpdateRecordTool::class, [
            'resource_key' => 'spaces',
            'record_key' => $spaceRouteKey,
            'payload' => [
                'name' => 'Admin MCP Space Updated '.$suffix,
                'slug' => 'admin-mcp-space-updated-'.$suffix,
                'capacity' => 65,
                'is_active' => false,
                'institutions' => [(string) $secondInstitution->getKey()],
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.record.attributes.name', 'Admin MCP Space Updated '.$suffix)
            ->where('data.record.attributes.slug', 'admin-mcp-space-updated-'.$suffix)
            ->where('data.record.attributes.capacity', 65)
            ->where('data.record.attributes.is_active', false)
            ->etc());

    $space->refresh();

    expect($space->name)->toBe('Admin MCP Space Updated '.$suffix)
        ->and($space->slug)->toBe('admin-mcp-space-updated-'.$suffix)
        ->and($space->capacity)->toBe(65)
        ->and($space->is_active)->toBeFalse()
        ->and($space->institutions()->pluck('institutions.id')->all())->toContain($secondInstitution->getKey())
        ->and($space->institutions()->pluck('institutions.id')->all())->not->toContain($firstInstitution->getKey());
});

it('exposes donation channel write schema and creates and updates donation channels through the admin MCP server', function () {
    $admin = adminMcpUser('super_admin');
    $institution = Institution::factory()->create();

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'donation-channels',
            'operation' => 'create',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'donation-channels')
            ->where('data.schema.resource_key', 'donation-channels')
            ->where('data.schema.content_type', 'application/json')
            ->where('data.schema.tool_arguments.resource_key', 'donation-channels')
            ->where('data.schema.fields', fn ($fields): bool => data_get(collect($fields)->firstWhere('name', 'qr'), 'mcp_upload.shape') === 'file_descriptor'
                && collect($fields)
                    ->pluck('name')
                    ->filter(fn (mixed $name): bool => is_string($name) && str_starts_with($name, 'clear_'))
                    ->isEmpty())
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminCreateRecordTool::class, [
            'resource_key' => 'donation-channels',
            'payload' => [
                'donatable_type' => 'institution',
                'donatable_id' => (string) $institution->getKey(),
                'label' => 'Tabung MCP',
                'recipient' => 'Masjid MCP',
                'method' => 'bank_account',
                'bank_code' => 'CIMB',
                'bank_name' => 'CIMB',
                'account_number' => '9876543210',
                'status' => 'verified',
                'is_default' => true,
                'qr' => adminMcpImageDescriptor('admin-mcp-donation-channel-qr.png'),
            ],
        ])
        ->assertOk();

    $donationChannelRouteKey = (string) DonationChannel::query()
        ->where('recipient', 'Masjid MCP')
        ->value('id');

    expect($donationChannelRouteKey)->not->toBe('');

    $donationChannel = DonationChannel::query()->findOrFail($donationChannelRouteKey);

    expect($donationChannel->donatable_type)->toBe('institution')
        ->and($donationChannel->donatable_id)->toBe($institution->getKey())
        ->and($donationChannel->method)->toBe('bank_account')
        ->and($donationChannel->bank_name)->toBe('CIMB')
        ->and($donationChannel->duitnow_type)->toBeNull()
        ->and($donationChannel->getMedia('qr'))->toHaveCount(1);

    AdminServer::actingAs($admin)
        ->tool(AdminUpdateRecordTool::class, [
            'resource_key' => 'donation-channels',
            'record_key' => $donationChannelRouteKey,
            'payload' => [
                'donatable_type' => 'institution',
                'donatable_id' => (string) $institution->getKey(),
                'label' => 'Tabung MCP Updated',
                'recipient' => 'Masjid MCP Updated',
                'method' => 'duitnow',
                'duitnow_type' => 'mobile',
                'duitnow_value' => '60123456789',
                'status' => 'inactive',
                'is_default' => false,
                'qr' => adminMcpImageDescriptor('admin-mcp-donation-channel-qr-updated.png'),
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.record.attributes.label', 'Tabung MCP Updated')
            ->where('data.record.attributes.method', 'duitnow')
            ->where('data.record.attributes.status', 'inactive')
            ->etc());

    $donationChannel->refresh();

    expect($donationChannel->label)->toBe('Tabung MCP Updated')
        ->and($donationChannel->recipient)->toBe('Masjid MCP Updated')
        ->and($donationChannel->method)->toBe('duitnow')
        ->and($donationChannel->bank_code)->toBeNull()
        ->and($donationChannel->bank_name)->toBeNull()
        ->and($donationChannel->account_number)->toBeNull()
        ->and($donationChannel->duitnow_type)->toBe('mobile')
        ->and($donationChannel->duitnow_value)->toBe('60123456789')
        ->and($donationChannel->status)->toBe('inactive')
        ->and($donationChannel->getMedia('qr'))->toHaveCount(1);
});

it('exposes inspiration write schema and creates and updates inspirations through the admin MCP server', function () {
    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'inspirations',
            'operation' => 'create',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'inspirations')
            ->where('data.schema.resource_key', 'inspirations')
            ->where('data.schema.content_type', 'application/json')
            ->where('data.schema.tool_arguments.resource_key', 'inspirations')
            ->where('data.schema.fields', fn ($fields): bool => data_get(collect($fields)->firstWhere('name', 'main'), 'mcp_upload.shape') === 'file_descriptor')
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminCreateRecordTool::class, [
            'resource_key' => 'inspirations',
            'payload' => [
                'category' => 'quran_quote',
                'locale' => 'ms',
                'title' => 'Admin MCP Inspiration',
                'content' => 'Inspiration created through MCP.',
                'source' => 'MCP Source',
                'is_active' => true,
                'main' => adminMcpImageDescriptor('admin-mcp-inspiration-main.png'),
            ],
        ])
        ->assertOk();

    $inspirationRouteKey = (string) Inspiration::query()->where('title', 'Admin MCP Inspiration')->value('id');

    expect($inspirationRouteKey)->not->toBe('');

    $inspiration = Inspiration::query()->findOrFail($inspirationRouteKey);

    expect($inspiration->getRawOriginal('category'))->toBe('quran_quote')
        ->and($inspiration->locale)->toBe('ms')
        ->and($inspiration->getMedia('main'))->toHaveCount(1);

    AdminServer::actingAs($admin)
        ->tool(AdminUpdateRecordTool::class, [
            'resource_key' => 'inspirations',
            'record_key' => $inspirationRouteKey,
            'payload' => [
                'category' => 'hadith_quote',
                'locale' => 'en',
                'title' => 'Admin MCP Inspiration Updated',
                'content' => 'Inspiration updated through MCP.',
                'source' => 'Updated MCP Source',
                'is_active' => false,
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.record.attributes.category', 'hadith_quote')
            ->where('data.record.attributes.locale', 'en')
            ->where('data.record.attributes.title', 'Admin MCP Inspiration Updated')
            ->where('data.record.attributes.is_active', false)
            ->etc());

    $inspiration->refresh();

    expect($inspiration->getRawOriginal('category'))->toBe('hadith_quote')
        ->and($inspiration->locale)->toBe('en')
        ->and($inspiration->title)->toBe('Admin MCP Inspiration Updated')
        ->and($inspiration->source)->toBe('Updated MCP Source')
        ->and($inspiration->is_active)->toBeFalse();
});

it('surfaces space report and inspiration update semantics through admin MCP write schemas', function () {
    $admin = adminMcpUser('super_admin');
    $space = Space::factory()->create([
        'capacity' => 40,
    ]);
    $report = Report::factory()->create([
        'status' => 'open',
    ]);
    $inspiration = Inspiration::factory()->create([
        'source' => 'Schema Source',
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'spaces',
            'operation' => 'update',
            'record_key' => (string) $space->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.schema.resource_key', 'spaces')
            ->where('data.schema.fields', function ($fields): bool {
                $fieldMap = collect($fields)->keyBy('name');

                return data_get($fieldMap->get('slug'), 'uniqueness_scope') === 'spaces.slug'
                    && data_get($fieldMap->get('capacity'), 'clear_semantics.explicit_null') === 'clear_to_null'
                    && data_get($fieldMap->get('institutions'), 'collection_semantics.submitted_array') === 'replace_relation_sync';
            })
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'reports',
            'operation' => 'update',
            'record_key' => (string) $report->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.schema.resource_key', 'reports')
            ->where('data.schema.fields', function ($fields): bool {
                $fieldMap = collect($fields)->keyBy('name');

                return data_get($fieldMap->get('entity_type'), 'paired_with') === 'entity_id'
                    && data_get($fieldMap->get('category'), 'allowed_values_resolved_from') === 'entity_type'
                    && data_get($fieldMap->get('reporter_id'), 'relation') === 'users'
                    && data_get($fieldMap->get('resolution_note'), 'clear_semantics.explicit_null') === 'clear_to_null'
                    && data_get($fieldMap->get('evidence'), 'collection_semantics.explicit_null') === 'preserve_existing_collection'
                    && data_get($fieldMap->get('evidence'), 'raw_http_clear_flag') === 'clear_evidence';
            })
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'inspirations',
            'operation' => 'update',
            'record_key' => (string) $inspiration->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.schema.resource_key', 'inspirations')
            ->where('data.schema.fields', function ($fields): bool {
                $fieldMap = collect($fields)->keyBy('name');

                return data_get($fieldMap->get('content'), 'input_normalization.kind') === 'rich_text_document'
                    && data_get($fieldMap->get('source'), 'clear_semantics.explicit_null') === 'clear_to_null'
                    && data_get($fieldMap->get('main'), 'mutation_semantics') === 'replace_single_media_collection'
                    && data_get($fieldMap->get('main'), 'raw_http_clear_flag') === 'clear_main';
            })
            ->etc());
});

it('surfaces tag and subdistrict update semantics through admin MCP write schemas', function () {
    $admin = adminMcpUser('super_admin');
    $tag = Tag::factory()->discipline()->verified()->create();

    $countryId = ensureMcpMalaysiaCountryExists();
    $suffix = Str::lower(Str::random(8));
    $stateId = DB::table('states')->insertGetId([
        'country_id' => $countryId,
        'name' => 'Admin MCP Negeri '.$suffix,
        'country_code' => 'MY',
    ]);
    $districtId = DB::table('districts')->insertGetId([
        'country_id' => $countryId,
        'state_id' => $stateId,
        'name' => 'Admin MCP Daerah '.$suffix,
        'country_code' => 'MY',
    ]);
    $subdistrictId = DB::table('subdistricts')->insertGetId([
        'country_id' => $countryId,
        'state_id' => $stateId,
        'district_id' => $districtId,
        'name' => 'Admin MCP Subdistrict '.$suffix,
        'country_code' => 'MY',
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'tags',
            'operation' => 'update',
            'record_key' => (string) $tag->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.schema.resource_key', 'tags')
            ->where('data.schema.fields', function ($fields): bool {
                $fieldMap = collect($fields)->keyBy('name');

                return data_get($fieldMap->get('name'), 'translation_fallback.en') === 'name.ms'
                    && data_get($fieldMap->get('name.en'), 'clear_semantics.explicit_null') === 'fallback_to_name.ms'
                    && data_get($fieldMap->get('order_column'), 'clear_semantics.explicit_null') === 'recompute_with_sortable_scope';
            })
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'subdistricts',
            'operation' => 'update',
            'record_key' => (string) $subdistrictId,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.schema.resource_key', 'subdistricts')
            ->where('data.schema.fields', function ($fields): bool {
                $fieldMap = collect($fields)->keyBy('name');

                return data_get($fieldMap->get('country_id'), 'relation') === 'countries'
                    && data_get($fieldMap->get('state_id'), 'must_match') === ['country_id']
                    && data_get($fieldMap->get('district_id'), 'clear_semantics.explicit_null') === 'allowed_only_for_federal_territory_state'
                    && data_get($fieldMap->get('district_id'), 'must_match') === ['country_id', 'state_id']
                    && data_get($fieldMap->get('name'), 'normalization.trim') === true;
            })
            ->etc());
});

it('returns write schema for supported resources and rejects unknown resources', function () {
    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'speakers',
            'operation' => 'create',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.schema.resource_key', 'speakers')
            ->where('data.schema.method', 'POST')
            ->where('data.schema.transport', 'mcp')
            ->where('data.schema.tool', 'admin-create-record')
            ->where('data.schema.tool_arguments.resource_key', 'speakers')
            ->where('data.schema.tool_arguments.payload', 'object')
            ->where('data.schema.tool_arguments.validate_only', false)
            ->where('data.schema.tool_arguments.apply_defaults', false)
            ->where('data.schema.endpoint', null)
            ->where('data.schema.content_type', 'application/json')
            ->where('data.schema.media_uploads_supported', true)
            ->where('data.schema.media_upload_transport', 'json_base64_descriptor')
            ->where('data.schema.unsupported_fields', [])
            ->where('data.schema.fields', fn ($fields): bool => collect($fields)
                ->pluck('name')
                ->filter(fn (mixed $name): bool => is_string($name) && str_starts_with($name, 'clear_'))
                ->isEmpty())
            ->where('data.schema.fields', fn ($fields): bool => data_get(collect($fields)->firstWhere('name', 'avatar'), 'mcp_upload.shape') === 'file_descriptor'
                && data_get(collect($fields)->firstWhere('name', 'gallery'), 'mcp_upload.shape') === 'array<file_descriptor>'
                && data_get(collect($fields)->firstWhere('name', 'gallery'), 'mcp_upload.accepted_mime_types.0') === 'image/jpeg')
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'events',
            'operation' => 'create',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.schema.resource_key', 'events')
            ->where('data.schema.method', 'POST')
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'references',
            'operation' => 'create',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.schema.resource_key', 'references')
            ->where('data.schema.method', 'POST')
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'subdistricts',
            'operation' => 'create',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.schema.resource_key', 'subdistricts')
            ->where('data.schema.method', 'POST')
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'not-a-resource',
            'operation' => 'create',
        ])
        ->assertHasErrors(['Resource not found.']);
});

it('previews admin speaker creation through the MCP write tool without persisting the record', function () {
    ensureMcpMalaysiaCountryExists();

    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminCreateRecordTool::class, [
            'resource_key' => 'speakers',
            'validate_only' => true,
            'payload' => [
                'name' => 'Previewed Admin MCP Speaker',
                'gender' => 'male',
                'status' => 'verified',
                'is_freelance' => false,
                'is_active' => true,
                'address' => [
                    'country_id' => 132,
                ],
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'speakers')
            ->where('data.preview.validate_only', true)
            ->where('data.preview.operation', 'create')
            ->where('data.preview.normalized_payload.address.country_id', 132)
            ->where('data.preview.current_record', null)
            ->etc());

    expect(Speaker::query()->where('name', 'Previewed Admin MCP Speaker')->exists())->toBeFalse();
});

it('previews admin speaker updates through the MCP write tool without persisting the record', function () {
    ensureMcpMalaysiaCountryExists();

    $admin = adminMcpUser('super_admin');
    $speaker = Speaker::factory()->create([
        'name' => 'Previewable Admin MCP Speaker',
        'is_freelance' => false,
        'job_title' => null,
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminUpdateRecordTool::class, [
            'resource_key' => 'speakers',
            'record_key' => $speaker->getKey(),
            'validate_only' => true,
            'payload' => [
                'name' => 'Previewed Admin MCP Speaker Updated',
                'gender' => 'male',
                'status' => 'verified',
                'is_freelance' => true,
                'job_title' => 'Imam',
                'is_active' => true,
                'allow_public_event_submission' => true,
                'address' => [
                    'country_id' => 132,
                ],
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'speakers')
            ->where('data.preview.validate_only', true)
            ->where('data.preview.operation', 'update')
            ->where('data.preview.current_record.route_key', $speaker->getRouteKey())
            ->where('data.preview.normalized_payload.job_title', 'Imam')
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminUpdateRecordTool::class, [
            'resource_key' => 'speakers',
            'record_key' => $speaker->getKey(),
            'payload' => [
                'name' => 'Previewed Admin MCP Speaker Updated',
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
            ],
        ])
        ->assertHasErrors(['Destructive media clear flags are not supported through MCP. Upload a replacement file or array when the schema advertises that media field.']);

    expect(Speaker::query()->findOrFail($speaker->getKey())->name)->toBe('Previewable Admin MCP Speaker')
        ->and(Speaker::query()->findOrFail($speaker->getKey())->job_title)->toBeNull();
});

it('returns remediation details for validate-only admin create validation failures', function () {
    ensureMcpMalaysiaCountryExists();

    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminCreateRecordTool::class, [
            'resource_key' => 'speakers',
            'validate_only' => true,
            'payload' => [
                'name' => 'Remediation Preview Speaker',
            ],
        ])
        ->assertStructuredContent(fn ($json) => $json
            ->where('error.code', 'validation_error')
            ->where('error.details.fix_plan', function ($fixPlan): bool {
                $keyedFixPlan = collect($fixPlan)->keyBy('field');

                return $keyedFixPlan->get('gender') === [
                    'action' => 'set_field',
                    'field' => 'gender',
                    'value' => 'male',
                    'auto_apply_safe' => true,
                ] && $keyedFixPlan->get('status') === [
                    'action' => 'choose_one',
                    'field' => 'status',
                    'options' => ['pending', 'verified', 'rejected'],
                    'auto_apply_safe' => false,
                ] && $keyedFixPlan->get('address') === [
                    'action' => 'set_field',
                    'field' => 'address',
                    'value' => [
                        'country_id' => 132,
                        'state_id' => null,
                        'district_id' => null,
                        'subdistrict_id' => null,
                    ],
                    'auto_apply_safe' => true,
                ];
            })
            ->where('error.details.normalized_payload_preview.name', 'Remediation Preview Speaker')
            ->where('error.details.normalized_payload_preview.gender', 'male')
            ->where('error.details.normalized_payload_preview.address.country_id', 132)
            ->where('error.details.remaining_blockers', function ($remainingBlockers): bool {
                $statusBlocker = collect($remainingBlockers)->keyBy('field')->get('status');

                return is_array($statusBlocker)
                    && ($statusBlocker['field'] ?? null) === 'status'
                    && ($statusBlocker['type'] ?? null) === 'required_choice'
                    && ($statusBlocker['options'] ?? null) === ['pending', 'verified', 'rejected'];
            })
            ->where('error.details.can_retry', false)
            ->etc());
});

it('returns retryable remediation details for validate-only admin update validation failures', function () {
    ensureMcpMalaysiaCountryExists();

    $admin = adminMcpUser('super_admin');
    $speaker = Speaker::factory()->create([
        'name' => 'Retryable Admin MCP Speaker',
        'gender' => 'male',
        'status' => 'verified',
    ]);
    $originalGender = $speaker->gender;
    $originalStatus = $speaker->status;

    AdminServer::actingAs($admin)
        ->tool(AdminUpdateRecordTool::class, [
            'resource_key' => 'speakers',
            'record_key' => $speaker->getKey(),
            'validate_only' => true,
            'payload' => [
                'name' => 'Retryable Admin MCP Speaker Updated',
            ],
        ])
        ->assertStructuredContent(fn ($json) => $json
            ->where('error.code', 'validation_error')
            ->where('error.details.fix_plan', function ($fixPlan) use ($originalGender, $originalStatus): bool {
                $keyedFixPlan = collect($fixPlan)->keyBy('field');

                return $keyedFixPlan->get('gender') === [
                    'action' => 'set_field',
                    'field' => 'gender',
                    'value' => $originalGender,
                    'auto_apply_safe' => true,
                ] && $keyedFixPlan->get('status') === [
                    'action' => 'set_field',
                    'field' => 'status',
                    'value' => $originalStatus,
                    'auto_apply_safe' => true,
                ];
            })
            ->where('error.details.normalized_payload_preview.name', 'Retryable Admin MCP Speaker Updated')
            ->where('error.details.normalized_payload_preview.gender', $originalGender)
            ->where('error.details.normalized_payload_preview.status', $originalStatus)
            ->has('error.details.remaining_blockers', 0)
            ->where('error.details.can_retry', true)
            ->etc());
});

it('registers admin write tools when the MCP actor is a normalized Passport user', function () {
    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs(adminPassportUser($admin))
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'speakers',
            'operation' => 'create',
        ])
        ->assertOk()
        ->assertHasNoErrors();
});

it('creates and updates speakers through MCP write tools', function () {
    ensureMcpMalaysiaCountryExists();

    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminCreateRecordTool::class, [
            'resource_key' => 'speakers',
            'payload' => [
                'name' => 'Admin MCP Created Speaker',
                'gender' => 'male',
                'status' => 'verified',
                'is_freelance' => false,
                'is_active' => true,
                'avatar' => adminMcpImageDescriptor('admin-mcp-avatar'),
                'address' => [
                    'country_id' => 132,
                ],
            ],
        ])
        ->assertOk();

    $speaker = Speaker::query()->where('name', 'Admin MCP Created Speaker')->firstOrFail();
    $speakerId = (string) $speaker->getKey();

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'speakers',
            'operation' => 'update',
            'record_key' => $speakerId,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.schema.resource_key', 'speakers')
            ->where('data.schema.fields', function ($fields): bool {
                $fieldMap = collect($fields)->keyBy('name');
                $qualificationItemFields = collect(data_get($fieldMap->get('qualifications'), 'item_schema.fields', []))->keyBy('name');

                return data_get($fieldMap->get('address'), 'required') === false
                    && data_get($fieldMap->get('address'), 'clear_semantics.empty_object') === 'invalid_without_country'
                    && data_get($fieldMap->get('address.country_id'), 'required_when_parent_present_on_update') === true
                    && data_get($fieldMap->get('language_ids'), 'collection_semantics.submitted_array') === 'replace_relation_sync'
                    && data_get($fieldMap->get('contacts'), 'collection_semantics.explicit_null') === 'clear_collection'
                    && data_get($fieldMap->get('social_media'), 'input_normalization.platform_aliases.x.normalizes_to') === 'twitter'
                    && data_get($fieldMap->get('social_media'), 'input_normalization.platform_aliases.x.accepted_by_write_validation') === false
                    && $qualificationItemFields->has('institution')
                    && $qualificationItemFields->has('degree');
            })
            ->etc());

    expect($speaker->name)->toBe('Admin MCP Created Speaker')
        ->and($speaker->status)->toBe('verified')
        ->and($speaker->allow_public_event_submission)->toBeTrue()
        ->and($speaker->getMedia('avatar'))->toHaveCount(1)
        ->and($speaker->getFirstMedia('avatar')?->file_name)->toEndWith('.png');

    AdminServer::actingAs($admin)
        ->tool(AdminUpdateRecordTool::class, [
            'resource_key' => 'speakers',
            'record_key' => $speakerId,
            'payload' => [
                'name' => 'Admin MCP Updated Speaker',
                'gender' => 'male',
                'status' => 'verified',
                'is_freelance' => true,
                'job_title' => 'Imam',
                'is_active' => true,
                'allow_public_event_submission' => true,
                'gallery' => [
                    adminMcpImageDescriptor('admin-mcp-gallery'),
                ],
                'address' => [
                    'country_id' => 132,
                ],
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.record.attributes.name', 'Admin MCP Updated Speaker')
            ->where('data.record.attributes.job_title', 'Imam')
            ->etc());

    expect($speaker->fresh()?->getMedia('gallery'))->toHaveCount(1);
});

it('requires an explicit speaker country when the address is mutated through admin MCP write tools', function () {
    ensureMcpMalaysiaCountryExists();

    $admin = adminMcpUser('super_admin');
    $speaker = Speaker::factory()->create([
        'name' => 'Admin MCP Speaker Address Guard',
        'gender' => 'male',
        'status' => 'verified',
        'is_active' => true,
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminUpdateRecordTool::class, [
            'resource_key' => 'speakers',
            'record_key' => (string) $speaker->getKey(),
            'payload' => [
                'name' => 'Admin MCP Speaker Address Guard',
                'gender' => 'male',
                'status' => 'verified',
                'address' => [],
            ],
        ])
        ->assertStructuredContent(fn ($json) => $json
            ->where('error.code', 'validation_error')
            ->where('error.details.errors', fn ($errors): bool => (collect($errors)->get('address.country_id')[0] ?? null) === 'The address country is required.')
            ->etc());
});

it('creates and updates institutions through MCP write tools', function () {
    ensureMcpMalaysiaCountryExists();

    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminCreateRecordTool::class, [
            'resource_key' => 'institutions',
            'payload' => [
                'name' => 'Admin MCP Institution',
                'nickname' => 'MCP Surau',
                'type' => 'masjid',
                'status' => 'verified',
                'is_active' => true,
                'address' => [
                    'country_id' => 132,
                ],
            ],
        ])
        ->assertOk();

    $institution = Institution::query()->where('name', 'Admin MCP Institution')->firstOrFail();
    $institutionId = (string) $institution->getKey();
    $originalAddress = $institution->fresh()?->addressModel;
    $originalLat = $originalAddress?->lat;
    $originalLng = $originalAddress?->lng;

    expect($institution->display_name)->toBe('Admin MCP Institution (MCP Surau)')
        ->and($institution->status)->toBe('verified')
        ->and($institution->allow_public_event_submission)->toBeTrue();

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'institutions',
            'operation' => 'update',
            'record_key' => $institutionId,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.schema.resource_key', 'institutions')
            ->where('data.schema.operation', 'update')
            ->where('data.schema.transport', 'mcp')
            ->where('data.schema.tool', 'admin-update-record')
            ->where('data.schema.fields', function ($fields): bool {
                $fieldMap = collect($fields)->keyBy('name');
                $contactItemFields = collect(data_get($fieldMap->get('contacts'), 'item_schema.fields', []))->keyBy('name');

                return data_get($fieldMap->get('address'), 'required') === false
                    && data_get($fieldMap->get('address.country_id'), 'required') === false
                    && data_get($fieldMap->get('nickname'), 'clear_semantics.explicit_null') === 'preserve_existing'
                    && data_get($fieldMap->get('contacts'), 'collection_semantics.explicit_null') === 'clear_collection'
                    && $contactItemFields->has('type')
                    && $contactItemFields->has('value')
                    && data_get($fieldMap->get('social_media'), 'input_normalization.platform_aliases.x.normalizes_to') === 'twitter'
                    && data_get($fieldMap->get('social_media'), 'input_normalization.platform_aliases.x.accepted_by_write_validation') === false;
            })
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminUpdateRecordTool::class, [
            'resource_key' => 'institutions',
            'record_key' => $institutionId,
            'payload' => [
                'name' => 'Admin MCP Institution Updated',
                'nickname' => 'MCP Masjid',
                'type' => 'masjid',
                'status' => 'pending',
                'is_active' => true,
                'allow_public_event_submission' => true,
                'slug' => 'attempted-admin-institution-injection',
                'address' => [
                    'country_id' => 132,
                ],
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.record.attributes.name', 'Admin MCP Institution Updated')
            ->where('data.record.attributes.nickname', 'MCP Masjid')
            ->etc());

    expect($institution->fresh()?->slug)->not->toBe('attempted-admin-institution-injection')
        ->and(abs(((float) $institution->fresh()?->addressModel?->lat) - (float) $originalLat))->toBeLessThan(0.000001)
        ->and(abs(((float) $institution->fresh()?->addressModel?->lng) - (float) $originalLng))->toBeLessThan(0.000001);
});

it('preserves institution nickname on null and clears it on empty string through admin MCP write tools', function () {
    ensureMcpMalaysiaCountryExists();

    $admin = adminMcpUser('super_admin');
    $institution = Institution::factory()->create([
        'name' => 'Admin MCP Institution Nickname',
        'nickname' => 'MCP Surau',
        'type' => 'masjid',
        'status' => 'verified',
        'is_active' => true,
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminUpdateRecordTool::class, [
            'resource_key' => 'institutions',
            'record_key' => $institution->getKey(),
            'payload' => [
                'name' => 'Admin MCP Institution Nickname',
                'nickname' => null,
                'type' => 'masjid',
                'status' => 'verified',
                'is_active' => true,
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.record.attributes.nickname', 'MCP Surau')
            ->etc());

    expect($institution->fresh()?->nickname)->toBe('MCP Surau');

    AdminServer::actingAs($admin)
        ->tool(AdminUpdateRecordTool::class, [
            'resource_key' => 'institutions',
            'record_key' => $institution->getKey(),
            'payload' => [
                'name' => 'Admin MCP Institution Nickname',
                'nickname' => '',
                'type' => 'masjid',
                'status' => 'verified',
                'is_active' => true,
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.record.attributes.nickname', null)
            ->etc());

    expect($institution->fresh()?->nickname)->toBeNull();
});

it('surfaces venue and reference update semantics through admin MCP write schemas', function () {
    $admin = adminMcpUser('super_admin');
    $venue = Venue::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $reference = Reference::factory()->verified()->create();

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'venues',
            'operation' => 'update',
            'record_key' => (string) $venue->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.schema.resource_key', 'venues')
            ->where('data.schema.fields', function ($fields): bool {
                $fieldMap = collect($fields)->keyBy('name');

                return data_get($fieldMap->get('name'), 'required') === false
                    && data_get($fieldMap->get('address'), 'required') === false
                    && data_get($fieldMap->get('address'), 'clear_semantics.empty_object') === 'delete_existing_address'
                    && data_get($fieldMap->get('facilities'), 'input_normalization.kind') === 'facility_list_to_boolean_map'
                    && data_get($fieldMap->get('contacts'), 'collection_semantics.explicit_null') === 'clear_collection'
                    && data_get($fieldMap->get('social_media'), 'input_normalization.platform_aliases.x.normalizes_to') === 'twitter'
                    && data_get($fieldMap->get('social_media'), 'input_normalization.platform_aliases.x.accepted_by_write_validation') === false;
            })
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'references',
            'operation' => 'update',
            'record_key' => (string) $reference->getRouteKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.schema.resource_key', 'references')
            ->where('data.schema.fields', function ($fields): bool {
                $fieldMap = collect($fields)->keyBy('name');

                return data_get($fieldMap->get('author'), 'clear_semantics.explicit_null') === 'clear_to_null'
                    && data_get($fieldMap->get('publication_year'), 'normalization.empty_string_at_mutation_layer') === 'null'
                    && data_get($fieldMap->get('social_media'), 'collection_semantics.submitted_array') === 'replace_collection'
                    && data_get($fieldMap->get('social_media'), 'input_normalization.platform_aliases.x.normalizes_to') === 'twitter'
                    && data_get($fieldMap->get('social_media'), 'input_normalization.platform_aliases.x.accepted_by_write_validation') === false;
            })
            ->etc());
});

it('surfaces event series and donation channel update semantics through admin MCP write schemas', function () {
    $admin = adminMcpUser('super_admin');
    $event = Event::factory()->create([
        'status' => 'approved',
    ]);
    $series = Series::factory()->create([
        'description' => 'Series schema surface',
    ]);
    $institution = Institution::factory()->create();
    $donationChannel = DonationChannel::factory()->create([
        'donatable_type' => (string) (new Institution)->getMorphClass(),
        'donatable_id' => (string) $institution->getKey(),
        'recipient' => 'Schema Donation Channel',
        'method' => 'bank_account',
        'bank_name' => 'CIMB',
        'account_number' => '1234567890',
        'status' => 'verified',
    ]);

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'events',
            'operation' => 'update',
            'record_key' => (string) $event->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.schema.resource_key', 'events')
            ->where('data.schema.fields', function ($fields): bool {
                $fieldMap = collect($fields)->keyBy('name');
                $otherKeyPeopleFields = collect(data_get($fieldMap->get('other_key_people'), 'item_schema.fields', []))->keyBy('name');

                return data_get($fieldMap->get('title'), 'required') === false
                    && data_get($fieldMap->get('references'), 'collection_semantics.explicit_null') === 'clear_collection'
                    && data_get($fieldMap->get('speakers'), 'collection_semantics.submitted_array') === 'replace_speaker_subset_and_rebuild_key_people'
                    && data_get($fieldMap->get('organizer_type'), 'accepted_aliases.institution') === Institution::class
                    && data_get($fieldMap->get('registration_mode'), 'lock_behavior.when_event_has_registrations') === 'retain_current_value'
                    && $otherKeyPeopleFields->has('role')
                    && $otherKeyPeopleFields->has('name');
            })
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'series',
            'operation' => 'update',
            'record_key' => (string) $series->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.schema.resource_key', 'series')
            ->where('data.schema.fields', function ($fields): bool {
                $fieldMap = collect($fields)->keyBy('name');

                return data_get($fieldMap->get('slug'), 'uniqueness_scope') === 'series.slug'
                    && data_get($fieldMap->get('description'), 'clear_semantics.explicit_null') === 'clear_to_null'
                    && data_get($fieldMap->get('languages'), 'collection_semantics.submitted_array') === 'replace_relation_sync';
            })
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'donation-channels',
            'operation' => 'update',
            'record_key' => (string) $donationChannel->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.schema.resource_key', 'donation-channels')
            ->where('data.schema.fields', function ($fields): bool {
                $fieldMap = collect($fields)->keyBy('name');

                return data_get($fieldMap->get('donatable_type'), 'accepted_aliases.events') === (string) (new Event)->getMorphClass()
                    && data_get($fieldMap->get('method'), 'switch_clears_fields.ewallet') === ['bank_code', 'bank_name', 'account_number', 'duitnow_type', 'duitnow_value']
                    && data_get($fieldMap->get('label'), 'clear_semantics.explicit_null') === 'clear_to_null'
                    && data_get($fieldMap->get('reference_note'), 'normalization.empty_string_at_mutation_layer') === 'null';
            })
            ->etc());
});

it('creates and updates events through MCP write tools', function () {
    ensureMcpMalaysiaCountryExists();

    $admin = adminMcpUser('super_admin');
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

    AdminServer::actingAs($admin)
        ->tool(AdminCreateRecordTool::class, [
            'resource_key' => 'events',
            'payload' => adminMcpEventPayload([
                'institution' => $institution,
                'speaker' => $speaker,
                'reference' => $reference,
                'series' => $series,
                'domain_tag' => $domainTag,
                'discipline_tag' => $disciplineTag,
            ]),
        ])
        ->assertOk();

    $event = Event::query()->where('title', 'Admin MCP Event Created')->with(['settings', 'references', 'series', 'tags', 'keyPeople'])->firstOrFail();
    $eventId = (string) $event->getKey();
    $oldPath = route('events.show', $event, false);

    $trackedProperty = app(SignalsTracker::class)->defaultTrackedProperty();

    expect($trackedProperty)->not->toBeNull();

    SignalEvent::query()
        ->withoutOwnerScope()
        ->create([
            'tracked_property_id' => (string) $trackedProperty?->getKey(),
            'occurred_at' => now(),
            'event_name' => (string) config('signals.defaults.page_view_event_name', 'page_view'),
            'event_category' => 'page_view',
            'path' => $oldPath,
            'url' => url($oldPath),
            'currency' => 'MYR',
            'revenue_minor' => 0,
        ]);

    expect($event->live_url)->toBeNull()
        ->and($event->organizer_type)->toBe(Institution::class)
        ->and($event->settings?->registration_required)->toBeTrue()
        ->and($event->references->pluck('id')->all())->toContain($reference->getKey())
        ->and($event->series->pluck('id')->all())->toContain($series->getKey())
        ->and($event->tags->pluck('id')->all())->toContain($domainTag->getKey(), $disciplineTag->getKey())
        ->and($event->keyPeople)->toHaveCount(2);

    AdminServer::actingAs($admin)
        ->tool(AdminUpdateRecordTool::class, [
            'resource_key' => 'events',
            'record_key' => $eventId,
            'payload' => adminMcpEventPayload([
                'institution' => $institution,
                'speaker' => $speaker,
                'reference' => $reference,
                'series' => $series,
                'domain_tag' => $domainTag,
                'discipline_tag' => $disciplineTag,
            ], [
                'title' => 'Admin MCP Event Updated',
                'event_date' => '2026-06-10',
                'prayer_time' => EventPrayerTime::SelepasMaghrib->value,
                'custom_time' => null,
                'live_url' => 'https://youtube.com/watch?v=admin-mcp-event-live',
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
            ]),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.record.attributes.title', 'Admin MCP Event Updated')
            ->where('data.record.attributes.live_url', 'https://youtube.com/watch?v=admin-mcp-event-live')
            ->etc());

    $event->refresh()->load(['settings', 'references', 'series', 'tags', 'keyPeople']);

    expect($event->title)->toBe('Admin MCP Event Updated')
        ->and($event->live_url)->toBe('https://youtube.com/watch?v=admin-mcp-event-live')
        ->and($event->organizer_type)->toBe(Speaker::class)
        ->and($event->settings?->registration_required)->toBeFalse()
        ->and($event->references)->toHaveCount(0)
        ->and($event->series)->toHaveCount(0)
        ->and($event->tags->pluck('id')->all())->toContain($sourceTag->getKey())
        ->and($event->tags->pluck('id')->all())->not->toContain($domainTag->getKey(), $disciplineTag->getKey())
        ->and($event->keyPeople)->toHaveCount(0);

    $this->get($oldPath)
        ->assertRedirect(route('events.show', $event));
});

it('surfaces admin event validation failures through MCP write tools', function () {
    ensureMcpMalaysiaCountryExists();

    $admin = adminMcpUser('super_admin');
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

    AdminServer::actingAs($admin)
        ->tool(AdminCreateRecordTool::class, [
            'resource_key' => 'events',
            'payload' => adminMcpEventPayload([
                'institution' => $institution,
                'speaker' => $speaker,
                'reference' => $reference,
                'series' => $series,
                'domain_tag' => $domainTag,
                'discipline_tag' => $disciplineTag,
            ], [
                'event_type' => [EventType::KuliahCeramah->value],
                'speakers' => [],
            ]),
        ])
        ->assertHasErrors(['Sekurang-kurangnya seorang penceramah diperlukan untuk jenis majlis ini.']);
});

it('returns default autofill hints and conditional requirement feedback for admin MCP dry runs', function () {
    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminCreateRecordTool::class, [
            'resource_key' => 'events',
            'validate_only' => true,
            'apply_defaults' => true,
            'payload' => [
                'title' => 'AI Feedback Event Preview',
                'event_date' => '2026-06-10',
            ],
        ])
        ->assertStructuredContent(fn ($json) => $json
            ->where('error.code', 'validation_error')
            ->where('error.details.feedback.validate_only', true)
            ->where('error.details.feedback.apply_defaults', true)
            ->where('error.details.feedback.normalized_payload.timezone', 'Asia/Kuala_Lumpur')
            ->where('error.details.feedback.normalized_payload.event_format', EventFormat::Physical->value)
            ->where('error.details.feedback.normalized_payload.prayer_time', EventPrayerTime::LainWaktu->value)
            ->where('error.details.feedback.issues.0.field', 'custom_time')
            ->where('error.details.feedback.issues.0.severity', 'blocking_error')
            ->where('error.details.feedback.issues.0.required_because.prayer_time', EventPrayerTime::LainWaktu->value)
            ->etc());
});

it('returns enum suggestions for invalid admin MCP dry-run values', function () {
    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminCreateRecordTool::class, [
            'resource_key' => 'events',
            'validate_only' => true,
            'apply_defaults' => true,
            'payload' => [
                'title' => 'AI Feedback Invalid Enum Preview',
                'event_date' => '2026-06-10',
                'custom_time' => '8:30 PM',
                'event_format' => 'physicl',
            ],
        ])
        ->assertStructuredContent(fn ($json) => $json
            ->where('error.code', 'validation_error')
            ->where('error.details.feedback.issues.0.field', 'event_format')
            ->where('error.details.feedback.issues.0.allowed_values.0', EventFormat::Physical->value)
            ->where('error.details.feedback.issues.0.closest_valid_value', EventFormat::Physical->value)
            ->where('error.details.feedback.issues.0.suggested', EventFormat::Physical->value)
            ->where('error.details.feedback.issues.0.severity', 'blocking_error')
            ->etc());
});

it('returns structured admin MCP validation feedback outside validate-only previews', function () {
    ensureMcpMalaysiaCountryExists();

    $admin = adminMcpUser('super_admin');
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

    AdminServer::actingAs($admin)
        ->tool(AdminCreateRecordTool::class, [
            'resource_key' => 'events',
            'payload' => adminMcpEventPayload([
                'institution' => $institution,
                'speaker' => $speaker,
                'reference' => $reference,
                'series' => $series,
                'domain_tag' => $domainTag,
                'discipline_tag' => $disciplineTag,
            ], [
                'event_format' => 'physicl',
            ]),
        ])
        ->assertStructuredContent(fn ($json) => $json
            ->where('error.code', 'validation_error')
            ->where('error.details.feedback.validate_only', false)
            ->where('error.details.feedback.apply_defaults', false)
            ->where('error.details.feedback.issues.0.field', 'event_format')
            ->where('error.details.feedback.issues.0.closest_valid_value', EventFormat::Physical->value)
            ->etc());
});

it('rejects malformed MCP media descriptors through write tools', function () {
    ensureMcpMalaysiaCountryExists();

    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminCreateRecordTool::class, [
            'resource_key' => 'speakers',
            'payload' => [
                'name' => 'Speaker With Media',
                'gender' => 'male',
                'status' => 'verified',
                'is_freelance' => false,
                'is_active' => true,
                'avatar' => 'base64-data',
                'address' => [
                    'country_id' => 132,
                ],
            ],
        ])
        ->assertHasErrors(['This MCP media field must be a file descriptor object.']);

    AdminServer::actingAs($admin)
        ->tool(AdminCreateRecordTool::class, [
            'resource_key' => 'references',
            'payload' => [
                'title' => 'Reference With Media',
                'type' => 'book',
                'status' => 'verified',
                'front_cover' => 'base64-data',
            ],
        ])
        ->assertHasErrors(['This MCP media field must be a file descriptor object.']);
});

it('returns structured MCP error payloads for validation failures', function () {
    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminGetRecordTool::class, [
            'resource_key' => 'speakers',
        ])
        ->assertHasErrors(['Record key diperlukan.'])
        ->assertStructuredContent([
            'error' => [
                'code' => 'validation_error',
                'message' => 'Record key diperlukan.',
                'details' => [
                    'errors' => [
                        'record_key' => [
                            'Record key diperlukan.',
                        ],
                    ],
                ],
            ],
        ]);
});

it('rejects unexpected MCP tool arguments instead of ignoring them', function () {
    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminListResourcesTool::class, [
            'unexpected' => 'value',
        ])
        ->assertHasErrors(['Unexpected argument(s): unexpected.'])
        ->assertStructuredContent([
            'error' => [
                'code' => 'validation_error',
                'message' => 'Unexpected argument(s): unexpected.',
                'details' => [
                    'errors' => [
                        'arguments' => [
                            'Unexpected argument(s): unexpected.',
                        ],
                    ],
                ],
            ],
        ]);
});

it('accepts nullable optional MCP arguments that are advertised by the tool schema', function () {
    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminListResourcesTool::class, [
            'verbose' => null,
            'writable_only' => null,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('data.resources')
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminListRecordsTool::class, [
            'resource_key' => 'speakers',
            'search' => null,
            'page' => null,
            'per_page' => null,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('meta.resource.key', 'speakers')
            ->where('meta.pagination.page', 1)
            ->etc());
});

it('creates github issues through the admin MCP tool with Copilot model fallback', function () {
    configureGithubIssueReportingForMcp();

    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/repos/AIArmada/majlisilmu/issues' => Http::sequence()
            ->push(['message' => 'Validation Failed', 'errors' => [['field' => 'agent_assignment.model', 'message' => 'Unsupported model']]], 422)
            ->push([
                'number' => 321,
                'title' => '[Bug] Admin MCP GitHub issue',
                'url' => 'https://api.github.com/repos/AIArmada/majlisilmu/issues/321',
                'html_url' => 'https://github.com/AIArmada/majlisilmu/issues/321',
            ], 201),
    ]);

    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminCreateGitHubIssueTool::class, [
            'category' => 'bug',
            'title' => 'Admin MCP GitHub issue',
            'summary' => 'The admin MCP GitHub issue tool should fall back to the next configured model when the first one is rejected.',
            'platform' => 'chatgpt',
            'client_name' => 'ChatGPT',
            'client_version' => 'GPT-5.4',
            'tool_name' => 'admin-create-github-issue',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.issue.assigned_to_copilot', true)
            ->where('data.issue.copilot_model', 'GPT-5.2-Codex')
            ->where('data.issue.attempted_models', ['GPT-5.4', 'GPT-5.2-Codex'])
            ->etc());

    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => data_get($request->data(), 'agent_assignment.model') === 'GPT-5.4');
    Http::assertSent(fn ($request): bool => data_get($request->data(), 'agent_assignment.model') === 'GPT-5.2-Codex');
    Http::assertSent(fn ($request): bool => data_get($request->data(), 'assignees.0') === 'copilot-swe-agent[bot]');
});

it('creates github issues through the admin MCP tool without Copilot assignment when disabled', function () {
    configureGithubIssueReportingForMcp([
        'admin_copilot_assignment_enabled' => false,
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/repos/AIArmada/majlisilmu/issues' => Http::response([
            'number' => 322,
            'title' => '[Bug] Admin MCP GitHub issue without Copilot',
            'url' => 'https://api.github.com/repos/AIArmada/majlisilmu/issues/322',
            'html_url' => 'https://github.com/AIArmada/majlisilmu/issues/322',
        ], 201),
    ]);

    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminCreateGitHubIssueTool::class, [
            'category' => 'bug',
            'title' => 'Admin MCP GitHub issue without Copilot',
            'summary' => 'When Copilot assignment is disabled, the admin MCP issue tool should create a plain issue.',
            'platform' => 'chatgpt',
            'client_name' => 'ChatGPT',
            'client_version' => 'GPT-5.4',
            'tool_name' => 'admin-create-github-issue',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.issue.assigned_to_copilot', false)
            ->where('data.issue.copilot_model', null)
            ->where('data.issue.attempted_models', [])
            ->etc());

    Http::assertSentCount(1);
    Http::assertSent(function ($request): bool {
        $payload = $request->data();

        return $request->method() === 'POST'
            && (string) $request->url() === 'https://api.github.com/repos/AIArmada/majlisilmu/issues'
            && data_get($payload, 'assignees') === null
            && data_get($payload, 'agent_assignment') === null;
    });
});

it('hides the admin github issue tool when github issue reporting is disabled', function () {
    configureGithubIssueReportingForMcp(['enabled' => false]);

    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminCreateGitHubIssueTool::class, [
            'category' => 'bug',
            'title' => 'Hidden tool',
            'summary' => 'This should not be callable when disabled.',
            'platform' => 'chatgpt',
        ])
        ->assertHasErrors(['Tool [admin-create-github-issue] not found.']);
});

it('denies non-admin users from MCP tools', function () {
    $user = User::factory()->create();

    AdminServer::actingAs($user)
        ->tool(AdminListResourcesTool::class)
        ->assertHasErrors(['Forbidden.']);
});

it('serves an authenticated event stream compatibility endpoint for /mcp/admin', function () {
    $admin = adminMcpUser('super_admin');
    $token = $admin->createToken('mcp-http-test')->plainTextToken;

    $response = $this->withToken($token)
        ->get('/mcp/admin');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/event-stream');
    expect($response->streamedContent())->toContain(': keep-alive');
});

it('returns a bearer-auth challenge for unauthenticated MCP stream requests', function () {
    $response = $this->withHeaders([
        'Accept' => 'text/event-stream',
    ])->get('/mcp/admin');

    $response->assertUnauthorized();
    $response->assertHeader('WWW-Authenticate');
    expect((string) $response->headers->get('WWW-Authenticate'))->toContain('Bearer realm="mcp"');
});

it('exposes OAuth metadata for MCP clients', function () {
    $this->getJson('/.well-known/oauth-authorization-server/mcp/admin')
        ->assertOk()
        ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'))
        ->assertJsonPath('token_endpoint', route('passport.token'))
        ->assertJsonPath('registration_endpoint', url('/oauth/mcp/register'))
        ->assertJsonPath('scopes_supported.0', 'mcp:use');

    $this->getJson('/.well-known/oauth-protected-resource/mcp/admin')
        ->assertOk()
        ->assertJsonPath('resource', url('/mcp/admin'))
        ->assertJsonPath('authorization_servers.0', url('/'))
        ->assertJsonPath('scopes_supported.0', 'mcp:use');
});

it('uses a hardened default MCP OAuth allowlist', function () {
    expect(config('mcp.redirect_domains'))
        ->toContain(rtrim((string) config('app.url'), '/'))
        ->toContain('http://localhost')
        ->toContain('https://chatgpt.com')
        ->not->toContain('*');

    expect(config('mcp.custom_schemes'))->toBeArray();
});

it('only registers OAuth clients for allowed redirect domains and schemes', function () {
    config()->set('mcp.redirect_domains', [
        'https://majlisilmu.test',
        'https://chatgpt.com',
        'http://localhost',
    ]);
    config()->set('mcp.custom_schemes', ['vscode']);

    $this->postJson('/oauth/mcp/register', [
        'client_name' => 'ChatGPT',
        'redirect_uris' => ['https://chatgpt.com/connector/oauth/callback-123'],
    ])->assertOk()
        ->assertJsonPath('redirect_uris.0', 'https://chatgpt.com/connector/oauth/callback-123')
        ->assertJsonPath('scope', 'mcp:use');

    $this->postJson('/oauth/mcp/register', [
        'client_name' => 'Prefix Attack',
        'redirect_uris' => ['https://chatgpt.com.evil.example/callback'],
    ])->assertStatus(400)
        ->assertJsonPath('error', 'invalid_redirect_uri');

    $this->postJson('/oauth/mcp/register', [
        'client_name' => 'VS Code',
        'redirect_uris' => ['vscode://copilot/mcp-callback'],
    ])->assertOk()
        ->assertJsonPath('redirect_uris.0', 'vscode://copilot/mcp-callback');

    $this->postJson('/oauth/mcp/register', [
        'client_name' => 'Blocked Host',
        'redirect_uris' => ['https://evil.example/callback'],
    ])->assertStatus(400)
        ->assertJsonPath('error', 'invalid_redirect_uri');

    $this->postJson('/oauth/mcp/register', [
        'client_name' => 'Blocked Scheme',
        'redirect_uris' => ['cursor://mcp-callback'],
    ])->assertStatus(400)
        ->assertJsonPath('error', 'invalid_redirect_uri');
});

it('serves the admin MCP stream for Passport-authenticated admin users', function () {
    $admin = adminMcpUser('super_admin');

    Passport::actingAs(adminPassportUser($admin), ['mcp:use']);

    $response = $this->get('/mcp/admin');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/event-stream');
    expect($response->streamedContent())->toContain(': keep-alive');
});

it('initializes and lists admin MCP tools over the HTTP endpoint for Passport-authenticated admins', function () {
    configureGithubIssueReportingForMcp();

    $admin = adminMcpUser('super_admin');

    Passport::actingAs(adminPassportUser($admin), ['mcp:use']);

    $initialize = $this->postJson('/mcp/admin', [
        'jsonrpc' => '2.0',
        'id' => 'initialize-admin-mcp-passport',
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => [
                'name' => 'Pest',
                'version' => '1.0.0',
            ],
        ],
    ])->assertOk();

    expect($initialize->json('result.instructions'))->toContain('institution-type nouns (`masjid`, `surau`, `madrasah`, `maahad`, `pondok`, `sekolah`, `kolej`, `universiti`) should be searched as `institutions` first')
        ->toContain('venue-type nouns (`dewan`, `auditorium`, `stadium`, `perpustakaan`, `padang`, `hotel`) should be searched as `venues` first')
        ->toContain('`spaces` are finer-grained sublocations inside institutions');

    $sessionId = $initialize->headers->get('MCP-Session-Id');

    expect($sessionId)->not->toBeNull();

    $listTools = $this->withHeaders([
        'MCP-Session-Id' => (string) $sessionId,
    ])->postJson('/mcp/admin', [
        'jsonrpc' => '2.0',
        'id' => 'list-tools-admin-mcp-passport',
        'method' => 'tools/list',
        'params' => [
            'per_page' => 50,
        ],
    ])->assertOk();

    $tools = collect($listTools->json('result.tools'))->keyBy('name');

    expect($tools->keys()->all())->toContain(
        'search',
        'fetch',
        'admin-list-resources',
        'admin-get-resource-meta',
        'admin-list-records',
        'admin-get-record',
        'admin-get-record-actions',
        'admin-get-write-schema',
        'admin-get-event-moderation-schema',
        'admin-get-report-triage-schema',
        'admin-get-contribution-request-review-schema',
        'admin-get-membership-claim-review-schema',
        'admin-create-record',
        'admin-create-github-issue',
        'admin-moderate-event',
        'admin-triage-report',
        'admin-review-contribution-request',
        'admin-review-membership-claim',
        'admin-update-record',
    );

    expect($tools->keys()->all())->not->toContain('member-list-resources', 'member-list-records');

    expect($tools->get('search')['securitySchemes'] ?? [])->toContainEqual([
        'type' => 'oauth2',
        'scopes' => ['mcp:use'],
    ]);

    expect($tools->get('fetch')['securitySchemes'] ?? [])->toContainEqual([
        'type' => 'oauth2',
        'scopes' => ['mcp:use'],
    ])
        ->and($tools->get('fetch')['description'] ?? '')->toContain('not a url or file:// resource URI')
        ->and(data_get($tools->get('fetch'), 'inputSchema.properties.id.description'))->toContain('Do not pass the document url');

    expect($tools->get('admin-list-resources')['annotations'] ?? [])->toMatchArray([
        'readOnlyHint' => true,
        'idempotentHint' => true,
    ]);

    $readOnlyTools = $tools->filter(fn (array $tool): bool => data_get($tool, 'annotations.readOnlyHint') === true);

    expect($readOnlyTools->isNotEmpty())->toBeTrue()
        ->and($readOnlyTools->every(fn (array $tool): bool => data_get($tool, 'annotations.destructiveHint') === false))->toBeTrue()
        ->and($readOnlyTools->every(fn (array $tool): bool => data_get($tool, 'annotations.openWorldHint') === false))->toBeTrue();

    expect(collect((array) data_get($tools->get('admin-list-records'), 'inputSchema.properties.filters.type'))->contains('object'))->toBeTrue();

    expect($tools->get('admin-get-write-schema')['annotations'] ?? [])->toMatchArray([
        'readOnlyHint' => true,
        'idempotentHint' => true,
        'destructiveHint' => false,
        'openWorldHint' => false,
    ]);

    expect($tools->get('admin-get-event-moderation-schema')['annotations'] ?? [])->toMatchArray([
        'readOnlyHint' => true,
        'idempotentHint' => true,
        'destructiveHint' => false,
        'openWorldHint' => false,
    ]);

    expect($tools->get('admin-create-record')['annotations'] ?? [])->toMatchArray([
        'readOnlyHint' => false,
        'idempotentHint' => false,
        'destructiveHint' => false,
        'openWorldHint' => false,
    ]);

    expect($tools->get('admin-create-github-issue')['annotations'] ?? [])->toMatchArray([
        'readOnlyHint' => false,
        'idempotentHint' => false,
        'destructiveHint' => false,
        'openWorldHint' => true,
    ]);

    $githubIssueCategorySchema = data_get($tools->get('admin-create-github-issue'), 'inputSchema.properties.category');

    expect($githubIssueCategorySchema['enum'] ?? null)->toBe(GitHubIssueReportContract::categories())
        ->and($githubIssueCategorySchema['default'] ?? null)->toBe(GitHubIssueReportContract::DEFAULT_CATEGORY)
        ->and((string) ($githubIssueCategorySchema['description'] ?? ''))
        ->toContain('bug', 'docs_mismatch', 'proposal', 'feature_request', 'parameter_change', 'other');
});

it('rejects Passport-authenticated users without admin access on the admin MCP stream endpoint', function () {
    $user = User::factory()->create();

    Passport::actingAs(adminPassportUser($user), ['mcp:use']);

    $this->get('/mcp/admin')->assertForbidden();
});

it('rejects member-scoped tokens on the admin MCP stream endpoint even for dual-scope users', function () {
    $admin = adminMcpUser('super_admin');
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    app(AddMemberToSubject::class)->handle($institution, $admin, 'admin');

    $token = $admin->createToken('mcp-member-only', [McpTokenManager::MEMBER_ABILITY])->plainTextToken;

    $this->withToken($token)
        ->get('/mcp/admin')
        ->assertForbidden();
});

it('initializes and lists admin MCP tools over the HTTP endpoint', function () {
    configureGithubIssueReportingForMcp();

    $admin = adminMcpUser('super_admin');
    $token = $admin->createToken('mcp-http-test')->plainTextToken;

    $initialize = $this->withToken($token)->postJson('/mcp/admin', [
        'jsonrpc' => '2.0',
        'id' => 'initialize-admin-mcp',
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => [
                'name' => 'Pest',
                'version' => '1.0.0',
            ],
        ],
    ])->assertOk();

    $sessionId = $initialize->headers->get('MCP-Session-Id');

    expect($sessionId)->not->toBeNull();

    $listTools = $this->withToken($token)->withHeaders([
        'MCP-Session-Id' => (string) $sessionId,
    ])->postJson('/mcp/admin', [
        'jsonrpc' => '2.0',
        'id' => 'list-tools-admin-mcp',
        'method' => 'tools/list',
        'params' => [
            'per_page' => 50,
        ],
    ])->assertOk();

    $toolNames = collect($listTools->json('result.tools'))->pluck('name')->all();

    expect($toolNames)->toContain(
        'search',
        'fetch',
        'admin-list-resources',
        'admin-get-resource-meta',
        'admin-list-records',
        'admin-get-record',
        'admin-get-record-actions',
        'admin-get-write-schema',
        'admin-get-event-moderation-schema',
        'admin-get-report-triage-schema',
        'admin-get-contribution-request-review-schema',
        'admin-get-membership-claim-review-schema',
        'admin-create-record',
        'admin-create-github-issue',
        'admin-moderate-event',
        'admin-triage-report',
        'admin-review-contribution-request',
        'admin-review-membership-claim',
        'admin-update-record',
    );
});

it('searches and fetches verified documentation through admin MCP tools', function () {
    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminDocumentationSearchTool::class, [
            'query' => 'capability matrix venues',
        ])
        ->assertOk()
        ->assertName('search')
        ->assertTitle('Search Verified Documentation')
        ->assertSee([
            'docs-mcp-guide',
            'MajlisIlmu MCP Guide',
        ]);

    AdminServer::actingAs($admin)
        ->tool(AdminDocumentationFetchTool::class, [
            'id' => 'docs-mcp-guide',
        ])
        ->assertOk()
        ->assertName('fetch')
        ->assertTitle('Fetch Verified Documentation Page')
        ->assertSee([
            'docs-mcp-guide',
            '# MajlisIlmu MCP Guide',
            '### MCP capability matrix',
            'Tool-centric clients like ChatGPT and the OpenAI Responses MCP integration import tools from `tools/list`',
        ]);
});

it('lists and reads the documentation routing prompt through the admin MCP server', function () {
    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->prompt(DocumentationToolRoutingPrompt::class, [
            'topic' => 'crud',
        ])
        ->assertOk()
        ->assertName('documentation-tool-routing')
        ->assertTitle('Documentation Tool Routing')
        ->assertSee([
            'Use the verified documentation tools like this:',
            'Use `fetch` first',
            'Search `institutions` first when the noun matches an institution type',
            'Topic-specific guidance for "crud":',
            'Fetch `docs-mcp-guide` and focus on the MCP capability matrix, writable resource matrix, and preview sections.',
        ]);

    $token = $admin->createToken('mcp-admin-prompt-list-test')->plainTextToken;

    $initialize = $this->withToken($token)->postJson('/mcp/admin', [
        'jsonrpc' => '2.0',
        'id' => 'initialize-admin-mcp-prompts',
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => [
                'name' => 'Pest',
                'version' => '1.0.0',
            ],
        ],
    ])->assertOk();

    $sessionId = $initialize->headers->get('MCP-Session-Id');

    expect($sessionId)->not->toBeNull();

    $listPrompts = $this->withToken($token)->withHeaders([
        'MCP-Session-Id' => (string) $sessionId,
    ])->postJson('/mcp/admin', [
        'jsonrpc' => '2.0',
        'id' => 'list-admin-mcp-prompts',
        'method' => 'prompts/list',
        'params' => [],
    ])->assertOk();

    $prompts = collect($listPrompts->json('result.prompts'))->keyBy('name');

    expect($prompts->keys()->all())->toContain('documentation-tool-routing');
    expect($prompts->get('documentation-tool-routing'))->toMatchArray([
        'title' => 'Documentation Tool Routing',
        'description' => 'Short guidance for deciding when to use the verified documentation search and fetch tools exposed by this server, with an optional topic hint for more targeted advice.',
        'arguments' => [
            [
                'name' => 'topic',
                'description' => 'Optional focus area such as crud, auth, media uploads, runtime records, entity selection, search, or fetch.',
                'required' => false,
            ],
        ],
    ]);

    $getPrompt = $this->withToken($token)->withHeaders([
        'MCP-Session-Id' => (string) $sessionId,
    ])->postJson('/mcp/admin', [
        'jsonrpc' => '2.0',
        'id' => 'get-admin-mcp-prompt',
        'method' => 'prompts/get',
        'params' => [
            'name' => 'documentation-tool-routing',
            'arguments' => [
                'topic' => 'crud',
            ],
        ],
    ])->assertOk();

    expect($getPrompt->json('result.description'))->toBe('Short guidance for deciding when to use the verified documentation search and fetch tools exposed by this server, with an optional topic hint for more targeted advice.');
    expect($getPrompt->json('result.messages.0.content.text'))->toContain('Use `fetch` first');
    expect($getPrompt->json('result.messages.0.content.text'))->toContain('Search `institutions` first when the noun matches an institution type');
    expect($getPrompt->json('result.messages.0.content.text'))->toContain('Topic-specific guidance for "crud":');
    expect($getPrompt->json('result.messages.0.content.text'))->toContain('Fetch `docs-mcp-guide` and focus on the MCP capability matrix, writable resource matrix, and preview sections.');
});

it('lists and reads verified documentation resources through the admin MCP server', function () {
    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->resource(McpGuideResource::class)
        ->assertOk()
        ->assertName('docs-mcp-guide')
        ->assertTitle('MajlisIlmu MCP Guide')
        ->assertSee([
            '# MajlisIlmu MCP Guide',
            'Verified documentation resources',
            '### MCP capability matrix',
            '### Entity selection heuristics for record search',
            '### Quick search playbook',
            'Current structurally write-capable admin resources include:',
            '- `venues`',
        ]);

    $token = $admin->createToken('mcp-admin-resource-list-test')->plainTextToken;

    $initialize = $this->withToken($token)->postJson('/mcp/admin', [
        'jsonrpc' => '2.0',
        'id' => 'initialize-admin-mcp-resources',
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => [
                'name' => 'Pest',
                'version' => '1.0.0',
            ],
        ],
    ])->assertOk();

    $sessionId = $initialize->headers->get('MCP-Session-Id');

    expect($sessionId)->not->toBeNull();

    $listResources = $this->withToken($token)->withHeaders([
        'MCP-Session-Id' => (string) $sessionId,
    ])->postJson('/mcp/admin', [
        'jsonrpc' => '2.0',
        'id' => 'list-admin-mcp-resources',
        'method' => 'resources/list',
        'params' => [],
    ])->assertOk();

    $resources = collect($listResources->json('result.resources'))->keyBy('name');

    expect($resources->keys()->all())->toContain('docs-mcp-guide');
    expect($resources->keys()->all())->not->toContain('docs-crud-capability-matrix');
    expect($resources->get('docs-mcp-guide'))->toMatchArray([
        'uri' => 'file://docs/MAJLISILMU_MCP_GUIDE.md',
        'mimeType' => 'text/markdown',
    ]);
});

function ensureMcpMalaysiaCountryExists(): int
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

function adminPassportUser(User $user): PassportUser
{
    return PassportUser::query()->findOrFail($user->getKey());
}

function adminMcpUser(string $role): User
{
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

    return $user;
}

function runLegacyEventEnumValueRepairMigration(): void
{
    $migration = require base_path('database/migrations/2026_04_23_120000_repair_legacy_event_enum_values.php');

    assert(is_object($migration) && method_exists($migration, 'up'));

    $migration->up();
}

/**
 * @return array{filename: string, mime_type: string, content_base64: string}
 */
function adminMcpImageDescriptor(string $name): array
{
    $upload = fakeGeneratedImageUpload($name, 640, 480);
    $contents = file_get_contents((string) $upload->getRealPath());

    if (! is_string($contents) || $contents === '') {
        throw new RuntimeException('Unable to create MCP image descriptor.');
    }

    return [
        'filename' => $name,
        'mime_type' => 'image/png',
        'content_base64' => base64_encode($contents),
    ];
}

/**
 * @param  array<string, mixed>  $overrides
 */
function configureGithubIssueReportingForMcp(array $overrides = []): void
{
    config()->set('services.github.issues', array_replace([
        'enabled' => true,
        'token' => 'github-token',
        'api_base' => 'https://api.github.com',
        'api_version' => '2026-03-10',
        'repository_owner' => 'AIArmada',
        'repository_name' => 'majlisilmu',
        'base_branch' => 'main',
        'custom_agent' => null,
        'custom_instructions' => 'Use repository tests and conventions when following up.',
        'admin_model' => 'GPT-5.4',
        'admin_model_fallbacks' => ['GPT-5.2-Codex', 'Auto'],
        'admin_copilot_assignment_enabled' => true,
        'copilot_assignee' => 'copilot-swe-agent[bot]',
    ], $overrides));
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
function adminMcpEventPayload(array $fixtures, array $overrides = []): array
{
    return array_replace([
        'title' => 'Admin MCP Event Created',
        'event_date' => '2026-05-27',
        'prayer_time' => EventPrayerTime::LainWaktu->value,
        'custom_time' => '20:00',
        'end_time' => '22:00',
        'timezone' => 'Asia/Kuala_Lumpur',
        'event_format' => EventFormat::Hybrid->value,
        'visibility' => EventVisibility::Public->value,
        'event_url' => 'https://example.com/events/admin-mcp-event-created',
        'live_url' => null,
        'recording_url' => 'https://example.com/recordings/admin-mcp-event-created',
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
                'name' => 'Admin MCP Moderator',
                'is_public' => true,
                'notes' => 'Will host the session.',
            ],
        ],
        'registration_required' => true,
        'registration_mode' => RegistrationMode::Event->value,
        'is_active' => true,
    ], $overrides);
}
