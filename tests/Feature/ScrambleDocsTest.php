<?php

use Symfony\Component\Process\Process;

it('serves scramble docs only on the api host', function () {
    $this->get('https://api.majlisilmu.test/docs', [
        'Host' => 'api.majlisilmu.test',
    ])->assertOk();

    $this->get('https://majlisilmu.test/docs', [
        'Host' => 'majlisilmu.test',
    ])->assertNotFound();
});

it('serves scramble docs publicly on the api host outside local environments', function () {
    $originalEnvironment = app()->environment();
    app()['env'] = 'production';

    try {
        $this->get('https://api.majlisilmu.test/docs', [
            'Host' => 'api.majlisilmu.test',
        ])->assertOk();

        $this->getJson('https://api.majlisilmu.test/docs.json', [
            'Host' => 'api.majlisilmu.test',
        ])->assertOk();
    } finally {
        app()['env'] = $originalEnvironment;
    }
});

it('publishes openapi json on the api host with the api v1 server url', function () {
    $this->getJson('https://api.majlisilmu.test/docs.json', [
        'Host' => 'api.majlisilmu.test',
    ])
        ->assertOk()
        ->assertJsonPath('openapi', '3.1.0')
        ->assertJsonPath('servers.0.url', 'https://api.majlisilmu.test/api/v1');
});

it('groups speaker endpoints under a dedicated speaker tag in scramble docs', function () {
    $response = $this->getJson('https://api.majlisilmu.test/docs.json', [
        'Host' => 'api.majlisilmu.test',
    ])->assertOk();

    $paths = $response->json('paths');

    expect($paths['/speakers']['get']['tags'] ?? null)->toContain('Speaker')
        ->and($paths['/speakers/{speakerKey}']['get']['tags'] ?? null)->toContain('Speaker');
});

it('groups other public directory endpoints under dedicated entity tags in scramble docs', function () {
    $response = $this->getJson('https://api.majlisilmu.test/docs.json', [
        'Host' => 'api.majlisilmu.test',
    ])->assertOk();

    $paths = $response->json('paths');

    expect($paths['/institutions']['get']['tags'] ?? null)->toContain('Institution')
        ->and($paths['/institutions/{institutionKey}']['get']['tags'] ?? null)->toContain('Institution')
        ->and($paths['/venues/{venueKey}']['get']['tags'] ?? null)->toContain('Venue')
        ->and($paths['/references/{referenceKey}']['get']['tags'] ?? null)->toContain('Reference')
        ->and($paths['/series/{series}']['get']['tags'] ?? null)->toContain('Series');
});

it('publishes named speaker and institution schemas for the public directory endpoints', function () {
    $response = $this->getJson('https://api.majlisilmu.test/docs.json', [
        'Host' => 'api.majlisilmu.test',
    ])->assertOk();

    $paths = $response->json('paths');
    $schemas = $response->json('components.schemas');

    expect($schemas)->toHaveKeys([
        'Speaker',
        'SpeakerListItem',
        'Institution',
        'InstitutionListItem',
    ])
        ->and(data_get($paths, '/speakers.get.responses.200.content.application/json.schema'))->not->toBeNull()
        ->and(data_get($paths, '/speakers/{speakerKey}.get.responses.200.content.application/json.schema'))->not->toBeNull()
        ->and(data_get($paths, '/institutions.get.responses.200.content.application/json.schema'))->not->toBeNull()
        ->and(data_get($paths, '/institutions/{institutionKey}.get.responses.200.content.application/json.schema'))->not->toBeNull();
});

