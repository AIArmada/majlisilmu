<?php

use App\Actions\Membership\AddMemberToSubject;
use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\RegistrationMode;
use App\Mcp\Servers\AdminServer;
use App\Mcp\Tools\Admin\AdminCreateRecordTool;
use App\Mcp\Tools\Admin\AdminGetRecordTool;
use App\Mcp\Tools\Admin\AdminGetResourceMetaTool;
use App\Mcp\Tools\Admin\AdminGetWriteSchemaTool;
use App\Mcp\Tools\Admin\AdminListRecordsTool;
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
use Illuminate\Support\Facades\DB;
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
                'address' => [],
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
                'address' => [],
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

    $toolNames = collect($listTools->json('result.tools'))->pluck('name')->all();

    expect($toolNames)->toContain('admin-list-resources', 'admin-get-record');
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
