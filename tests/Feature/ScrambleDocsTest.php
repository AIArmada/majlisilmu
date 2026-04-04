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