it('exposes the admin api foundation in scramble docs under dedicated admin tags', function () {
    $response = $this->getJson('https://api.majlisilmu.test/docs.json', [
        'Host' => 'api.majlisilmu.test',
    ])->assertOk();

    $paths = $response->json('paths');

    expect($paths['/admin/manifest']['get']['tags'] ?? null)->toContain('Admin Manifest')
        ->and($paths['/admin/catalogs/countries']['get']['tags'] ?? null)->toContain('Admin Catalog')
        ->and($paths['/admin/catalogs/states']['get']['tags'] ?? null)->toContain('Admin Catalog')
        ->and($paths['/admin/catalogs/districts']['get']['tags'] ?? null)->toContain('Admin Catalog')
        ->and($paths['/admin/catalogs/subdistricts']['get']['tags'] ?? null)->toContain('Admin Catalog')
        ->and($paths['/admin/{resourceKey}']['get']['tags'] ?? null)->toContain('Admin Resource')
        ->and($paths['/admin/{resourceKey}']['post']['tags'] ?? null)->toContain('Admin Resource')
        ->and($paths['/admin/{resourceKey}/meta']['get']['tags'] ?? null)->toContain('Admin Resource')
        ->and($paths['/admin/{resourceKey}/schema']['get']['tags'] ?? null)->toContain('Admin Resource')
        ->and($paths['/admin/{resourceKey}/{recordKey}']['get']['tags'] ?? null)->toContain('Admin Resource')
        ->and($paths['/admin/{resourceKey}/{recordKey}']['put']['tags'] ?? null)->toContain('Admin Resource');
});

it('documents how clients obtain bearer tokens for authenticated api access', function () {
    $response = $this->getJson('https://api.majlisilmu.test/docs.json', [
        'Host' => 'api.majlisilmu.test',
    ])->assertOk();

    $paths = $response->json('paths');

    expect((string) $response->json('info.description'))
        ->toContain('POST /auth/login')
        ->toContain('Authorization: Bearer {token}')
        ->toContain('Admin > Authz > User > API Access')
        ->not->toContain('Account Settings > API Access')
        ->and($paths['/auth/login']['post']['tags'] ?? null)->toContain('Authentication')
        ->and($paths['/auth/register']['post']['tags'] ?? null)->toContain('Authentication')
        ->and($paths['/auth/logout']['post']['tags'] ?? null)->toContain('Authentication');
});

it('documents public and admin mutation capability boundaries in the api overview', function () {
    $response = $this->getJson('https://api.majlisilmu.test/docs.json', [
        'Host' => 'api.majlisilmu.test',
    ])->assertOk();

    expect((string) $response->json('info.description'))
        ->toContain("Canonical API documentation for Majlis Ilmu client and platform integrations.\n\nGet an access token")
        ->toContain("\n\n---\n\nAI QUICKSTART:\n")
        ->toContain('https://api.majlisilmu.test/docs')
        ->toContain('https://api.majlisilmu.test/docs.json')
        ->toContain("\n\nROUTING SURFACES:\n")
        ->toContain("\n\nTIMEZONE:\n")
        ->toContain('Public create flows currently exist for events, institutions, and speakers.')
        ->toContain('must include an explicit country selection')
        ->toContain('Public update flows currently exist for events, institutions, speakers, and references')
        ->toContain('does not currently include creating references, venues, or series')
        ->toContain('GET /forms/*')
        ->toContain('GET /admin/manifest')
        ->toContain('GET /admin/{resourceKey}/schema?operation=create')
        ->toContain('GET /admin/catalogs/*')
        ->toContain('Current admin write support includes events, institutions, speakers, references, venues, and subdistricts.');
});

it('documents utc transport fields and request-timezone helper behavior clearly', function () {
    $response = $this->getJson('https://api.majlisilmu.test/docs.json', [
        'Host' => 'api.majlisilmu.test',
    ])->assertOk();

    expect((string) $response->json('info.description'))
        ->toContain('Raw API timestamp fields are stored and returned in UTC')
        ->toContain('Viewer-facing helper fields such as event timing_display and end_time_display are localized only when the request provides timezone context')
        ->toContain('Without timezone context, bare API requests fall back to UTC.')
        ->toContain('Date-only event filters such as filter[starts_after] and filter[starts_before] are interpreted in the resolved request timezone')
        ->toContain('send X-Timezone: Asia/Kuala_Lumpur and date-only values such as filter[starts_after]=2026-04-12&filter[starts_before]=2026-04-12')
        ->toContain('If you omit timezone context, the same filter values are interpreted in UTC instead.')
        ->not->toContain('The server timezone is UTC; the default display timezone is Asia/Kuala_Lumpur (MYT, UTC+8).')
        ->not->toContain('All date/time filter values must be expressed in UTC.');
});

