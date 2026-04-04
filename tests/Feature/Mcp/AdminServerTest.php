<?php

use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\AdminCreateRecordTool;
use App\Mcp\Tools\Admin\AdminGetRecordTool;
use App\Mcp\Tools\Admin\AdminGetResourceMetaTool;
use App\Mcp\Tools\Admin\AdminGetWriteSchemaTool;
use App\Mcp\Tools\Admin\AdminListRecordsTool;
use App\Mcp\Tools\Admin\AdminListResourcesTool;
use App\Mcp\Tools\Admin\AdminUpdateRecordTool;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

it('lists accessible admin resources for admin users through the MCP server', function () {
    $admin = adminMcpUser('super_admin');

    AdminServer::actingAs($admin)
        ->tool(AdminListResourcesTool::class)
        ->assertOk()
        ->assertSee(['speakers', 'events', 'institutions'])
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
            ->has('data.resources', 2)
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
            ->where('data.record.id', $speaker->getKey())
            ->where('data.record.attributes.name', 'Admin MCP Speaker')
            ->etc());
});

it('returns write schema for supported resources and rejects unsupported resources', function () {
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
            ->etc());

    AdminServer::actingAs($admin)
        ->tool(AdminGetWriteSchemaTool::class, [
            'resource_key' => 'events',
            'operation' => 'create',
        ])
        ->assertHasErrors(['Resource not found.']);
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
                'address' => [
                    'country_id' => 132,
                ],
            ],
        ])
        ->assertOk();

    $speaker = Speaker::query()->where('name', 'Admin MCP Created Speaker')->firstOrFail();
    $speakerId = (string) $speaker->getKey();

    expect($speaker->name)->toBe('Admin MCP Created Speaker')
        ->and($speaker->status)->toBe('verified')
        ->and($speaker->allow_public_event_submission)->toBeTrue();

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
});

it('rejects media fields through MCP write tools', function () {
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
        ->assertHasErrors(['Media uploads are not supported through MCP v1.']);
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

it('initializes and lists admin MCP tools over the HTTP endpoint', function () {
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
        'admin-list-resources',
        'admin-get-resource-meta',
        'admin-list-records',
        'admin-get-record',
        'admin-get-write-schema',
        'admin-create-record',
        'admin-update-record',
    );
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
