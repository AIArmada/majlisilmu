<?php

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
        ->toContain('Account Settings > API Access')
        ->and($paths['/auth/login']['post']['tags'] ?? null)->toContain('Authentication')
        ->and($paths['/auth/register']['post']['tags'] ?? null)->toContain('Authentication')
        ->and($paths['/auth/logout']['post']['tags'] ?? null)->toContain('Authentication');
});

it('documents public and admin mutation capability boundaries in the api overview', function () {
    $response = $this->getJson('https://api.majlisilmu.test/docs.json', [
        'Host' => 'api.majlisilmu.test',
    ])->assertOk();

    expect((string) $response->json('info.description'))
        ->toContain('Public create flows currently exist for events, institutions, and speakers.')
        ->toContain('Public update flows currently exist for events, institutions, speakers, and references')
        ->toContain('does not currently include creating references, venues, or series')
        ->toContain('GET /forms/*')
        ->toContain('GET /admin/manifest')
        ->toContain('GET /admin/{resourceKey}/schema?operation=create')
        ->toContain('GET /admin/catalogs/*')
        ->toContain('Current admin write support includes events, institutions, speakers, references, venues, and subdistricts.');
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
        ->and($paths['/contributions/institutions']['post']['summary'] ?? null)->toBe('Create an institution contribution')
        ->and($paths['/contributions/speakers']['post']['summary'] ?? null)->toBe('Create a speaker contribution')
        ->and($paths['/forms/contributions/{subjectType}/{subject}/suggest']['get']['summary'] ?? null)->toBe('Get editable contribution context')
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
