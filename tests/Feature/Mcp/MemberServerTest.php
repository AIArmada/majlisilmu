<?php

use App\Actions\Membership\AddMemberToSubject;
use App\Mcp\Prompts\DocumentationToolRoutingPrompt;
use App\Mcp\Resources\Docs\McpGuideResource;
use App\Mcp\Servers\MemberServer;
use App\Mcp\Tools\Member\MemberCreateGitHubIssueTool;
use App\Mcp\Tools\Member\MemberDocumentationFetchTool;
use App\Mcp\Tools\Member\MemberDocumentationSearchTool;
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
        'params' => [],
    ])->assertOk();

    $tools = collect($listTools->json('result.tools'))->keyBy('name');

    expect($tools->keys()->all())->toContain(
        'search',
        'fetch',
        'member-list-resources',
        'member-get-resource-meta',
        'member-list-records',
        'member-get-record',
        'member-get-write-schema',
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
        'params' => [],
    ])->assertOk();

    $toolNames = collect($listTools->json('result.tools'))->pluck('name')->all();

    expect($toolNames)->toContain(
        'search',
        'fetch',
        'member-list-resources',
        'member-get-resource-meta',
        'member-list-records',
        'member-get-record',
        'member-get-write-schema',
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
            '| `member-update-record` | Update a writable member record | `resource_key`, `record_key`, `payload` |',
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
