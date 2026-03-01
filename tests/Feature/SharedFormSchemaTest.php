<?php

use App\Forms\InstitutionFormSchema;
use App\Forms\SharedFormSchema;
use App\Forms\VenueFormSchema;
use App\Models\Institution;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates an address when only a google maps url is provided', function () {
    $institution = Institution::factory()->create();
    $institution->address()->delete();

    SharedFormSchema::createAddressFromData($institution, [
        'google_maps_url' => 'https://www.google.com/maps/@3.139003,101.686855,17z',
    ]);

    $address = $institution->fresh()->addressModel;

    expect($address)->not->toBeNull();
    expect($address?->google_maps_url)->toContain('google.com/maps');
    expect(abs(((float) $address?->lat) - 3.139003))->toBeLessThan(0.000001);
    expect(abs(((float) $address?->lng) - 101.686855))->toBeLessThan(0.000001);
});

it('requires google maps url in institution and venue quick-create forms', function () {
    $institutionComponents = collect(InstitutionFormSchema::createOptionForm())
        ->keyBy(fn (mixed $component): ?string => method_exists($component, 'getName') ? $component->getName() : null);

    $venueComponents = collect(VenueFormSchema::createOptionForm())
        ->keyBy(fn (mixed $component): ?string => method_exists($component, 'getName') ? $component->getName() : null);

    $institutionGoogleMaps = $institutionComponents->get('google_maps_url');
    $venueGoogleMaps = $venueComponents->get('google_maps_url');

    expect($institutionGoogleMaps)->not->toBeNull();
    expect($venueGoogleMaps)->not->toBeNull();
    expect(method_exists($institutionGoogleMaps, 'isRequired'))->toBeTrue();
    expect(method_exists($venueGoogleMaps, 'isRequired'))->toBeTrue();
    expect($institutionGoogleMaps?->isRequired())->toBeTrue();
    expect($venueGoogleMaps?->isRequired())->toBeTrue();
});
