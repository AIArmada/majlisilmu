<?php

use App\Support\Location\PreferredCountryResolver;
use App\Support\Location\PublicMarketPreference;
use App\Support\Location\PublicMarketRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    if (! DB::table('countries')->where('id', 132)->exists()) {
        DB::table('countries')->insert([
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
});

it('treats unsupported and disabled saved market values as absent selections', function () {
    $request = Request::create('/events', 'GET');
    $request->cookies->set(PublicMarketPreference::COOKIE_NAME, 'indonesia');

    $preference = app(PublicMarketPreference::class);

    expect($preference->selectedKey($request))->toBeNull()
        ->and($preference->currentKey($request))->toBe('malaysia');
});

it('falls back to a valid cookie selection when the session market value is stale', function () {
    $request = Request::create('/events', 'GET');
    $request->setLaravelSession(app('session')->driver());
    $request->session()->put(PublicMarketPreference::SESSION_KEY, 'indonesia');
    $request->cookies->set(PublicMarketPreference::COOKIE_NAME, 'malaysia');

    $selectedKey = app(PublicMarketPreference::class)->selectedKey($request);

    expect($selectedKey)->toBe('malaysia');
});

it('ignores an invalid saved market so CF-IPCountry can still win later in resolution order', function () {
    config()->set('public-markets.markets.singapore.enabled', true);

    $singaporeId = DB::table('countries')->insertGetId([
        'iso2' => 'SG',
        'name' => 'Singapore',
        'status' => 1,
        'phone_code' => '65',
        'iso3' => 'SGP',
        'region' => 'Asia',
        'subregion' => 'South-Eastern Asia',
    ]);

    app()->forgetInstance(PublicMarketRegistry::class);
    app()->forgetInstance(PublicMarketPreference::class);

    $request = Request::create('/events', 'GET');
    $request->cookies->set(PublicMarketPreference::COOKIE_NAME, 'unsupported-market');
    $request->headers->set('CF-IPCountry', 'SG');

    $resolved = app(PreferredCountryResolver::class)->resolveId($request);

    expect($resolved)->toBe($singaporeId);
});
