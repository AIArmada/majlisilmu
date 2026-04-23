<?php

use App\Actions\Membership\AddMemberToSubject;
use App\Enums\ContributionRequestStatus;
use App\Enums\ContributionRequestType;
use App\Enums\ContributionSubjectType;
use App\Enums\EventChangeSeverity;
use App\Enums\EventChangeStatus;
use App\Enums\EventChangeType;
use App\Enums\EventStructure;
use App\Enums\MemberSubjectType;
use App\Mcp\Prompts\DocumentationToolRoutingPrompt;
use App\Mcp\Resources\Docs\McpGuideResource;
use App\Mcp\Servers\MemberServer;
use App\Mcp\Tools\Member\MemberApproveContributionRequestTool;
use App\Mcp\Tools\Member\MemberCancelContributionRequestTool;
use App\Mcp\Tools\Member\MemberCancelMembershipClaimTool;
use App\Mcp\Tools\Member\MemberCreateGitHubIssueTool;
use App\Mcp\Tools\Member\MemberDocumentationFetchTool;
use App\Mcp\Tools\Member\MemberDocumentationSearchTool;
use App\Mcp\Tools\Member\MemberGetRecordActionsTool;
use App\Mcp\Tools\Member\MemberGetRecordTool;
use App\Mcp\Tools\Member\MemberGetResourceMetaTool;
use App\Mcp\Tools\Member\MemberGetWriteSchemaTool;
use App\Mcp\Tools\Member\MemberListContributionRequestsTool;
use App\Mcp\Tools\Member\MemberListMembershipClaimsTool;
use App\Mcp\Tools\Member\MemberListRecordsTool;
use App\Mcp\Tools\Member\MemberListRelatedRecordsTool;
use App\Mcp\Tools\Member\MemberListResourcesTool;
use App\Mcp\Tools\Member\MemberRejectContributionRequestTool;
use App\Mcp\Tools\Member\MemberSubmitMembershipClaimTool;
use App\Mcp\Tools\Member\MemberUpdateRecordTool;
use App\Models\ContributionRequest;
use App\Models\Event;
use App\Models\EventChangeAnnouncement;
use App\Models\Institution;
use App\Models\MembershipClaim;
use App\Models\PassportUser;
use App\Models\Reference;
use App\Models\Speaker;
use App\Models\User;
use App\Support\GitHub\GitHubIssueReportContract;
use App\Support\Mcp\McpTokenManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('lists accessible member resources for institution members through the MCP server', function () {
    [$member] = institutionMemberMcpContext();

    MemberServer::actingAs($member)
        ->tool(MemberListResourcesTool::class)
        ->assertOk()
        ->assertHasNoErrors()
        ->assertStructuredContent(fn ($json) => $json
            ->has('data.resources', 2)
            ->where('data.resources.0.key', 'institutions')
            ->where('data.resources.1.key', 'events')
            ->etc());
});

it('lists accessible member resources for speaker members through the MCP server', function () {
    [$member] = speakerMemberMcpContext();

    MemberServer::actingAs($member)
        ->tool(MemberListResourcesTool::class)
        ->assertOk()
        ->assertHasNoErrors()
        ->assertStructuredContent(fn ($json) => $json
            ->has('data.resources', 2)
            ->where('data.resources.0.key', 'speakers')
            ->where('data.resources.1.key', 'events')
            ->etc());
});

