<?php

use App\Forms\InstitutionContributionFormSchema;
use App\Forms\InstitutionFormSchema;
use App\Forms\SharedFormSchema;
use App\Forms\SpeakerContributionFormSchema;
use App\Forms\SpeakerFormSchema;
use App\Forms\VenueFormSchema;
use App\Models\Institution;
use App\Models\Speaker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        ->and($speaker->addressModel?->google_maps_url)->toContain('maps.google.com')
        ->and($speaker->contacts->pluck('value')->all())->toContain('0123456789')
        ->and($speaker->socialMedia->pluck('platform')->all())->toContain('facebook')
        ->and($speaker->languages->pluck('id')->all())->toContain($language->id);
});
