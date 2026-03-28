<?php

use App\Support\Location\PreferredCountryResolver;
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

it('prefers the saved user timezone when it resolves to a known country', function () {
    $indonesiaId = DB::table('countries')->insertGetId([
        'iso2' => 'ID',
        'name' => 'Indonesia',
        'status' => 1,
        'phone_code' => '62',
        'iso3' => 'IDN',
        'region' => 'Asia',
        'subregion' => 'South-Eastern Asia',
    ]);

    $request = Request::create('/events', 'GET');
    $request->cookies->set('user_timezone', 'Asia/Jakarta');

    $resolved = app(PreferredCountryResolver::class)->resolveId($request);

    expect($resolved)->toBe($indonesiaId);
});

it('falls back to CF-IPCountry when no user timezone country can be resolved', function () {
    $indonesiaId = DB::table('countries')->insertGetId([
        'iso2' => 'ID',
        'name' => 'Indonesia',
        'status' => 1,
        'phone_code' => '62',
        'iso3' => 'IDN',
        'region' => 'Asia',
        'subregion' => 'South-Eastern Asia',
    ]);

    $request = Request::create('/events', 'GET');
    $request->headers->set('CF-IPCountry', 'ID');

    $resolved = app(PreferredCountryResolver::class)->resolveId($request);

    expect($resolved)->toBe($indonesiaId);
});

it('falls back to Malaysia when timezone and CF-IPCountry are unavailable', function () {
    $request = Request::create('/events', 'GET');

    $resolved = app(PreferredCountryResolver::class)->resolveId($request);

    expect($resolved)->toBe(PreferredCountryResolver::MALAYSIA_ID);
});
