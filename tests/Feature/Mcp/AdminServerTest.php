<?php

use App\Actions\Membership\AddMemberToSubject;
use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\RegistrationMode;
use App\Mcp\Prompts\DocumentationToolRoutingPrompt;
use App\Mcp\Resources\Docs\McpGuideResource;
use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\AdminCreateGitHubIssueTool;
use App\Mcp\Tools\Admin\AdminCreateRecordTool;
use App\Mcp\Tools\Admin\AdminDocumentationFetchTool;
use App\Mcp\Tools\Admin\AdminDocumentationSearchTool;
use App\Mcp\Tools\Admin\AdminGetRecordTool;
use App\Mcp\Tools\Admin\AdminGetResourceMetaTool;
use App\Mcp\Tools\Admin\AdminGetWriteSchemaTool;
use App\Mcp\Tools\Admin\AdminListRecordsTool;
use App\Mcp\Tools\Admin\AdminListRelatedRecordsTool;
use App\Mcp\Tools\Admin\AdminListResourcesTool;
use App\Mcp\Tools\Admin\AdminUpdateRecordTool;
use App\Models\Event;
use App\Models\Institution;
use App\Models\PassportUser;
use App\Models\Reference;
use App\Models\Series;
use App\Models\Speaker;
use App\Models\Tag;
use App\Models\User;
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
        ->assertSee(['speakers', 'events', 'institutions', 'references', 'subdistricts'])
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
            ->has('data.resources', 6)
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
            ->where('meta.resource.key', 'events')
            ->where('meta.search', null)
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

it('returns remediation details for validate only admin create validation failures', function () {
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
            ->where('error.details.fix_plan.0.action', 'set_field')
            ->where('error.details.fix_plan.0.field', 'gender')
            ->where('error.details.fix_plan.0.value', 'male')
            ->where('error.details.fix_plan.1.action', 'choose_one')
            ->where('error.details.fix_plan.1.field', 'status')
            ->where('error.details.fix_plan.1.options.0', 'pending')
            ->where('error.details.fix_plan.1.options.1', 'verified')
            ->where('error.details.fix_plan.1.options.2', 'rejected')
            ->where('error.details.fix_plan.2.action', 'set_field')
            ->where('error.details.fix_plan.2.field', 'address')
            ->where('error.details.normalized_payload_preview.name', 'Remediation Preview Speaker')
            ->where('error.details.normalized_payload_preview.gender', 'male')
            ->where('error.details.normalized_payload_preview.address.country_id', 132)
            ->where('error.details.remaining_blockers.0.field', 'status')
            ->where('error.details.remaining_blockers.0.type', 'required_choice')
            ->where('error.details.can_retry', false)
            ->etc());
});

it('returns retryable remediation details for validate only admin update validation failures', function () {
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
            ->where('error.details.fix_plan.0.action', 'set_field')
            ->where('error.details.fix_plan.0.field', 'gender')
            ->where('error.details.fix_plan.0.value', $originalGender)
            ->where('error.details.fix_plan.1.action', 'set_field')
            ->where('error.details.fix_plan.1.field', 'status')
            ->where('error.details.fix_plan.1.value', $originalStatus)
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
                'address' => [],
            ],
        ])
        ->assertOk();

    $speaker = Speaker::query()->where('name', 'Admin MCP Created Speaker')->firstOrFail();
    $speakerId = (string) $speaker->getKey();

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
                'address' => [],
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.record.attributes.name', 'Admin MCP Updated Speaker')
            ->where('data.record.attributes.job_title', 'Imam')
            ->etc());

    expect($speaker->fresh()?->getMedia('gallery'))->toHaveCount(1);
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
        'params' => [],
    ])->assertOk();

    $tools = collect($listTools->json('result.tools'))->keyBy('name');

    expect($tools->keys()->all())->toContain(
        'search',
        'fetch',
        'admin-list-resources',
        'admin-get-resource-meta',
        'admin-list-records',
        'admin-get-record',
        'admin-get-write-schema',
        'admin-create-record',
        'admin-create-github-issue',
        'admin-update-record',
    );

    expect($tools->get('search')['securitySchemes'] ?? [])->toContainEqual([
        'type' => 'oauth2',
        'scopes' => ['mcp:use'],
    ]);

    expect($tools->get('fetch')['securitySchemes'] ?? [])->toContainEqual([
        'type' => 'oauth2',
        'scopes' => ['mcp:use'],
    ]);

    expect($tools->get('admin-list-resources')['annotations'] ?? [])->toMatchArray([
        'readOnlyHint' => true,
        'idempotentHint' => true,
    ]);

    expect(collect((array) data_get($tools->get('admin-list-records'), 'inputSchema.properties.filters.type'))->contains('object'))->toBeTrue();

    expect($tools->get('admin-get-write-schema')['annotations'] ?? [])->toMatchArray([
        'readOnlyHint' => true,
        'idempotentHint' => true,
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
        'params' => [],
    ])->assertOk();

    $toolNames = collect($listTools->json('result.tools'))->pluck('name')->all();

    expect($toolNames)->toContain(
        'search',
        'fetch',
        'admin-list-resources',
        'admin-get-resource-meta',
        'admin-list-records',
        'admin-get-record',
        'admin-get-write-schema',
        'admin-create-record',
        'admin-create-github-issue',
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
