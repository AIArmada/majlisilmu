<?php

use App\Actions\Membership\AddMemberToSubject;
use App\Mcp\Servers\MemberServer;
use App\Mcp\Tools\Member\MemberGetRecordTool;
use App\Mcp\Tools\Member\MemberGetResourceMetaTool;
use App\Mcp\Tools\Member\MemberGetWriteSchemaTool;
use App\Mcp\Tools\Member\MemberListRecordsTool;
use App\Mcp\Tools\Member\MemberListResourcesTool;
use App\Mcp\Tools\Member\MemberUpdateRecordTool;
use App\Models\Event;
use App\Models\Institution;
use App\Models\PassportUser;
use App\Models\Speaker;
use App\Models\User;
use App\Support\Mcp\McpTokenManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
            ->where('data.schema.endpoint', null)
            ->where('data.schema.content_type', 'application/json')
            ->where('data.schema.media_uploads_supported', true)
            ->where('data.schema.media_upload_transport', 'json_base64_descriptor')
            ->where('data.schema.unsupported_fields', [])
            ->where('data.schema.fields', fn ($fields): bool => data_get(collect($fields)->firstWhere('name', 'logo'), 'mcp_upload.shape') === 'file_descriptor'
                && data_get(collect($fields)->firstWhere('name', 'gallery'), 'mcp_upload.shape') === 'array<file_descriptor>')
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

    $sessionId = $initialize->headers->get('MCP-Session-Id');

    expect($sessionId)->not->toBeNull();

    $listTools = $this->withHeaders([
        'MCP-Session-Id' => (string) $sessionId,
    ])->postJson('/mcp/member', [
        'jsonrpc' => '2.0',
        'id' => 'list-tools-member-mcp-passport',
        'method' => 'tools/list',
        'params' => [],
    ])->assertOk();

    $tools = collect($listTools->json('result.tools'))->keyBy('name');

    expect($tools->keys()->all())->toContain(
        'member-list-resources',
        'member-get-resource-meta',
        'member-list-records',
        'member-get-record',
        'member-get-write-schema',
        'member-update-record',
    );

    expect($tools->get('member-list-resources')['annotations'] ?? [])->toMatchArray([
        'readOnlyHint' => true,
        'idempotentHint' => true,
    ]);

    expect($tools->get('member-get-write-schema')['annotations'] ?? [])->toMatchArray([
        'readOnlyHint' => true,
        'idempotentHint' => true,
    ]);

    expect($tools->get('member-update-record')['annotations'] ?? [])->toMatchArray([
        'readOnlyHint' => false,
        'idempotentHint' => false,
        'destructiveHint' => false,
        'openWorldHint' => false,
    ]);
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
        'params' => [],
    ])->assertOk();

    $toolNames = collect($listTools->json('result.tools'))->pluck('name')->all();

    expect($toolNames)->toContain(
        'member-list-resources',
        'member-get-resource-meta',
        'member-list-records',
        'member-get-record',
        'member-get-write-schema',
        'member-update-record',
    );
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
