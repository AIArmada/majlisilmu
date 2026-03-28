<?php

use App\Forms\InstitutionContributionFormSchema;
use App\Forms\InstitutionFormSchema;
use App\Forms\SharedFormSchema;
use App\Forms\SpeakerContributionFormSchema;
use App\Forms\SpeakerFormSchema;
use App\Forms\VenueFormSchema;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\Venue;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\View as SchemaView;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Nnjeim\World\Models\Language;

uses(RefreshDatabase::class);

it('creates an address when only a google maps url is provided', function () {
    $institution = Institution::factory()->create();
    $institution->address()->delete();

    SharedFormSchema::createAddressFromData($institution, [
        'google_maps_url' => 'https://www.google.com/maps/@3.139003,101.686855,17z',
    ]);

    $address = $institution->fresh()->addressModel;

    expect($address)->not->toBeNull();
    expect($address?->google_maps_url)->toBe('https://www.google.com/maps/search/?api=1&query=3.139003%2C101.686855');
    expect(abs(((float) $address?->lat) - 3.139003))->toBeLessThan(0.000001);
    expect(abs(((float) $address?->lng) - 101.686855))->toBeLessThan(0.000001);
});

it('preserves explicit google place metadata when creating an address from form data', function () {
    $institution = Institution::factory()->create();
    $institution->address()->delete();

    SharedFormSchema::createAddressFromData($institution, [
        'line1' => 'Persiaran Masjid',
        'google_maps_url' => 'https://www.google.com/maps/place/?q=place_id:place_123',
        'google_place_id' => 'place_123',
        'lat' => '3.07853',
        'lng' => '101.52073',
    ]);

    $address = $institution->fresh()->addressModel;

    expect($address)->not->toBeNull();
    expect($address?->google_maps_url)->toBe('https://www.google.com/maps/search/?api=1&query=3.07853%2C101.52073&query_place_id=place_123')
        ->and($address?->google_place_id)->toBe('place_123');
    expect(abs(((float) $address?->lat) - 3.07853))->toBeLessThan(0.000001);
    expect(abs(((float) $address?->lng) - 101.52073))->toBeLessThan(0.000001);
});

it('preserves raw google maps urls without enrichment when normalization is disabled', function () {
    $institution = Institution::factory()->create();
    $institution->address()->delete();

    Http::fake();

    SharedFormSchema::createAddressFromData($institution, [
        'google_maps_url' => 'https://maps.app.goo.gl/KWFQuuxAmSK3kRFM8',
        'google_maps_normalization_enabled' => false,
    ]);

    $address = $institution->fresh()->addressModel;

    expect($address)->not->toBeNull();
    expect($address?->google_maps_url)->toBe('https://maps.app.goo.gl/KWFQuuxAmSK3kRFM8')
        ->and($address?->google_place_id)->toBeNull()
        ->and($address?->lat)->toBeNull()
        ->and($address?->lng)->toBeNull();

    Http::assertNothingSent();
});

