<?php

declare(strict_types=1);

use App\Support\ApiDocumentation\ApiDocumentationUrlResolver;
use Illuminate\Http\Request;

it('uses the configured api domain for docs and base urls', function () {
    config()->set('scramble.api_domain', 'api.majlisilmu.my');
    config()->set('scramble.api_path', 'api/v1');
    config()->set('app.url', 'https://admin.majlisilmu.my');

    $resolver = app(ApiDocumentationUrlResolver::class);

    expect($resolver->apiDomain())->toBe('api.majlisilmu.my')
        ->and($resolver->docsUrl())->toBe('https://api.majlisilmu.my/docs')
        ->and($resolver->apiBaseUrl())->toBe('https://api.majlisilmu.my/api/v1');
});

it('falls back to the current app origin when running on localhost without a configured api domain', function () {
    config()->set('scramble.api_domain', null);
    config()->set('scramble.api_path', 'api/v1');
    config()->set('app.url', 'http://localhost');
    app()->instance('request', Request::create('http://localhost/docs', 'GET'));

    $resolver = app(ApiDocumentationUrlResolver::class);

    expect($resolver->apiDomain())->toBeNull()
        ->and($resolver->docsUrl())->toBe('http://localhost/docs')
        ->and($resolver->apiBaseUrl())->toBe('http://localhost/api/v1');
});