it('does not leak local-only docs urls into the published api description', function () {
    $response = $this->getJson('https://api.majlisilmu.test/docs.json', [
        'Host' => 'api.majlisilmu.test',
    ])->assertOk();

    expect((string) $response->json('info.description'))
        ->not->toContain('https://majlisilmu.test/docs')
        ->not->toContain('https://majlisilmu.test/docs.json')
        ->toContain('published at `/docs.json`');
});

it('adds workflow summaries to public contract and mutation endpoints', function () {
    $response = $this->getJson('https://api.majlisilmu.test/docs.json', [
        'Host' => 'api.majlisilmu.test',
    ])->assertOk();

    $paths = $response->json('paths');

    expect($paths['/manifest']['get']['summary'] ?? null)->toBe('Discover public client flows')
        ->and($paths['/forms/submit-event']['get']['summary'] ?? null)->toBe('Get submit-event field contract')
        ->and($paths['/submit-event']['post']['summary'] ?? null)->toBe('Submit a public event')
        ->and($paths['/submit-event']['post']['description'] ?? null)->toContain('This route is create-only')
        ->and($paths['/submit-event']['post']['description'] ?? null)->toContain('submission_country_code')
        ->and($paths['/contributions/institutions']['post']['summary'] ?? null)->toBe('Create an institution contribution')
        ->and($paths['/contributions/institutions']['post']['description'] ?? null)->toContain('address.country_code')
        ->and($paths['/contributions/speakers']['post']['summary'] ?? null)->toBe('Create a speaker contribution')
        ->and($paths['/contributions/speakers']['post']['description'] ?? null)->toContain('address.country_code')
        ->and($paths['/forms/contributions/{subjectType}/{subject}/suggest']['get']['summary'] ?? null)->toBe('Get editable contribution context')
        ->and($paths['/forms/contributions/{subjectType}/{subject}/suggest']['get']['description'] ?? null)->toContain('event `poster`/`gallery`')
        ->and($paths['/contributions/{subjectType}/{subject}/suggest']['post']['summary'] ?? null)->toBe('Submit a contribution update')
        ->and($paths['/contributions/{subjectType}/{subject}/suggest']['post']['description'] ?? null)->toContain('direct_edit')
        ->and($paths['/contributions/{subjectType}/{subject}/suggest']['post']['description'] ?? null)->toContain('review');
});

it('documents admin schema-driven writes and dynamic payload discovery', function () {
    $response = $this->getJson('https://api.majlisilmu.test/docs.json', [
        'Host' => 'api.majlisilmu.test',
    ])->assertOk();

    $paths = $response->json('paths');

    expect($paths['/admin/manifest']['get']['summary'] ?? null)->toBe('List admin resources and write support')
        ->and($paths['/admin/{resourceKey}/schema']['get']['summary'] ?? null)->toBe('Get admin write schema')
        ->and($paths['/admin/{resourceKey}/schema']['get']['description'] ?? null)->toContain('mutation payloads are resource-specific')
        ->and($paths['/admin/{resourceKey}']['post']['summary'] ?? null)->toBe('Create an admin resource record')
        ->and($paths['/admin/{resourceKey}']['post']['description'] ?? null)->toContain('fetch `GET /admin/{resourceKey}/schema?operation=create` first')
        ->and($paths['/admin/{resourceKey}/{recordKey}']['put']['summary'] ?? null)->toBe('Update an admin resource record')
        ->and($paths['/admin/{resourceKey}/{recordKey}']['put']['description'] ?? null)->toContain('schema?operation=update&recordKey={recordKey}');
});

it('includes the mobile api reference in the docs description for ai and mobile consumers', function () {
    $response = $this->getJson('https://api.majlisilmu.test/docs.json', [
        'Host' => 'api.majlisilmu.test',
    ])->assertOk();

    expect((string) $response->json('info.description'))
        ->toContain('Majlisilmu Mobile API Reference')
        ->toContain('If you are building an AI client, use this read order:')
        ->toContain('Android, iOS application developers, and AI agents');
});