it('returns member resource metadata, record listings, and record detail for institutions', function () {
    [$member, $institution] = institutionMemberMcpContext(role: 'admin', status: 'pending');

    MemberServer::actingAs($member)
        ->tool(MemberGetResourceMetaTool::class, [
            'resource_key' => 'institutions',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'institutions')
            ->where('data.resource.write_support.update', true)
            ->where('data.resource.mcp_tools.get_record_actions.tool', 'member-get-record-actions')
            ->etc());

    MemberServer::actingAs($member)
        ->tool(MemberListRecordsTool::class, [
            'resource_key' => 'institutions',
            'search' => $institution->name,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.0.id', $institution->getKey())
            ->where('data.0.title', $institution->name)
            ->where('meta.resource.key', 'institutions')
            ->where('meta.pagination.page', 1)
            ->etc());

    MemberServer::actingAs($member)
        ->tool(MemberGetRecordTool::class, [
            'resource_key' => 'institutions',
            'record_key' => $institution->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'institutions')
            ->where('data.record.route_key', $institution->getRouteKey())
            ->where('data.record.attributes.name', $institution->name)
            ->etc());
});

it('returns focused next-step actions for member records through the MCP server', function () {
    [$member, $institution] = institutionMemberMcpContext(role: 'admin', status: 'verified');

    MemberServer::actingAs($member)
        ->tool(MemberGetRecordActionsTool::class, [
            'resource_key' => 'institutions',
            'record_key' => $institution->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'institutions')
            ->where('data.record.route_key', $institution->getRouteKey())
            ->where('data.focus_actions.recommended_keys.0', 'get_update_schema')
            ->where('data.focus_actions.actions', fn ($actions): bool => array_diff(
                ['get_record', 'get_update_schema', 'update_record'],
                collect($actions)->pluck('key')->all(),
            ) === [])
            ->where('data.focus_actions.actions', fn ($actions): bool => data_get(collect($actions)->firstWhere('key', 'update_record'), 'tool') === 'member-update-record')
            ->where('data.focus_actions.actions', fn ($actions): bool => data_get(collect($actions)->firstWhere('key', 'update_record'), 'arguments.validate_only', false) === true)
            ->etc());
});

it('exposes timezone-aware metadata for member event resources', function () {
    [$member] = institutionMemberMcpContext();

    MemberServer::actingAs($member)
        ->tool(MemberGetResourceMetaTool::class, [
            'resource_key' => 'events',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'events')
            ->where('data.resource.timezone_sensitive', true)
            ->where('data.resource.date_semantics.local_date_filter', 'starts_on_local_date')
            ->where('data.resource.mcp_tools.list_records.arguments.starts_after', null)
            ->where('data.resource.mcp_tools.list_records.arguments.starts_before', null)
            ->where('data.resource.mcp_tools.list_records.arguments.starts_on_local_date', null)
            ->where('data.resource.mcp_tools.list_records.tool', 'member-list-records')
            ->where('data.resource.mcp_tools.get_record_actions.tool', 'member-get-record-actions')
            ->where('data.resource.mcp_tools.list_records.arguments.resource_key', 'events')
            ->etc());
});

it('filters member event records by local date through the MCP server', function () {
    [$member, $institution] = institutionMemberMcpContext();

    $member->forceFill([
        'timezone' => 'Asia/Kuala_Lumpur',
    ])->save();

    $matchingEvent = Event::factory()->create([
        'institution_id' => $institution->getKey(),
        'title' => 'Member MCP Date Match',
        'starts_at' => Carbon::parse('2026-05-01 02:00:00', 'UTC'),
        'status' => 'approved',
    ]);

    Event::factory()->create([
        'institution_id' => $institution->getKey(),
        'title' => 'Member MCP Date Miss',
        'starts_at' => Carbon::parse('2026-05-02 02:00:00', 'UTC'),
        'status' => 'approved',
    ]);

    MemberServer::actingAs($member)
        ->tool(MemberListRecordsTool::class, [
            'resource_key' => 'events',
            'starts_on_local_date' => '2026-05-01',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->has('data', 1)
            ->where('data.0.id', $matchingEvent->getKey())
            ->where('data.0.title', 'Member MCP Date Match')
            ->where('meta.resource.key', 'events')
            ->where('meta.search', null)
            ->etc());
});

it('lists related events for institution members through the member MCP server', function () {
    [$member, $institution] = institutionMemberMcpContext();

    $event = Event::factory()->create([
        'institution_id' => $institution->getKey(),
        'title' => 'Member MCP Institution Event',
    ]);

    MemberServer::actingAs($member)
        ->tool(MemberListRecordsTool::class, [
            'resource_key' => 'events',
            'search' => $event->title,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.0.id', $event->getKey())
            ->where('data.0.title', $event->title)
            ->where('meta.resource.key', 'events')
            ->etc());
});

it('lists related records through the member MCP server', function () {
    [$member, $institution] = institutionMemberMcpContext(role: 'admin');

    $parentEvent = Event::factory()->create([
        'institution_id' => $institution->getKey(),
        'title' => 'Member MCP Parent Program',
        'event_structure' => EventStructure::ParentProgram,
        'status' => 'approved',
    ]);

    $childEvent = Event::factory()->create([
        'institution_id' => $institution->getKey(),
        'parent_event_id' => $parentEvent->getKey(),
        'title' => 'Member MCP Child Event',
        'status' => 'approved',
    ]);

    MemberServer::actingAs($member)
        ->tool(MemberGetResourceMetaTool::class, [
            'resource_key' => 'events',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.mcp_tools.list_related_records.tool', 'member-list-related-records')
            ->where('data.resource.mcp_tools.list_related_records.arguments.resource_key', 'events')
            ->etc());

    MemberServer::actingAs($member)
        ->tool(MemberListRelatedRecordsTool::class, [
            'resource_key' => 'events',
            'record_key' => $parentEvent->getKey(),
            'relation' => 'child_events',
            'search' => $childEvent->title,
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.0.route_key', $childEvent->getRouteKey())
            ->where('data.0.title', 'Member MCP Child Event')
            ->where('meta.resource.key', 'events')
            ->where('meta.parent_record.route_key', $parentEvent->getRouteKey())
            ->where('meta.relation.name', 'child_events')
            ->where('meta.relation.related_resource.key', 'events')
            ->etc());
});

it('surfaces public event change projections on member event record detail through the MCP server', function () {
    [$member, $institution] = institutionMemberMcpContext(role: 'admin');

    $actor = User::factory()->create();
    $original = Event::factory()->create([
        'institution_id' => $institution->getKey(),
        'title' => 'Member MCP Change Surface Original',
        'slug' => 'member-mcp-change-surface-original',
        'status' => 'approved',
        'visibility' => 'public',
        'is_active' => true,
    ]);
    $firstReplacement = Event::factory()->create([
        'institution_id' => $institution->getKey(),
        'title' => 'Member MCP Change Surface First Replacement',
        'slug' => 'member-mcp-change-surface-first-replacement',
        'status' => 'approved',
        'visibility' => 'public',
        'is_active' => true,
    ]);
    $finalReplacement = Event::factory()->create([
        'institution_id' => $institution->getKey(),
        'title' => 'Member MCP Change Surface Final Replacement',
        'slug' => 'member-mcp-change-surface-final-replacement',
        'status' => 'approved',
        'visibility' => 'public',
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
        'visibility' => 'private',
    ]);

    MemberServer::actingAs($member)
        ->tool(MemberGetRecordTool::class, [
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

it('denies non-member users from member MCP tools', function () {
    $user = User::factory()->create();

    MemberServer::actingAs($user)
        ->tool(MemberListResourcesTool::class)
        ->assertHasErrors(['Forbidden.']);
});

it('hides member write tools when the authenticated member has no writable access', function () {
    [$member, $institution] = institutionMemberMcpContext(role: 'viewer');

    MemberServer::actingAs($member)
        ->tool(MemberGetWriteSchemaTool::class, [
            'resource_key' => 'institutions',
            'record_key' => $institution->getKey(),
        ])
        ->assertHasErrors(['Tool [member-get-write-schema] not found.']);
});

it('returns member update schema and updates institutions through member MCP write tools', function () {
    ensureMemberMcpMalaysiaCountryExists();

    [$member, $institution] = institutionMemberMcpContext(role: 'admin');
    $originalAddress = $institution->fresh()?->addressModel;
    $originalLat = $originalAddress?->lat;
    $originalLng = $originalAddress?->lng;

    MemberServer::actingAs($member)
        ->tool(MemberGetWriteSchemaTool::class, [
            'resource_key' => 'institutions',
            'record_key' => $institution->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'institutions')
            ->where('data.schema.resource_key', 'institutions')
            ->where('data.schema.operation', 'update')
            ->where('data.schema.transport', 'mcp')
            ->where('data.schema.tool', 'member-update-record')
            ->where('data.schema.tool_arguments.resource_key', 'institutions')
            ->where('data.schema.tool_arguments.record_key', $institution->getRouteKey())
            ->where('data.schema.tool_arguments.payload', 'object')
            ->where('data.schema.tool_arguments.validate_only', false)
            ->where('data.schema.endpoint', null)
            ->where('data.schema.content_type', 'application/json')
            ->where('data.schema.media_uploads_supported', true)
            ->where('data.schema.media_upload_transport', 'json_base64_descriptor')
            ->where('data.schema.unsupported_fields', [])
            ->where('data.schema.fields', function ($fields): bool {
                $fieldMap = collect($fields)->keyBy('name');
                $contactItemFields = collect(data_get($fieldMap->get('contacts'), 'item_schema.fields', []))->keyBy('name');

                return data_get($fieldMap->get('logo'), 'mcp_upload.shape') === 'file_descriptor'
                    && data_get($fieldMap->get('gallery'), 'mcp_upload.shape') === 'array<file_descriptor>'
                    && data_get($fieldMap->get('address'), 'required') === false
                    && data_get($fieldMap->get('nickname'), 'normalization.empty_string_at_mutation_layer') === 'null'
                    && data_get($fieldMap->get('contacts'), 'collection_semantics.explicit_null') === 'clear_collection'
                    && $contactItemFields->has('type')
                    && $contactItemFields->has('value')
                    && data_get($fieldMap->get('social_media'), 'input_normalization.platform_aliases.x.normalizes_to') === 'twitter'
                    && data_get($fieldMap->get('social_media'), 'input_normalization.platform_aliases.x.accepted_by_write_validation') === false;
            })
            ->etc());

    MemberServer::actingAs($member)
        ->tool(MemberUpdateRecordTool::class, [
            'resource_key' => 'institutions',
            'record_key' => $institution->getKey(),
            'payload' => [
                'name' => 'Member MCP Institution Updated',
                'nickname' => 'Member MCP Masjid',
                'type' => 'masjid',
                'status' => 'pending',
                'is_active' => true,
                'allow_public_event_submission' => true,
                'slug' => 'attempted-member-institution-injection',
                'cover' => memberMcpImageDescriptor('member-mcp-cover.png'),
                'gallery' => [
                    memberMcpImageDescriptor('member-mcp-gallery.png'),
                ],
                'address' => [
                    'country_id' => 132,
                ],
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.record.attributes.name', 'Member MCP Institution Updated')
            ->where('data.record.attributes.nickname', 'Member MCP Masjid')
            ->etc());

    expect($institution->fresh()?->name)->toBe('Member MCP Institution Updated')
        ->and($institution->fresh()?->nickname)->toBe('Member MCP Masjid')
        ->and($institution->fresh()?->slug)->not->toBe('attempted-member-institution-injection')
        ->and($institution->fresh()?->getMedia('cover'))->toHaveCount(1)
        ->and($institution->fresh()?->getMedia('gallery'))->toHaveCount(1)
        ->and(abs(((float) $institution->fresh()?->addressModel?->lat) - (float) $originalLat))->toBeLessThan(0.000001)
        ->and(abs(((float) $institution->fresh()?->addressModel?->lng) - (float) $originalLng))->toBeLessThan(0.000001);

    MemberServer::actingAs($member)
        ->tool(MemberUpdateRecordTool::class, [
            'resource_key' => 'institutions',
            'record_key' => $institution->getKey(),
            'payload' => [
                'clear_gallery' => true,
            ],
        ])
        ->assertHasErrors(['Destructive media clear flags are not supported through MCP. Upload a replacement file or array when the schema advertises that media field.']);
});

it('returns member update schema for speakers with surfaced mutation semantics', function () {
    [$member, $speaker] = speakerMemberMcpContext(role: 'admin');

    MemberServer::actingAs($member)
        ->tool(MemberGetWriteSchemaTool::class, [
            'resource_key' => 'speakers',
            'record_key' => $speaker->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'speakers')
            ->where('data.schema.resource_key', 'speakers')
            ->where('data.schema.fields', function ($fields): bool {
                $fieldMap = collect($fields)->keyBy('name');
                $qualificationItemFields = collect(data_get($fieldMap->get('qualifications'), 'item_schema.fields', []))->keyBy('name');

                return data_get($fieldMap->get('avatar'), 'mcp_upload.shape') === 'file_descriptor'
                    && data_get($fieldMap->get('gallery'), 'mcp_upload.shape') === 'array<file_descriptor>'
                    && data_get($fieldMap->get('address'), 'required') === false
                    && data_get($fieldMap->get('address'), 'clear_semantics.empty_object') === 'invalid_without_country'
                    && data_get($fieldMap->get('address.country_id'), 'required_when_parent_present_on_update') === true
                    && data_get($fieldMap->get('language_ids'), 'collection_semantics.submitted_array') === 'replace_relation_sync'
                    && data_get($fieldMap->get('social_media'), 'input_normalization.platform_aliases.x.normalizes_to') === 'twitter'
                    && data_get($fieldMap->get('social_media'), 'input_normalization.platform_aliases.x.accepted_by_write_validation') === false
                    && $qualificationItemFields->has('institution')
                    && $qualificationItemFields->has('degree');
            })
            ->etc());
});

it('requires an explicit speaker country when the address is mutated through member MCP write tools', function () {
    [$member, $speaker] = speakerMemberMcpContext(role: 'admin');

    MemberServer::actingAs($member)
        ->tool(MemberUpdateRecordTool::class, [
            'resource_key' => 'speakers',
            'record_key' => $speaker->getKey(),
            'payload' => [
                'name' => $speaker->name,
                'gender' => 'male',
                'status' => 'verified',
                'address' => [],
            ],
        ])
        ->assertStructuredContent(fn ($json) => $json
            ->where('error.code', 'validation_error')
            ->where('error.details.errors', fn($errors): bool => (collect($errors)->get('address.country_id')[0] ?? null) === 'The address country is required.')
            ->etc());
});

it('returns member update schema for references with surfaced mutation semantics', function () {
    [$member, $reference] = referenceMemberMcpContext(role: 'admin');

    MemberServer::actingAs($member)
        ->tool(MemberGetWriteSchemaTool::class, [
            'resource_key' => 'references',
            'record_key' => $reference->getRouteKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'references')
            ->where('data.schema.resource_key', 'references')
            ->where('data.schema.fields', function ($fields): bool {
                $fieldMap = collect($fields)->keyBy('name');

                return data_get($fieldMap->get('front_cover'), 'mcp_upload.shape') === 'file_descriptor'
                    && data_get($fieldMap->get('gallery'), 'mcp_upload.shape') === 'array<file_descriptor>'
                    && data_get($fieldMap->get('author'), 'clear_semantics.explicit_null') === 'clear_to_null'
                    && data_get($fieldMap->get('publication_year'), 'normalization.empty_string_at_mutation_layer') === 'null'
                    && data_get($fieldMap->get('social_media'), 'collection_semantics.submitted_array') === 'replace_collection'
                    && data_get($fieldMap->get('social_media'), 'input_normalization.platform_aliases.x.normalizes_to') === 'twitter'
                    && data_get($fieldMap->get('social_media'), 'input_normalization.platform_aliases.x.accepted_by_write_validation') === false;
            })
            ->etc());
});

it('returns member update schema for events with surfaced mutation semantics', function () {
    [$member, $institution] = institutionMemberMcpContext(role: 'admin');

    $event = Event::factory()->create([
        'institution_id' => $institution->getKey(),
        'status' => 'approved',
    ]);

    MemberServer::actingAs($member)
        ->tool(MemberGetWriteSchemaTool::class, [
            'resource_key' => 'events',
            'record_key' => $event->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'events')
            ->where('data.schema.resource_key', 'events')
            ->where('data.schema.fields', function ($fields): bool {
                $fieldMap = collect($fields)->keyBy('name');
                $otherKeyPeopleFields = collect(data_get($fieldMap->get('other_key_people'), 'item_schema.fields', []))->keyBy('name');

                return data_get($fieldMap->get('title'), 'required') === false
                    && data_get($fieldMap->get('references'), 'collection_semantics.explicit_null') === 'clear_collection'
                    && data_get($fieldMap->get('speakers'), 'collection_semantics.submitted_array') === 'replace_speaker_subset_and_rebuild_key_people'
                    && data_get($fieldMap->get('organizer_type'), 'accepted_aliases.speaker') === Speaker::class
                    && data_get($fieldMap->get('registration_mode'), 'lock_behavior.when_event_has_registrations') === 'retain_current_value'
                    && $otherKeyPeopleFields->has('role')
                    && $otherKeyPeopleFields->has('name');
            })
            ->etc());
});

it('registers member write tools when the MCP actor is a normalized Passport user', function () {
    [$member, $institution] = institutionMemberMcpContext(role: 'admin');

    MemberServer::actingAs(memberPassportUser($member))
        ->tool(MemberGetWriteSchemaTool::class, [
            'resource_key' => 'institutions',
            'record_key' => $institution->getKey(),
        ])
        ->assertOk()
        ->assertHasNoErrors();
});

it('previews member institution updates without persisting the record', function () {
    ensureMemberMcpMalaysiaCountryExists();

    [$member, $institution] = institutionMemberMcpContext(role: 'admin');

    MemberServer::actingAs($member)
        ->tool(MemberUpdateRecordTool::class, [
            'resource_key' => 'institutions',
            'record_key' => $institution->getKey(),
            'validate_only' => true,
            'payload' => [
                'name' => 'Previewed Member MCP Institution',
                'nickname' => 'Previewed Masjid',
                'type' => 'masjid',
                'status' => 'pending',
                'is_active' => true,
                'allow_public_event_submission' => true,
                'address' => [
                    'country_id' => 132,
                ],
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.resource.key', 'institutions')
            ->where('data.preview.validate_only', true)
            ->where('data.preview.operation', 'update')
            ->where('data.preview.current_record.route_key', $institution->getRouteKey())
            ->where('data.preview.normalized_payload.name', 'Previewed Member MCP Institution')
            ->etc());

    expect($institution->fresh()?->name)->not->toBe('Previewed Member MCP Institution');
});

it('lists and processes contribution requests through member MCP workflow tools', function () {
    [$member, $institution] = institutionMemberMcpContext(role: 'admin');

    $ownRequest = ContributionRequest::factory()->create([
        'type' => ContributionRequestType::Update,
        'subject_type' => ContributionSubjectType::Institution,
        'entity_type' => $institution->getMorphClass(),
        'entity_id' => $institution->getKey(),
        'proposer_id' => $member->getKey(),
        'status' => ContributionRequestStatus::Pending,
        'proposed_data' => [
            'description' => 'Own pending request.',
        ],
        'original_data' => [
            'description' => (string) $institution->description,
        ],
    ]);

    $reviewRequest = ContributionRequest::factory()->create([
        'type' => ContributionRequestType::Update,
        'subject_type' => ContributionSubjectType::Institution,
        'entity_type' => $institution->getMorphClass(),
        'entity_id' => $institution->getKey(),
        'status' => ContributionRequestStatus::Pending,
        'proposed_data' => [
            'description' => 'Approved through member MCP.',
        ],
        'original_data' => [
            'description' => (string) $institution->description,
        ],
    ]);

    $rejectRequest = ContributionRequest::factory()->create([
        'type' => ContributionRequestType::Update,
        'subject_type' => ContributionSubjectType::Institution,
        'entity_type' => $institution->getMorphClass(),
        'entity_id' => $institution->getKey(),
        'status' => ContributionRequestStatus::Pending,
        'proposed_data' => [
            'description' => 'Rejected through member MCP.',
        ],
        'original_data' => [
            'description' => (string) $institution->description,
        ],
    ]);

    MemberServer::actingAs($member)
        ->tool(MemberListContributionRequestsTool::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.my_requests', fn ($requests): bool => collect($requests)->pluck('id')->contains($ownRequest->getKey()))
            ->where('data.pending_approvals', fn ($requests): bool => collect($requests)->pluck('id')->contains($reviewRequest->getKey())
                && collect($requests)->pluck('id')->contains($rejectRequest->getKey()))
            ->etc());

    MemberServer::actingAs($member)
        ->tool(MemberApproveContributionRequestTool::class, [
            'request_id' => $reviewRequest->getKey(),
            'reviewer_note' => 'Approved through member MCP.',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.request.id', $reviewRequest->getKey())
            ->where('data.request.status', 'approved')
            ->where('data.request.reviewer_note', 'Approved through member MCP.')
            ->etc());

    MemberServer::actingAs($member)
        ->tool(MemberRejectContributionRequestTool::class, [
            'request_id' => $rejectRequest->getKey(),
            'reason_code' => 'needs_more_evidence',
            'reviewer_note' => 'Need stronger evidence.',
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.request.id', $rejectRequest->getKey())
            ->where('data.request.status', 'rejected')
            ->where('data.request.reason_code', 'needs_more_evidence')
            ->etc());

    MemberServer::actingAs($member)
        ->tool(MemberCancelContributionRequestTool::class, [
            'request_id' => $ownRequest->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.request.id', $ownRequest->getKey())
            ->where('data.request.status', 'cancelled')
            ->etc());

    expect($reviewRequest->fresh()?->status)->toBe(ContributionRequestStatus::Approved)
        ->and($rejectRequest->fresh()?->status)->toBe(ContributionRequestStatus::Rejected)
        ->and($ownRequest->fresh()?->status)->toBe(ContributionRequestStatus::Cancelled)
        ->and($institution->fresh()?->description)->toBe('Approved through member MCP.');
});

it('lists submits and cancels membership claims through member MCP workflow tools', function () {
    [$member] = institutionMemberMcpContext(role: 'admin');

    $listedInstitution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    $claimTarget = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $listedClaim = MembershipClaim::factory()
        ->forInstitution($listedInstitution)
        ->create([
            'claimant_id' => $member->getKey(),
            'status' => 'pending',
        ]);

    MemberServer::actingAs($member)
        ->tool(MemberListMembershipClaimsTool::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data', fn ($claims): bool => collect($claims)->pluck('id')->contains($listedClaim->getKey()))
            ->etc());

    MemberServer::actingAs($member)
        ->tool(MemberSubmitMembershipClaimTool::class, [
            'subject_type' => MemberSubjectType::Institution->value,
            'subject' => $claimTarget->getKey(),
            'justification' => 'I help manage this institution.',
            'evidence' => [
                memberMcpImageDescriptor('member-mcp-claim-evidence.png'),
            ],
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.claim.subject_type', 'institution')
            ->where('data.claim.status', 'pending')
            ->where('data.claim.can_cancel', true)
            ->etc());

    $claim = MembershipClaim::query()
        ->where('claimant_id', $member->getKey())
        ->where('subject_id', $claimTarget->getKey())
        ->latest('created_at')
        ->firstOrFail();

    expect($claim->getMedia('evidence'))->toHaveCount(1);

    MemberServer::actingAs($member)
        ->tool(MemberCancelMembershipClaimTool::class, [
            'claim_id' => $claim->getKey(),
        ])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('data.claim.id', $claim->getKey())
            ->where('data.claim.status', 'cancelled')
            ->etc());

    expect($claim->fresh()?->status->value)->toBe('cancelled');
});

it('creates plain github issues through the member MCP tool', function () {
    configureGithubIssueReportingForMemberMcp();

    Http::preventStrayRequests();
    Http::fake([
        'https://api.github.com/repos/AIArmada/majlisilmu/issues' => Http::response([
            'number' => 654,
            'title' => '[Bug] Member MCP GitHub issue',
            'url' => 'https://api.github.com/repos/AIArmada/majlisilmu/issues/654',
            'html_url' => 'https://github.com/AIArmada/majlisilmu/issues/654',
        ], 201),
    ]);

    [$member] = institutionMemberMcpContext(role: 'admin');

    MemberServer::actingAs($member)
        ->tool(MemberCreateGitHubIssueTool::class, [
            'category' => 'bug',
            'title' => 'Member MCP GitHub issue',
            'summary' => 'The member MCP GitHub issue tool should create a plain issue without Copilot assignment.',
            'platform' => 'chatgpt',
            'client_name' => 'ChatGPT',
            'client_version' => 'GPT-5.4',
            'tool_name' => 'member-create-github-issue',
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

it('hides the member github issue tool when github issue reporting is disabled', function () {
    configureGithubIssueReportingForMemberMcp(['enabled' => false]);

    [$member] = institutionMemberMcpContext(role: 'admin');

    MemberServer::actingAs($member)
        ->tool(MemberCreateGitHubIssueTool::class, [
            'category' => 'bug',
            'title' => 'Hidden tool',
            'summary' => 'This should not be callable when disabled.',
            'platform' => 'chatgpt',
        ])
        ->assertHasErrors(['Tool [member-create-github-issue] not found.']);
});

it('keeps admin and member MCP boundaries separate', function () {
    $admin = globalRoleMcpUser('super_admin');

    MemberServer::actingAs($admin)
        ->tool(MemberListResourcesTool::class)
        ->assertHasErrors(['Forbidden.']);
});

it('serves an authenticated event stream compatibility endpoint for /mcp/member', function () {
    [$member] = institutionMemberMcpContext();
    $token = $member->createToken('mcp-member-http-test', [McpTokenManager::MEMBER_ABILITY])->plainTextToken;

    $response = $this->withToken($token)
        ->get('/mcp/member');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/event-stream');
    expect($response->streamedContent())->toContain(': keep-alive');
});

it('returns a bearer-auth challenge for unauthenticated member MCP stream requests', function () {
    $response = $this->withHeaders([
        'Accept' => 'text/event-stream',
    ])->get('/mcp/member');

    $response->assertUnauthorized();
    $response->assertHeader('WWW-Authenticate');
    expect((string) $response->headers->get('WWW-Authenticate'))->toContain('Bearer realm="mcp"');
});

it('serves the member MCP stream for Passport-authenticated eligible users', function () {
    [$member] = institutionMemberMcpContext();

    Passport::actingAs(memberPassportUser($member), ['mcp:use']);

    $response = $this->get('/mcp/member');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/event-stream');
    expect($response->streamedContent())->toContain(': keep-alive');
});

it('initializes and lists member MCP tools over the HTTP endpoint for Passport-authenticated members', function () {
    configureGithubIssueReportingForMemberMcp();

    [$member] = institutionMemberMcpContext(role: 'admin');

    Passport::actingAs(memberPassportUser($member), ['mcp:use']);

    $initialize = $this->postJson('/mcp/member', [
        'jsonrpc' => '2.0',
        'id' => 'initialize-member-mcp-passport',
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
    ])->postJson('/mcp/member', [
        'jsonrpc' => '2.0',
        'id' => 'list-tools-member-mcp-passport',
        'method' => 'tools/list',
        'params' => [
            'per_page' => 50,
        ],
    ])->assertOk();

    $tools = collect($listTools->json('result.tools'))->keyBy('name');

    expect($tools->keys()->all())->toContain(
        'search',
        'fetch',
        'member-list-resources',
        'member-get-resource-meta',
        'member-list-records',
        'member-list-related-records',
        'member-get-record',
        'member-get-record-actions',
        'member-get-write-schema',
        'member-list-contribution-requests',
        'member-approve-contribution-request',
        'member-reject-contribution-request',
        'member-cancel-contribution-request',
        'member-list-membership-claims',
        'member-submit-membership-claim',
        'member-cancel-membership-claim',
        'member-create-github-issue',
        'member-update-record',
    );

    expect($tools->get('search')['securitySchemes'] ?? [])->toContainEqual([
        'type' => 'oauth2',
        'scopes' => ['mcp:use'],
    ]);

    expect($tools->get('fetch')['securitySchemes'] ?? [])->toContainEqual([
        'type' => 'oauth2',
        'scopes' => ['mcp:use'],
    ]);

    expect($tools->get('member-list-resources')['annotations'] ?? [])->toMatchArray([
        'readOnlyHint' => true,
        'idempotentHint' => true,
    ]);

    $readOnlyTools = $tools->filter(fn (array $tool): bool => data_get($tool, 'annotations.readOnlyHint') === true);

    expect($readOnlyTools->isNotEmpty())->toBeTrue()
        ->and($readOnlyTools->every(fn (array $tool): bool => data_get($tool, 'annotations.destructiveHint') === false))->toBeTrue()
        ->and($readOnlyTools->every(fn (array $tool): bool => data_get($tool, 'annotations.openWorldHint') === false))->toBeTrue();

    expect($tools->get('member-list-related-records')['annotations'] ?? [])->toMatchArray([
        'readOnlyHint' => true,
        'idempotentHint' => true,
        'destructiveHint' => false,
        'openWorldHint' => false,
    ]);

    expect($tools->get('member-get-write-schema')['annotations'] ?? [])->toMatchArray([
        'readOnlyHint' => true,
        'idempotentHint' => true,
        'destructiveHint' => false,
        'openWorldHint' => false,
    ]);

    expect($tools->get('member-update-record')['annotations'] ?? [])->toMatchArray([
        'readOnlyHint' => false,
        'idempotentHint' => false,
        'destructiveHint' => false,
        'openWorldHint' => false,
    ]);

    expect($tools->get('member-create-github-issue')['annotations'] ?? [])->toMatchArray([
        'readOnlyHint' => false,
        'idempotentHint' => false,
        'destructiveHint' => false,
        'openWorldHint' => true,
    ]);

    $githubIssueCategorySchema = data_get($tools->get('member-create-github-issue'), 'inputSchema.properties.category');

    expect($githubIssueCategorySchema['enum'] ?? null)->toBe(GitHubIssueReportContract::categories())
        ->and($githubIssueCategorySchema['default'] ?? null)->toBe(GitHubIssueReportContract::DEFAULT_CATEGORY)
        ->and((string) ($githubIssueCategorySchema['description'] ?? ''))
        ->toContain('bug', 'docs_mismatch', 'proposal', 'feature_request', 'parameter_change', 'other');
});

it('returns forbidden for Passport-authenticated users without member access on the member MCP stream endpoint', function () {
    $user = User::factory()->create();

    Passport::actingAs(memberPassportUser($user), ['mcp:use']);

    $this->get('/mcp/member')->assertForbidden();
});

it('returns forbidden for authenticated users without member access on the member MCP stream endpoint', function () {
    $user = User::factory()->create();
    $token = $user->createToken('mcp-member-http-test', [McpTokenManager::MEMBER_ABILITY])->plainTextToken;

    $this->withToken($token)
        ->get('/mcp/member')
        ->assertForbidden();
});

it('rejects admin-scoped tokens on the member MCP stream endpoint even for dual-scope users', function () {
    $member = globalRoleMcpUser('super_admin');
    $institution = Institution::factory()->create([
        'status' => 'verified',
    ]);

    app(AddMemberToSubject::class)->handle($institution, $member, 'admin');

    $token = $member->createToken('mcp-admin-only', [McpTokenManager::ADMIN_ABILITY])->plainTextToken;

    $this->withToken($token)
        ->get('/mcp/member')
        ->assertForbidden();
});

it('rejects legacy wildcard MCP tokens on the member MCP stream endpoint', function () {
    $member = globalRoleMcpUser('super_admin');
    $institution = Institution::factory()->create([
        'status' => 'verified',
    ]);

    app(AddMemberToSubject::class)->handle($institution, $member, 'admin');

    $token = $member->createToken('legacy-admin-mcp')->plainTextToken;

    $this->withToken($token)
        ->get('/mcp/member')
        ->assertForbidden();
});

it('initializes and lists member MCP tools over the HTTP endpoint', function () {
    configureGithubIssueReportingForMemberMcp();

    [$member] = speakerMemberMcpContext(role: 'admin');
    $token = $member->createToken('mcp-member-http-test', [McpTokenManager::MEMBER_ABILITY])->plainTextToken;

    $initialize = $this->withToken($token)->postJson('/mcp/member', [
        'jsonrpc' => '2.0',
        'id' => 'initialize-member-mcp',
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
    ])->postJson('/mcp/member', [
        'jsonrpc' => '2.0',
        'id' => 'list-tools-member-mcp',
        'method' => 'tools/list',
        'params' => [
            'per_page' => 50,
        ],
    ])->assertOk();

    $toolNames = collect($listTools->json('result.tools'))->pluck('name')->all();

    expect($toolNames)->toContain(
        'search',
        'fetch',
        'member-list-resources',
        'member-get-resource-meta',
        'member-list-records',
        'member-list-related-records',
        'member-get-record',
        'member-get-record-actions',
        'member-get-write-schema',
        'member-list-contribution-requests',
        'member-approve-contribution-request',
        'member-reject-contribution-request',
        'member-cancel-contribution-request',
        'member-list-membership-claims',
        'member-submit-membership-claim',
        'member-cancel-membership-claim',
        'member-create-github-issue',
        'member-update-record',
    );
});

it('searches and fetches verified documentation through member MCP tools', function () {
    [$member] = institutionMemberMcpContext(role: 'admin');

    MemberServer::actingAs($member)
        ->tool(MemberDocumentationSearchTool::class, [
            'query' => 'member write capable resources',
        ])
        ->assertOk()
        ->assertName('search')
        ->assertTitle('Search Verified Documentation')
        ->assertSee([
            'docs-mcp-guide',
            'MajlisIlmu MCP Guide',
        ]);

    MemberServer::actingAs($member)
        ->tool(MemberDocumentationFetchTool::class, [
            'id' => 'docs-mcp-guide',
        ])
        ->assertOk()
        ->assertName('fetch')
        ->assertTitle('Fetch Verified Documentation Page')
        ->assertSee([
            'docs-mcp-guide',
            '# MajlisIlmu MCP Guide',
            'Current member-write-capable resources include:',
        ]);
});

it('lists and reads the documentation routing prompt through the member MCP server', function () {
    [$member] = institutionMemberMcpContext(role: 'admin');

    MemberServer::actingAs($member)
        ->prompt(DocumentationToolRoutingPrompt::class, [
            'topic' => 'media uploads',
        ])
        ->assertOk()
        ->assertName('documentation-tool-routing')
        ->assertTitle('Documentation Tool Routing')
        ->assertSee([
            'Use the verified documentation tools like this:',
            'Use `fetch` first',
            'Search `institutions` first when the noun matches an institution type',
            'Topic-specific guidance for "media uploads":',
            'Fetch `docs-mcp-guide` and focus on the MCP media/file upload contract and preview rules sections.',
        ]);

    $token = $member->createToken('mcp-member-prompt-list-test', [McpTokenManager::MEMBER_ABILITY])->plainTextToken;

    $initialize = $this->withToken($token)->postJson('/mcp/member', [
        'jsonrpc' => '2.0',
        'id' => 'initialize-member-mcp-prompts',
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
    ])->postJson('/mcp/member', [
        'jsonrpc' => '2.0',
        'id' => 'list-member-mcp-prompts',
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
    ])->postJson('/mcp/member', [
        'jsonrpc' => '2.0',
        'id' => 'get-member-mcp-prompt',
        'method' => 'prompts/get',
        'params' => [
            'name' => 'documentation-tool-routing',
            'arguments' => [
                'topic' => 'media uploads',
            ],
        ],
    ])->assertOk();

    expect($getPrompt->json('result.description'))->toBe('Short guidance for deciding when to use the verified documentation search and fetch tools exposed by this server, with an optional topic hint for more targeted advice.');
    expect($getPrompt->json('result.messages.0.content.text'))->toContain('Use `fetch` first');
    expect($getPrompt->json('result.messages.0.content.text'))->toContain('Search `institutions` first when the noun matches an institution type');
    expect($getPrompt->json('result.messages.0.content.text'))->toContain('Topic-specific guidance for "media uploads":');
    expect($getPrompt->json('result.messages.0.content.text'))->toContain('Fetch `docs-mcp-guide` and focus on the MCP media/file upload contract and preview rules sections.');
});

it('lists and reads verified documentation resources through the member MCP server', function () {
    [$member] = institutionMemberMcpContext(role: 'admin');

    MemberServer::actingAs($member)
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
            'Current member-write-capable resources include:',
            '| `member-list-related-records` | Traverse a named relation on a member record | `resource_key`, `record_key`, `relation`, `page?`, `per_page?` |',
            '| `member-update-record` | Update or preview a writable member record | `resource_key`, `record_key`, `payload`, `validate_only?` |',
        ]);

    $token = $member->createToken('mcp-member-resource-list-test', [McpTokenManager::MEMBER_ABILITY])->plainTextToken;

    $initialize = $this->withToken($token)->postJson('/mcp/member', [
        'jsonrpc' => '2.0',
        'id' => 'initialize-member-mcp-resources',
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
    ])->postJson('/mcp/member', [
        'jsonrpc' => '2.0',
        'id' => 'list-member-mcp-resources',
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

/**
 * @return array{0: User, 1: Institution}
 */
function institutionMemberMcpContext(string $role = 'viewer', string $status = 'verified'): array
{
    $institution = Institution::factory()->create([
        'status' => $status,
    ]);
    $member = User::factory()->create([
        'phone' => '+60112223344',
        'phone_verified_at' => now(),
    ]);

    app(AddMemberToSubject::class)->handle($institution, $member, $role);

    return [$member, $institution];
}

function memberPassportUser(User $user): PassportUser
{
    return PassportUser::query()->findOrFail($user->getKey());
}

/**
 * @return array{0: User, 1: Speaker}
 */
function speakerMemberMcpContext(string $role = 'viewer', string $status = 'verified'): array
{
    $speaker = Speaker::factory()->create([
        'status' => $status,
        'is_active' => $status === 'verified',
    ]);
    $member = User::factory()->create([
        'phone' => '+60112223344',
        'phone_verified_at' => now(),
    ]);

    app(AddMemberToSubject::class)->handle($speaker, $member, $role);

    return [$member, $speaker];
}

/**
 * @return array{0: User, 1: Reference}
 */
function referenceMemberMcpContext(string $role = 'viewer', string $status = 'verified'): array
{
    $reference = Reference::factory()->create([
        'status' => $status,
        'is_active' => $status === 'verified',
    ]);
    $member = User::factory()->create([
        'phone' => '+60112223344',
        'phone_verified_at' => now(),
    ]);

    app(AddMemberToSubject::class)->handle($reference, $member, $role);

    return [$member, $reference];
}

function globalRoleMcpUser(string $role): User
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
function memberMcpImageDescriptor(string $name): array
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

function ensureMemberMcpMalaysiaCountryExists(): int
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
 * @param  array<string, mixed>  $overrides
 */
function configureGithubIssueReportingForMemberMcp(array $overrides = []): void
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