it('still canonicalizes google maps urls locally when remote lookup is disabled', function () {
    $institution = Institution::factory()->create();
    $institution->address()->delete();

    config()->set('services.google.place_link_resolution_enabled', true);
    config()->set('services.google.places_server_api_key', 'server-test-key');

    Http::fake([
        'https://maps.app.goo.gl/*' => Http::response('', 302, [
            'Location' => 'https://www.google.com/maps/place/Masjid+Jamik+Ungku+Ahmad,+Kampung+Separap/@1.9089362,102.865462,925m/data=!3m2!1e3!4b1!4m6!3m5!1s0x31d0539173ae7dd9:0xb4fce77c077ec5f3!8m2!3d1.9089362!4d102.865462!16s%2Fg%2F11sqw6yjrc?hl=en-US&entry=ttu',
        ]),
        'https://places.googleapis.com/v1/places:searchText' => Http::response([
            'places' => [[
                'id' => 'ChIJ2X2uc5FT0DER88V-B3zn_LQ',
                'displayName' => ['text' => 'Masjid Jamik Ungku Ahmad, Kampung Separap'],
                'location' => [
                    'latitude' => 1.9089362,
                    'longitude' => 102.865462,
                ],
            ]],
        ], 200),
    ]);

    SharedFormSchema::createAddressFromData($institution, [
        'google_maps_url' => 'https://maps.app.goo.gl/KWFQuuxAmSK3kRFM8',
        'google_maps_remote_lookup_enabled' => false,
    ]);

    $address = $institution->fresh()->addressModel;

    expect($address)->not->toBeNull();
    expect($address?->google_maps_url)->toBe('https://www.google.com/maps/search/?api=1&query=1.9089362%2C102.865462')
        ->and($address?->google_place_id)->toBeNull();
    expect(abs(((float) $address?->lat) - 1.9089362))->toBeLessThan(0.000001);
    expect(abs(((float) $address?->lng) - 102.865462))->toBeLessThan(0.000001);

    Http::assertSentCount(1);
    Http::assertSent(fn ($request) => str_starts_with($request->url(), 'https://maps.app.goo.gl/'));
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

it('hides raw google maps fields in institution and venue quick-create picker mode', function () {
    config()->set('services.google.maps_api_key', 'test-maps-key');
    config()->set('services.google.places_enabled', true);

    $flatten = function (array $components) use (&$flatten): array {
        $flattened = [];

        foreach ($components as $component) {
            $flattened[] = $component;

            $reflection = new ReflectionObject($component);

            while (! $reflection->hasProperty('childComponents') && ($parent = $reflection->getParentClass())) {
                $reflection = $parent;
            }

            if (! $reflection->hasProperty('childComponents')) {
                continue;
            }

            $childComponentsProperty = $reflection->getProperty('childComponents');
            $childComponents = $childComponentsProperty->getValue($component);

            if (! is_array($childComponents)) {
                continue;
            }

            $defaultChildComponents = $childComponents['default'] ?? null;

            if (! is_array($defaultChildComponents)) {
                continue;
            }

            array_push($flattened, ...$flatten($defaultChildComponents));
        }

        return $flattened;
    };

    $institutionComponents = collect($flatten(InstitutionFormSchema::createOptionForm(includeLocationPicker: true)));
    $venueComponents = collect($flatten(VenueFormSchema::createOptionForm(includeLocationPicker: true)));

    $institutionGoogleMaps = $institutionComponents
        ->keyBy(fn (mixed $component): ?string => method_exists($component, 'getName') ? $component->getName() : null)
        ->get('google_maps_url');
    $venueGoogleMaps = $venueComponents
        ->keyBy(fn (mixed $component): ?string => method_exists($component, 'getName') ? $component->getName() : null)
        ->get('google_maps_url');

    expect($institutionGoogleMaps)->toBeInstanceOf(Hidden::class)
        ->and($venueGoogleMaps)->toBeInstanceOf(Hidden::class);
    expect($institutionComponents->contains(fn (mixed $component): bool => $component instanceof SchemaView))->toBeTrue();
    expect($venueComponents->contains(fn (mixed $component): bool => $component instanceof SchemaView))->toBeTrue();
});

it('shows raw google maps fields in institution and venue quick-create fallback mode', function () {
    config()->set('services.google.maps_api_key', null);
    config()->set('services.google.places_enabled', false);

    $flatten = function (array $components) use (&$flatten): array {
        $flattened = [];

        foreach ($components as $component) {
            $flattened[] = $component;

            $reflection = new ReflectionObject($component);

            while (! $reflection->hasProperty('childComponents') && ($parent = $reflection->getParentClass())) {
                $reflection = $parent;
            }

            if (! $reflection->hasProperty('childComponents')) {
                continue;
            }

            $childComponentsProperty = $reflection->getProperty('childComponents');
            $childComponents = $childComponentsProperty->getValue($component);

            if (! is_array($childComponents)) {
                continue;
            }

            $defaultChildComponents = $childComponents['default'] ?? null;

            if (! is_array($defaultChildComponents)) {
                continue;
            }

            array_push($flattened, ...$flatten($defaultChildComponents));
        }

        return $flattened;
    };

    $institutionGoogleMaps = collect($flatten(InstitutionFormSchema::createOptionForm(includeLocationPicker: true)))
        ->keyBy(fn (mixed $component): ?string => method_exists($component, 'getName') ? $component->getName() : null)
        ->get('google_maps_url');
    $venueGoogleMaps = collect($flatten(VenueFormSchema::createOptionForm(includeLocationPicker: true)))
        ->keyBy(fn (mixed $component): ?string => method_exists($component, 'getName') ? $component->getName() : null)
        ->get('google_maps_url');

    expect($institutionGoogleMaps)->toBeInstanceOf(TextInput::class)
        ->and($venueGoogleMaps)->toBeInstanceOf(TextInput::class);
});

it('requires google maps url on the dedicated institution contribution create form', function () {
    $flatten = function (array $components) use (&$flatten): array {
        $flattened = [];

        foreach ($components as $component) {
            $flattened[] = $component;

            $reflection = new ReflectionObject($component);

            while (! $reflection->hasProperty('childComponents') && ($parent = $reflection->getParentClass())) {
                $reflection = $parent;
            }

            if (! $reflection->hasProperty('childComponents')) {
                continue;
            }

            $childComponentsProperty = $reflection->getProperty('childComponents');
            $childComponents = $childComponentsProperty->getValue($component);

            if (! is_array($childComponents)) {
                continue;
            }

            $defaultChildComponents = $childComponents['default'] ?? null;

            if (! is_array($defaultChildComponents)) {
                continue;
            }

            array_push($flattened, ...$flatten($defaultChildComponents));
        }

        return $flattened;
    };

    $components = collect($flatten(InstitutionContributionFormSchema::components(includeMedia: true, requireGoogleMaps: true)))
        ->keyBy(fn (mixed $component): ?string => method_exists($component, 'getName') ? $component->getName() : null);

    $googleMaps = $components->get('google_maps_url');

    expect($googleMaps)->not->toBeNull();
    expect(method_exists($googleMaps, 'isRequired'))->toBeTrue();
    expect($googleMaps?->isRequired())->toBeTrue();
});

it('hides the raw google maps field in picker mode while keeping the location requirement', function () {
    config()->set('services.google.maps_api_key', 'test-maps-key');
    config()->set('services.google.places_enabled', true);

    $flatten = function (array $components) use (&$flatten): array {
        $flattened = [];

        foreach ($components as $component) {
            $flattened[] = $component;

            $reflection = new ReflectionObject($component);

            while (! $reflection->hasProperty('childComponents') && ($parent = $reflection->getParentClass())) {
                $reflection = $parent;
            }

            if (! $reflection->hasProperty('childComponents')) {
                continue;
            }

            $childComponentsProperty = $reflection->getProperty('childComponents');
            $childComponents = $childComponentsProperty->getValue($component);

            if (! is_array($childComponents)) {
                continue;
            }

            $defaultChildComponents = $childComponents['default'] ?? null;

            if (! is_array($defaultChildComponents)) {
                continue;
            }

            array_push($flattened, ...$flatten($defaultChildComponents));
        }

        return $flattened;
    };

    $components = collect($flatten(InstitutionContributionFormSchema::components(
        includeMedia: true,
        requireGoogleMaps: true,
        addressStatePath: 'address',
        includeLocationPicker: true,
    )))->keyBy(fn (mixed $component): ?string => method_exists($component, 'getName') ? $component->getName() : null);

    $googleMaps = $components->get('google_maps_url');

    expect($googleMaps)->toBeInstanceOf(Hidden::class);
    expect(method_exists($googleMaps, 'isRequired'))->toBeTrue();
    expect($googleMaps?->isRequired())->toBeTrue();
});

it('shows the raw google maps field in manual institution contribution mode', function () {
    config()->set('services.google.maps_api_key', null);
    config()->set('services.google.places_enabled', false);

    $flatten = function (array $components) use (&$flatten): array {
        $flattened = [];

        foreach ($components as $component) {
            $flattened[] = $component;

            $reflection = new ReflectionObject($component);

            while (! $reflection->hasProperty('childComponents') && ($parent = $reflection->getParentClass())) {
                $reflection = $parent;
            }

            if (! $reflection->hasProperty('childComponents')) {
                continue;
            }

            $childComponentsProperty = $reflection->getProperty('childComponents');
            $childComponents = $childComponentsProperty->getValue($component);

            if (! is_array($childComponents)) {
                continue;
            }

            $defaultChildComponents = $childComponents['default'] ?? null;

            if (! is_array($defaultChildComponents)) {
                continue;
            }

            array_push($flattened, ...$flatten($defaultChildComponents));
        }

        return $flattened;
    };

    $components = collect($flatten(InstitutionContributionFormSchema::components(
        includeMedia: true,
        requireGoogleMaps: true,
        addressStatePath: 'address',
        includeLocationPicker: true,
    )))->keyBy(fn (mixed $component): ?string => method_exists($component, 'getName') ? $component->getName() : null);

    $googleMaps = $components->get('google_maps_url');

    expect($googleMaps)->toBeInstanceOf(TextInput::class);
    expect(method_exists($googleMaps, 'isRequired'))->toBeTrue();
    expect($googleMaps?->isRequired())->toBeTrue();
});

it('uses rich description and full contact details in the institution quick-create form', function () {
    $components = collect(InstitutionFormSchema::createOptionForm())
        ->keyBy(fn (mixed $component): ?string => method_exists($component, 'getName') ? $component->getName() : null);

    expect($components->get('description'))->toBeInstanceOf(RichEditor::class)
        ->and($components->get('contacts'))->toBeInstanceOf(Repeater::class)
        ->and($components->has('proposer_note'))->toBeFalse()
        ->and($components->has('logo'))->toBeFalse();
});

it('stores description and contacts when creating an institution via quick-create', function () {
    $institutionId = InstitutionFormSchema::createOptionUsing([
        'name' => 'Masjid Quick Create',
        'type' => 'masjid',
        'description' => '<p>Institusi komuniti yang aktif.</p>',
        'contacts' => [[
            'category' => 'phone',
            'value' => '0123456789',
            'type' => 'main',
            'is_public' => true,
        ]],
    ]);

    $institution = Institution::query()
        ->with('contacts')
        ->findOrFail($institutionId);

    expect($institution->description)->toBe('<p>Institusi komuniti yang aktif.</p>')
        ->and($institution->contacts->pluck('value')->all())->toContain('0123456789');
});

it('stores nested institution quick-create address data when picker mode is used', function () {
    config()->set('services.google.place_link_resolution_enabled', true);
    config()->set('services.google.places_server_api_key', 'server-test-key');

    Http::fake([
        'https://maps.app.goo.gl/*' => Http::response('', 302, [
            'Location' => 'https://www.google.com/maps/place/Masjid+Jamik+Ungku+Ahmad,+Kampung+Separap/@1.9089362,102.865462,925m/data=!3m2!1e3!4b1!4m6!3m5!1s0x31d0539173ae7dd9:0xb4fce77c077ec5f3!8m2!3d1.9089362!4d102.865462!16s%2Fg%2F11sqw6yjrc?hl=en-US&entry=ttu',
        ]),
    ]);

    $institutionId = InstitutionFormSchema::createOptionUsing([
        'name' => 'Masjid Event Quick Create',
        'type' => 'masjid',
        'address' => [
            'google_maps_url' => 'https://maps.app.goo.gl/KWFQuuxAmSK3kRFM8',
            'google_maps_remote_lookup_enabled' => false,
        ],
    ]);

    $institution = Institution::query()
        ->with('address')
        ->findOrFail($institutionId);

    expect($institution->addressModel?->google_maps_url)->toBe('https://www.google.com/maps/search/?api=1&query=1.9089362%2C102.865462')
        ->and($institution->addressModel?->google_place_id)->toBeNull();
    expect(abs(((float) $institution->addressModel?->lat) - 1.9089362))->toBeLessThan(0.000001);
    expect(abs(((float) $institution->addressModel?->lng) - 102.865462))->toBeLessThan(0.000001);

    Http::assertSentCount(1);
});

it('stores nested venue quick-create address data when picker mode is used', function () {
    config()->set('services.google.place_link_resolution_enabled', true);
    config()->set('services.google.places_server_api_key', 'server-test-key');

    Http::fake([
        'https://maps.app.goo.gl/*' => Http::response('', 302, [
            'Location' => 'https://www.google.com/maps/place/Masjid+Jamik+Ungku+Ahmad,+Kampung+Separap/@1.9089362,102.865462,925m/data=!3m2!1e3!4b1!4m6!3m5!1s0x31d0539173ae7dd9:0xb4fce77c077ec5f3!8m2!3d1.9089362!4d102.865462!16s%2Fg%2F11sqw6yjrc?hl=en-US&entry=ttu',
        ]),
    ]);

    $venueId = VenueFormSchema::createOptionUsing([
        'name' => 'Dewan Event Quick Create',
        'type' => 'dewan',
        'address' => [
            'google_maps_url' => 'https://maps.app.goo.gl/KWFQuuxAmSK3kRFM8',
            'google_maps_remote_lookup_enabled' => false,
        ],
    ]);

    $venue = Venue::query()
        ->with('address')
        ->findOrFail($venueId);

    expect($venue->addressModel?->google_maps_url)->toBe('https://www.google.com/maps/search/?api=1&query=1.9089362%2C102.865462')
        ->and($venue->addressModel?->google_place_id)->toBeNull();
    expect(abs(((float) $venue->addressModel?->lat) - 1.9089362))->toBeLessThan(0.000001);
    expect(abs(((float) $venue->addressModel?->lng) - 102.865462))->toBeLessThan(0.000001);

    Http::assertSentCount(1);
});

it('uses rich description and no logo upload in the institution contribution form', function () {
    $flatten = function (array $components) use (&$flatten): array {
        $flattened = [];

        foreach ($components as $component) {
            $flattened[] = $component;

            $reflection = new ReflectionObject($component);

            while (! $reflection->hasProperty('childComponents') && ($parent = $reflection->getParentClass())) {
                $reflection = $parent;
            }

            if (! $reflection->hasProperty('childComponents')) {
                continue;
            }

            $childComponentsProperty = $reflection->getProperty('childComponents');
            $childComponents = $childComponentsProperty->getValue($component);

            if (! is_array($childComponents)) {
                continue;
            }

            $defaultChildComponents = $childComponents['default'] ?? null;

            if (! is_array($defaultChildComponents)) {
                continue;
            }

            array_push($flattened, ...$flatten($defaultChildComponents));
        }

        return $flattened;
    };

    $components = collect($flatten(InstitutionContributionFormSchema::components(includeMedia: true)))
        ->keyBy(fn (mixed $component): ?string => method_exists($component, 'getName') ? $component->getName() : null);

    expect($components->get('description'))->toBeInstanceOf(RichEditor::class)
        ->and($components->get('contacts'))->toBeInstanceOf(Repeater::class)
        ->and($components->has('logo'))->toBeFalse();
});

it('uses the same core speaker fields in the quick-create modal and dedicated contribution form', function () {
    $flatten = function (array $components) use (&$flatten): array {
        $flattened = [];

        foreach ($components as $component) {
            $flattened[] = $component;

            $reflection = new ReflectionObject($component);

            while (! $reflection->hasProperty('childComponents') && ($parent = $reflection->getParentClass())) {
                $reflection = $parent;
            }

            if (! $reflection->hasProperty('childComponents')) {
                continue;
            }

            $childComponentsProperty = $reflection->getProperty('childComponents');
            $childComponents = $childComponentsProperty->getValue($component);

            if (! is_array($childComponents)) {
                continue;
            }

            $defaultChildComponents = $childComponents['default'] ?? null;

            if (! is_array($defaultChildComponents)) {
                continue;
            }

            array_push($flattened, ...$flatten($defaultChildComponents));
        }

        return $flattened;
    };

    $quickCreateComponents = collect($flatten(SpeakerFormSchema::createOptionForm()))
        ->keyBy(fn (mixed $component): ?string => method_exists($component, 'getName') ? $component->getName() : null);

    $contributionComponents = collect($flatten(SpeakerContributionFormSchema::components(includeMedia: true)))
        ->keyBy(fn (mixed $component): ?string => method_exists($component, 'getName') ? $component->getName() : null);

    foreach (['bio', 'language_ids', 'qualifications', 'contacts', 'social_media', 'avatar', 'cover', 'gallery', 'line1', 'google_maps_url'] as $field) {
        expect($quickCreateComponents->has($field))->toBeTrue();
        expect($contributionComponents->has($field))->toBeTrue();
    }
});

it('stores structured speaker quick-create details when creating a speaker via quick-create', function () {
    $language = Language::where('code', 'ms')->first() ?? Language::query()->create([
        'code' => 'ms',
        'name' => 'Malay',
        'name_native' => 'Bahasa Melayu',
        'dir' => 'ltr',
    ]);

    $speakerId = SpeakerFormSchema::createOptionUsing([
        'name' => 'Ustaz Quick Create',
        'gender' => 'male',
        'bio' => ['type' => 'doc', 'content' => []],
        'qualifications' => [[
            'institution' => 'Universiti Islam',
            'degree' => 'MA',
            'field' => 'Dakwah',
            'year' => '2020',
        ]],
        'language_ids' => [$language->id],
        'line1' => 'Jalan Hikmah 8',
        'state_id' => 1,
        'google_maps_url' => 'https://maps.google.com/?q=3.1390,101.6869',
        'contacts' => [[
            'category' => 'phone',
            'value' => '0123456789',
            'type' => 'main',
            'is_public' => true,
        ]],
        'social_media' => [[
            'platform' => 'facebook',
            'url' => 'https://facebook.com/ustaz.quick.create',
        ]],
    ]);

    $speaker = Speaker::query()
        ->with(['address', 'contacts', 'socialMedia', 'languages'])
        ->findOrFail($speakerId);

    expect($speaker->qualifications)->toBeArray()
        ->and($speaker->addressModel?->line1)->toBe('Jalan Hikmah 8')
        ->and($speaker->addressModel?->google_maps_url)->toBe('https://www.google.com/maps/search/?api=1&query=3.139%2C101.6869')
        ->and($speaker->contacts->pluck('value')->all())->toContain('0123456789')
        ->and($speaker->socialMedia->pluck('platform')->all())->toContain('facebook')
        ->and($speaker->languages->pluck('id')->all())->toContain($language->id);
});