it('publishes explicit auth response schemas for ai clients', function () {
    $script = <<<'PHP'
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
ini_set('display_errors', '0');
putenv('APP_ENV=testing');
$_ENV['APP_ENV'] = 'testing';
$_SERVER['APP_ENV'] = 'testing';

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::create(
    'https://api.majlisilmu.test/docs.json',
    'GET',
    [],
    [],
    [],
    [
        'HTTP_HOST' => 'api.majlisilmu.test',
        'HTTP_ACCEPT' => 'application/json',
        'HTTPS' => 'on',
    ],
);

$response = $kernel->handle($request);

if ($response->getStatusCode() !== 200) {
    fwrite(STDERR, 'Unexpected status: '.$response->getStatusCode());
    exit(1);
}

echo $response->getContent();

$kernel->terminate($request, $response);
PHP;

    $process = new Process(
        [PHP_BINARY, '-r', $script],
        base_path(),
        [
            'APP_ENV' => 'testing',
            'LARAVEL_PARALLEL_TESTING' => false,
            'PARATEST' => false,
            'TEST_TOKEN' => false,
            'UNIQUE_TEST_TOKEN' => false,
        ],
    );
    $process->mustRun();

    $spec = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    $paths = $spec['paths'] ?? [];

    expect($paths['/auth/register']['post']['requestBody']['content']['application/json']['schema'] ?? null)->not->toBeNull()
        ->and($paths['/auth/register']['post']['summary'] ?? null)->toBe('Register and issue a bearer token')
        ->and($paths['/auth/register']['post']['description'] ?? null)->toContain('Supply either `email` or `phone`')
        ->and($paths['/auth/register']['post']['responses']['201']['description'] ?? null)->toBe('Successful registration response.')
        ->and($paths['/auth/register']['post']['responses']['201']['content']['application/json']['schema'] ?? null)->not->toBeNull()
        ->and($paths['/auth/login']['post']['summary'] ?? null)->toBe('Log in and issue a bearer token')
        ->and($paths['/auth/login']['post']['description'] ?? null)->toContain('Admin > Authz > User > API Access')
        ->and($paths['/auth/login']['post']['responses']['200']['description'] ?? null)->toBe('Successful login response.')
        ->and($paths['/auth/login']['post']['responses']['200']['content']['application/json']['schema'] ?? null)->not->toBeNull()
        ->and($paths['/auth/logout']['post']['summary'] ?? null)->toBe('Revoke the current bearer token')
        ->and($paths['/auth/logout']['post']['responses']['200']['description'] ?? null)->toBe('Successful logout response.')
        ->and($paths['/auth/logout']['post']['responses']['200']['content']['application/json']['schema'] ?? null)->not->toBeNull();
});

it('publishes sanctum bearer security metadata for authenticated api operations', function () {
    $response = $this->getJson('https://api.majlisilmu.test/docs.json', [
        'Host' => 'api.majlisilmu.test',
    ])->assertOk();

    $securitySchemes = $response->json('components.securitySchemes');
    $paths = $response->json('paths');

    expect($securitySchemes)->toHaveKey('sanctumBearer')
        ->and($securitySchemes['sanctumBearer']['type'] ?? null)->toBe('http')
        ->and($securitySchemes['sanctumBearer']['scheme'] ?? null)->toBe('bearer')
        ->and((string) ($securitySchemes['sanctumBearer']['description'] ?? ''))->toContain('Authorization: Bearer {token}')
        ->and($paths['/auth/logout']['post']['security'][0]['sanctumBearer'] ?? null)->toBe([])
        ->and($paths['/user']['get']['security'][0]['sanctumBearer'] ?? null)->toBe([])
        ->and($paths['/forms/account-settings']['get']['security'][0]['sanctumBearer'] ?? null)->toBe([])
        ->and($paths['/events/{event}']['get']['security'] ?? null)->toBeNull()
        ->and($paths['/events/{event}/registrations']['post']['security'] ?? null)->toBeNull()
        ->and($paths['/events/{event}/registration-status']['get']['security'][0]['sanctumBearer'] ?? null)->toBe([])
        ->and($paths['/events/{event}/check-ins']['post']['security'][0]['sanctumBearer'] ?? null)->toBe([])
        ->and($paths['/event-saves']['get']['security'][0]['sanctumBearer'] ?? null)->toBe([])
        ->and($paths['/notification-settings/catalog']['get']['security'][0]['sanctumBearer'] ?? null)->toBe([])
        ->and($paths['/notifications']['get']['security'][0]['sanctumBearer'] ?? null)->toBe([])
        ->and($paths['/notifications/read-all']['post']['security'][0]['sanctumBearer'] ?? null)->toBe([])
        ->and($paths['/notification-destinations/push']['post']['security'][0]['sanctumBearer'] ?? null)->toBe([])
        ->and($paths['/auth/login']['post']['security'] ?? null)->toBeNull();
});

