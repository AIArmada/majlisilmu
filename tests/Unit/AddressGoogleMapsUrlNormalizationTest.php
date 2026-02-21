<?php

use App\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('prefers place coordinates over viewport coordinates when compacting long google maps urls', function () {
    $longGoogleUrl = 'https://www.google.com/maps/place/Surau+An-Nur+Taman+Cahaya+Alam+Seksyen+U12/@3.1066434,101.4172012,13z/data=!4m7!3m6!1s0x31cc524f06135979:0x8bafc3c2708d1e3b!8m2!3d3.0878096!4d101.4884755!15sCgxzdXJhdSBhbiBudXJaDiIMc3VyYXUgYW4gbnVykgEGbW9zcXVlmgEkQ2hkRFNVaE5NRzluUzBWSlEwRm5TVU5sWHpkMVRXcG5SUkFC4AEA-gEECAAQGw!16s%2Fg%2F11b6c615pc?entry=tts&g_ep=EgoyMDI2MDIxMS4wIPu8ASoASAFQAw%3D%3D&skid=9d511055-13ac-4d23-872e-c298e94c10cd';

    $address = Address::query()->create([
        'addressable_type' => 'institution',
        'addressable_id' => (string) Str::uuid(),
        'google_maps_url' => $longGoogleUrl,
    ]);

    $freshAddress = $address->fresh();

    expect($freshAddress->google_maps_url)
        ->toBe('https://www.google.com/maps/search/?api=1&query=Surau+An-Nur+Taman+Cahaya+Alam+Seksyen+U12+3.0878096%2C101.4884755')
        ->and($freshAddress->lat)->toBe(3.0878096)
        ->and($freshAddress->lng)->toBe(101.4884755);
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
