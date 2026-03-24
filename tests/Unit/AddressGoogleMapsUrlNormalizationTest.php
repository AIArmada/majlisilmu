<?php

use App\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('preserves long google place urls and still extracts place coordinates instead of viewport coordinates', function () {
    $longGoogleUrl = 'https://www.google.com/maps/place/Surau+An-Nur+Taman+Cahaya+Alam+Seksyen+U12/@3.1066434,101.4172012,13z/data=!4m7!3m6!1s0x31cc524f06135979:0x8bafc3c2708d1e3b!8m2!3d3.0878096!4d101.4884755!15sCgxzdXJhdSBhbiBudXJaDiIMc3VyYXUgYW4gbnVykgEGbW9zcXVlmgEkQ2hkRFNVaE5NRzluUzBWSlEwRm5TVU5sWHpkMVRXcG5SUkFC4AEA-gEECAAQGw!16s%2Fg%2F11b6c615pc?entry=tts&g_ep=EgoyMDI2MDIxMS4wIPu8ASoASAFQAw%3D%3D&skid=9d511055-13ac-4d23-872e-c298e94c10cd';

    $address = Address::query()->create([
        'addressable_type' => 'institution',
        'addressable_id' => (string) Str::uuid(),
        'google_maps_url' => $longGoogleUrl,
    ]);

    $freshAddress = $address->fresh();

    expect($freshAddress->google_maps_url)
        ->toBe($longGoogleUrl)
        ->and($freshAddress->lat)->toBe(3.0878096)
        ->and($freshAddress->lng)->toBe(101.4884755);
});

it('preserves resolved google place urls that are longer than 255 characters', function () {
    $resolvedPlaceUrl = 'https://www.google.com.my/maps/place/Sultan+Salahuddin+Abdul+Aziz+Shah+Masjid/@3.07853,101.520698,17z/data=!3m1!4b1!4m6!3m5!1s0x31cc527ec7366823:0xd59cd5f00940bc42!8m2!3d3.07853!4d101.520698!16zL20vMGJ0ems3?entry=ttu&g_ep=EgoyMDI2MDMyMi4wIKXMDSoASAFQAw%3D%3D';

    $address = Address::query()->create([
        'addressable_type' => 'institution',
        'addressable_id' => (string) Str::uuid(),
        'google_maps_url' => $resolvedPlaceUrl,
    ]);

    $freshAddress = $address->fresh();

    expect($freshAddress->google_maps_url)
        ->toBe($resolvedPlaceUrl)
        ->and($freshAddress->lat)->toBe(3.07853)
        ->and($freshAddress->lng)->toBe(101.520698);
});

it('preserves google maps place urls longer than 500 characters when the column is text', function () {
    $baseUrl = 'https://www.google.com/maps/place/Sultan+Salahuddin+Abdul+Aziz+Shah+Masjid/@3.07853,101.5158324,17z/data=!3m1!4b1!4m6!3m5!1s0x31cc527ec7366823:0xd59cd5f00940bc42!8m2!3d3.07853!4d101.520698!16zL20vMGJ0ems3?entry=tts';
    $longQuery = '&debug='.str_repeat('a', 320);
    $veryLongPlaceUrl = $baseUrl.$longQuery;

    expect(strlen($veryLongPlaceUrl))->toBeGreaterThan(500);

    $address = Address::query()->create([
        'addressable_type' => 'institution',
        'addressable_id' => (string) Str::uuid(),
        'google_maps_url' => $veryLongPlaceUrl,
    ]);

    $freshAddress = $address->fresh();

    expect($freshAddress->google_maps_url)
        ->toBe($veryLongPlaceUrl)
        ->and($freshAddress->lat)->toBe(3.07853)
        ->and($freshAddress->lng)->toBe(101.520698);
});

it('unwraps google consent redirect urls before saving the canonical google maps place url', function () {
    $consentWrappedUrl = 'https://consent.google.com/ml?continue=https://www.google.com/maps/place/Sultan%2BSalahuddin%2BAbdul%2BAziz%2BShah%2BMasjid/@3.07853,101.5158324,17z/data%3D!3m1!4b1!4m6!3m5!1s0x31cc527ec7366823:0xd59cd5f00940bc42!8m2!3d3.07853!4d101.520698!16zL20vMGJ0ems3?entry%3Dtts%26g_ep%3DEgoyMDI2MDMyMi4wIPu8ASoASAFQAw%253D%253D%26skid%3D5d93d3b6-3fef-4b16-ac2d-00f11b5119af&gl=DE&m=0&pc=m&uxe=eomtm&cm=2&hl=de&src=1';
    $expectedResolvedUrl = 'https://www.google.com/maps/place/Sultan+Salahuddin+Abdul+Aziz+Shah+Masjid/@3.07853,101.5158324,17z/data=!3m1!4b1!4m6!3m5!1s0x31cc527ec7366823:0xd59cd5f00940bc42!8m2!3d3.07853!4d101.520698!16zL20vMGJ0ems3?entry=tts&g_ep=EgoyMDI2MDMyMi4wIPu8ASoASAFQAw%3D%3D&skid=5d93d3b6-3fef-4b16-ac2d-00f11b5119af';

    $address = Address::query()->create([
        'addressable_type' => 'institution',
        'addressable_id' => (string) Str::uuid(),
        'google_maps_url' => $consentWrappedUrl,
    ]);

    $freshAddress = $address->fresh();

    expect($freshAddress->google_maps_url)
        ->toBe($expectedResolvedUrl)
        ->and($freshAddress->lat)->toBe(3.07853)
        ->and($freshAddress->lng)->toBe(101.520698);
});

it('extracts latitude and longitude from short google maps urls after redirect resolution', function () {
    $address = Address::query()->create([
        'addressable_type' => 'institution',
        'addressable_id' => (string) Str::uuid(),
        'google_maps_url' => 'https://www.google.com/maps/@3.139003,101.686855,17z',
    ]);

    $freshAddress = $address->fresh();

    expect($freshAddress->lat)->toBe(3.139003)
        ->and($freshAddress->lng)->toBe(101.686855);
});

it('extracts latitude and longitude from compact google maps search query urls', function () {
    $address = Address::query()->create([
        'addressable_type' => 'institution',
        'addressable_id' => (string) Str::uuid(),
        'google_maps_url' => 'https://www.google.com/maps/search/?api=1&query=Surau+An-Nur+Taman+Cahaya+Alam+Seksyen+U12+3.0878096%2C101.4884755',
    ]);

    $freshAddress = $address->fresh();

    expect($freshAddress->lat)->toBe(3.0878096)
        ->and($freshAddress->lng)->toBe(101.4884755);
});