it('publishes explicit schemas for search manifest and public form contracts', function () {
    $response = $this->getJson('https://api.majlisilmu.test/docs.json', [
        'Host' => 'api.majlisilmu.test',
    ])->assertOk();

    $paths = $response->json('paths');
    $schemas = $response->json('components.schemas');

    expect($schemas)->toHaveKeys([
        'SearchIndexResponse',
        'PublicManifestResponse',
        'PublicFormFieldContract',
        'PublicConditionalRule',
        'SubmitEventFormResponse',
        'InstitutionContributionFormResponse',
        'SpeakerContributionFormResponse',
        'ReportFormResponse',
        'AccountSettingsFormResponse',
        'AdvancedEventFormResponse',
        'InstitutionWorkspaceFormResponse',
        'MembershipClaimFormResponse',
        'ContributionSuggestContextResponse',
    ])
        ->and(data_get($paths, '/search.get.responses.200.content.application/json.schema.$ref'))->toBe('#/components/schemas/SearchIndexResponse')
        ->and(data_get($paths, '/manifest.get.responses.200.content.application/json.schema.$ref'))->toBe('#/components/schemas/PublicManifestResponse')
        ->and(data_get($paths, '/forms/submit-event.get.responses.200.content.application/json.schema.$ref'))->toBe('#/components/schemas/SubmitEventFormResponse')
        ->and(data_get($paths, '/forms/contributions/institutions.get.responses.200.content.application/json.schema.$ref'))->toBe('#/components/schemas/InstitutionContributionFormResponse')
        ->and(data_get($paths, '/forms/contributions/speakers.get.responses.200.content.application/json.schema.$ref'))->toBe('#/components/schemas/SpeakerContributionFormResponse')
        ->and(data_get($paths, '/forms/report.get.responses.200.content.application/json.schema.$ref'))->toBe('#/components/schemas/ReportFormResponse')
        ->and(data_get($paths, '/forms/account-settings.get.responses.200.content.application/json.schema.$ref'))->toBe('#/components/schemas/AccountSettingsFormResponse')
        ->and(data_get($paths, '/forms/advanced-events.get.responses.200.content.application/json.schema.$ref'))->toBe('#/components/schemas/AdvancedEventFormResponse')
        ->and(data_get($paths, '/forms/institution-workspace.get.responses.200.content.application/json.schema.$ref'))->toBe('#/components/schemas/InstitutionWorkspaceFormResponse')
        ->and(data_get($paths, '/forms/membership-claims/{subjectType}.get.responses.200.content.application/json.schema.$ref'))->toBe('#/components/schemas/MembershipClaimFormResponse')
        ->and(data_get($paths, '/forms/contributions/{subjectType}/{subject}/suggest.get.responses.200.content.application/json.schema.$ref'))->toBe('#/components/schemas/ContributionSuggestContextResponse')
        ->and(data_get($schemas, 'SearchIndexResponse.properties.data.properties.speakers.properties.items.type'))->toBe('array')
        ->and(data_get($schemas, 'PublicFormFieldContract.properties.name.type'))->toBe('string')
        ->and(data_get($schemas, 'PublicConditionalRule.properties.field.type'))->toBe('string');
});

it('adds summaries and descriptions to catalog and authenticated workflow endpoints', function () {
    $response = $this->getJson('https://api.majlisilmu.test/docs.json', [
        'Host' => 'api.majlisilmu.test',
    ])->assertOk();

    $paths = $response->json('paths');
    $tags = collect($response->json('tags'))->keyBy('name');

    expect($paths['/catalogs/countries']['get']['summary'] ?? null)->toBe('List public countries catalog')
        ->and($paths['/catalogs/membership-claim-subjects/{subjectType}']['get']['summary'] ?? null)->toBe('List membership-claim subjects')
        ->and($paths['/user/going-events']['get']['summary'] ?? null)->toBe('List going events')
        ->and($paths['/events/{event}/check-ins']['post']['summary'] ?? null)->toBe('Record an event check-in')
        ->and($paths['/account-settings']['get']['summary'] ?? null)->toBe('Get account settings')
        ->and($paths['/institution-workspace']['get']['summary'] ?? null)->toBe('Get institution workspace')
        ->and($paths['/membership-claims/{subjectType}/{subject}']['post']['summary'] ?? null)->toBe('Submit a membership claim')
        ->and($paths['/reports']['post']['summary'] ?? null)->toBe('Submit a report')
        ->and($paths['/events/{event}/registrations/export']['get']['summary'] ?? null)->toBe('Export registrations as CSV')
        ->and($paths['/institution-workspace/{institutionId}/members/{memberId}']['delete']['summary'] ?? null)->toBe('Remove an institution member')
        ->and($paths['/follows/{type}/{subject}']['get']['description'] ?? null)->toContain('current authenticated user is following')
        ->and($paths['/follows/{type}/{subject}']['post']['description'] ?? null)->toContain('Creates a follow relationship')
        ->and($paths['/follows/{type}/{subject}']['delete']['description'] ?? null)->toContain('Removes the follow relationship')
        ->and($paths['/events/{event}']['get']['summary'] ?? null)->toBe('Get a public event')
        ->and($paths['/events/{event}']['get']['description'] ?? null)->toContain('public event detail payload')
        ->and($tags->get('Catalog')['description'] ?? null)->toBe('Public lookup catalogs for geography, tags, languages, references, venues, and write-flow selectors.')
        ->and($tags->get('InstitutionWorkspace')['description'] ?? null)->toContain('member management')
        ->and($tags->get('RegistrationExport')['description'] ?? null)->toContain('CSV export');
});

it('publishes high-value request body examples for agentic write endpoints', function () {
    $response = $this->getJson('https://api.majlisilmu.test/docs.json', [
        'Host' => 'api.majlisilmu.test',
    ])->assertOk();

    $paths = $response->json('paths');

    expect(data_get($paths, '/auth/login.post.requestBody.content.application/json.example.login'))->toBe('superadmin@majlisilmu.my')
        ->and(data_get($paths, '/auth/register.post.requestBody.content.application/json.example.device_name'))->toBe('OpenClaw iPhone')
        ->and(data_get($paths, '/account-settings.put.requestBody.content.application/json.example.timezone'))->toBe('Asia/Kuala_Lumpur')
        ->and(data_get($paths, '/notification-settings.put.requestBody.content.application/json.example.settings.preferred_channels.0'))->toBe('in_app')
        ->and(data_get($paths, '/events/{event}/registrations.post.requestBody.content.application/json.example.email'))->toBe('guest@example.com')
        ->and(data_get($paths, '/saved-searches.post.requestBody.content.application/json.example.notify'))->toBe('daily')
        ->and(data_get($paths, '/saved-searches/{savedSearch}.put.requestBody.content.application/json.example.notify'))->toBe('instant')
        ->and(data_get($paths, '/reports.post.requestBody.content.application/json.example.category'))->toBe('wrong_info');
});

it('publishes follow-up request examples for authenticated workflow mutations', function () {
    $response = $this->getJson('https://api.majlisilmu.test/docs.json', [
        'Host' => 'api.majlisilmu.test',
    ])->assertOk();

    $paths = $response->json('paths');

    expect(data_get($paths, '/notification-destinations/push.post.requestBody.content.application/json.example.installation_id'))->toBe('ios-installation-123')
        ->and(data_get($paths, '/notification-destinations/push/{installation}.put.requestBody.content.application/json.example.fcm_token'))->toBe('fcm-token-updated-xyz789')
        ->and(data_get($paths, '/membership-claims/{subjectType}/{subject}.post.requestBody.content.multipart/form-data.example.justification'))->toContain('mosque committee')
        ->and(data_get($paths, '/institution-workspace/{institutionId}/members.post.requestBody.content.application/json.example.email'))->toBe('member@example.com')
        ->and(data_get($paths, '/institution-workspace/{institutionId}/members/{memberId}.put.requestBody.content.application/json.example.role_id'))->toBe('institution_editor')
        ->and(data_get($paths, '/follows/{type}/{subject}.post.requestBody'))->toBeNull()
        ->and(data_get($paths, '/follows/{type}/{subject}.post.parameters.0.example'))->toBe('institution');
});
