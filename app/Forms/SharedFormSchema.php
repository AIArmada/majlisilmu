<?php

namespace App\Forms;

use App\Actions\Location\NormalizeGoogleMapsInputAction;
use App\Enums\ContactCategory;
use App\Enums\ContactType;
use App\Enums\SocialMediaPlatform;
use App\Models\Country;
use App\Models\District;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use App\Models\State;
use App\Models\Subdistrict;
use App\Models\Venue;
use App\Support\Location\FederalTerritoryLocation;
use App\Support\Location\PreferredCountryResolver;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Arr;

class SharedFormSchema
{
    /**
     * Address fields (line1, line2, postcode, state/district/subdistrict cascades, maps URLs).
     *
     * @return array<int, Component>
     */
    public static function addressFields(
        bool $requireGoogleMaps = false,
        bool $showGoogleMapsUrlField = true,
        bool $enableGoogleMapsNormalization = true,
        bool $enableGoogleMapsRemoteLookup = true,
        bool $includeCountryField = false,
        ?bool $showCountryField = null,
        ?int $defaultCountryId = null,
    ): array {
        $defaultCountryId ??= PreferredCountryResolver::MALAYSIA_ID;
        $showCountryField ??= true;

        return [
            TextInput::make('line1')
                ->label(__('Address Line 1'))
                ->maxLength(255)
                ->placeholder(__('e.g., No. 123, Jalan Masjid')),

            TextInput::make('line2')
                ->label(__('Address Line 2'))
                ->maxLength(255)
                ->placeholder(__('e.g., Taman Indah')),

            TextInput::make('postcode')
                ->label(__('Postcode'))
                ->maxLength(16)
                ->placeholder(__('e.g., 50000')),

            ...($includeCountryField
                ? [($showCountryField
                    ? Select::make('country_id')
                        ->label(__('Country'))
                        ->options(fn (): array => Country::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->default($defaultCountryId)
                        ->afterStateUpdatedJs(<<<'JS'
                            const guard = Number($get('cascade_reset_guard') ?? 0)

                            if (guard > 0) {
                                $set('cascade_reset_guard', guard - 1)
                            } else {
                                $set('state_id', null)
                                $set('district_id', null)
                                $set('subdistrict_id', null)
                            }
                            JS)
                    : Hidden::make('country_id')->default($defaultCountryId))]
                : [Hidden::make('country_id')->default($defaultCountryId)]),

            Hidden::make('lat'),
            Hidden::make('lng'),
            Hidden::make('google_place_id'),
            Hidden::make('google_display_name'),
            Hidden::make('google_resolution_source'),
            Hidden::make('google_resolution_status'),
            Hidden::make('google_resolution_fingerprint'),
            Hidden::make('google_resolution_message'),
            Hidden::make('google_maps_normalization_enabled')
                ->default($enableGoogleMapsNormalization),
            Hidden::make('google_maps_remote_lookup_enabled')
                ->default($enableGoogleMapsRemoteLookup),
            Hidden::make('cascade_reset_guard')
                ->default(0)
                ->dehydrated(false),

            Select::make('state_id')
                ->label(__('Negeri'))
                ->options(fn (Get $get): array => self::stateOptionsForCountry(
                    $includeCountryField ? $get('country_id') : $defaultCountryId,
                ))
                ->searchable()
                ->preload()
                ->live()
                ->disabled(fn (Get $get): bool => $includeCountryField && self::normalizeLocationId($get('country_id')) === null)
                ->afterStateUpdatedJs(<<<'JS'
                    const guard = Number($get('cascade_reset_guard') ?? 0)

                    if (guard > 0) {
                        $set('cascade_reset_guard', guard - 1)
                    } else {
                        $set('district_id', null)
                        $set('subdistrict_id', null)
                    }
                    JS),

            Select::make('district_id')
                ->label(__('Daerah'))
                ->options(fn (Get $get): array => self::districtOptionsForState($get('state_id')))
                ->searchable()
                ->live()
                ->afterStateUpdatedJs(<<<'JS'
                    const guard = Number($get('cascade_reset_guard') ?? 0)

                    if (guard > 0) {
                        $set('cascade_reset_guard', guard - 1)
                    } else {
                        $set('subdistrict_id', null)
                    }
                    JS)
                ->visible(fn (Get $get): bool => filled($get('state_id')) && ! FederalTerritoryLocation::isFederalTerritoryStateId($get('state_id'))),

            Select::make('subdistrict_id')
                ->label(__('Bandar / Mukim / Zon'))
                ->options(fn (Get $get): array => self::subdistrictOptionsForSelection($get('state_id'), $get('district_id')))
                ->searchable()
                ->visible(fn (Get $get): bool => self::shouldShowSubdistrictField($get('state_id'), $get('district_id'))),

            ...($showGoogleMapsUrlField
                ? [self::googleMapsUrlField(required: $requireGoogleMaps)]
                : [Hidden::make('google_maps_url')->required($requireGoogleMaps)]),

            TextInput::make('waze_url')
                ->label(__('Waze URL'))
                ->url()
                ->maxLength(255)
                ->placeholder(__('https://waze.com/ul/...')),
        ];
    }

    public static function addressGroup(
        bool $requireGoogleMaps = false,
        ?string $statePath = null,
        bool $showGoogleMapsUrlField = true,
        bool $enableGoogleMapsNormalization = true,
        bool $enableGoogleMapsRemoteLookup = true,
        bool $includeCountryField = false,
        ?bool $showCountryField = null,
        ?int $defaultCountryId = null,
    ): Group {
        $group = Group::make(self::addressFields(
            requireGoogleMaps: $requireGoogleMaps,
            showGoogleMapsUrlField: $showGoogleMapsUrlField,
            enableGoogleMapsNormalization: $enableGoogleMapsNormalization,
            enableGoogleMapsRemoteLookup: $enableGoogleMapsRemoteLookup,
            includeCountryField: $includeCountryField,
            showCountryField: $showCountryField,
            defaultCountryId: $defaultCountryId,
        ))
            ->columns(2);

        if ($statePath !== null) {
            $group->statePath($statePath);
        }

        return $group;
    }

    public static function googleMapsUrlField(bool $required = false, ?string $defaultHelperText = null): TextInput
    {
        return TextInput::make('google_maps_url')
            ->label(__('Google Maps URL'))
            ->url()
            ->required($required)
            ->live(onBlur: true)
            ->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
                self::normalizeGoogleMapsFieldState($get, $set, $state, $old);
            })
            ->placeholder(__('https://maps.google.com/...'))
            ->helperText(function (Get $get) use ($defaultHelperText): string {
                $message = $get('google_resolution_message');

                if (is_string($message) && $message !== '') {
                    return $message;
                }

                return $defaultHelperText ?? __('Paste the full Google Maps link from your browser');
            });
    }

    /**
     * Social media repeater schema.
     */
    public static function socialMediaRepeater(string $helperText = 'Add social media links'): Repeater
    {
        return Repeater::make('social_media')
            ->label(__('Social Media'))
            ->schema([
                Select::make('platform')
                    ->label(__('Platform'))
                    ->required()
                    ->options(SocialMediaPlatform::class)
                    ->searchable(),
                TextInput::make('username')
                    ->label(__('Username / Handle'))
                    ->requiredWithout('url')
                    ->maxLength(255)
                    ->placeholder(__('@username / https://...')),
                TextInput::make('url')
                    ->label(__('URL'))
                    ->requiredWithout('username')
                    ->url()
                    ->maxLength(255)
                    ->placeholder(__('https://...')),
            ])
            ->collapsible()
            ->defaultItems(0)
            ->addActionLabel(__('Add Social Media'))
            ->helperText(__($helperText));
    }

    public static function contactsRepeater(?string $helperText = null): Repeater
    {
        $repeater = Repeater::make('contacts')
            ->label(__('Contact Details'))
            ->default([])
            ->schema([
                Select::make('category')
                    ->label(__('Category'))
                    ->options(ContactCategory::class)
                    ->required()
                    ->live(),
                TextInput::make('value')
                    ->label(fn (Get $get): string => match ($get('category')) {
                        ContactCategory::Email, ContactCategory::Email->value => __('Email Address'),
                        ContactCategory::Phone, ContactCategory::Phone->value => __('Phone Number'),
                        ContactCategory::WhatsApp, ContactCategory::WhatsApp->value => __('WhatsApp Number'),
                        default => __('Value'),
                    })
                    ->required()
                    ->maxLength(255)
                    ->email(fn (Get $get): bool => in_array($get('category'), [ContactCategory::Email, ContactCategory::Email->value], true))
                    ->tel(fn (Get $get): bool => in_array($get('category'), [ContactCategory::Phone, ContactCategory::Phone->value, ContactCategory::WhatsApp, ContactCategory::WhatsApp->value], true)),
                Select::make('type')
                    ->label(__('Type'))
                    ->options(ContactType::class)
                    ->default(ContactType::Main->value)
                    ->required(),
                Toggle::make('is_public')
                    ->label(__('Public'))
                    ->default(true),
            ])
            ->columns(4);

        if ($helperText !== null) {
            $repeater->helperText(__($helperText));
        }

        return $repeater;
    }

    /**
     * Create address record for a model that has an address() relationship.
     *
     * @param  array<string, mixed>  $data
     */
    public static function createAddressFromData(Event|Institution|Speaker|Venue $model, array $data, string $type = 'main'): void
    {
        $data = self::prepareAddressPersistenceData($data);

        if (
            ! empty($data['line1'])
            || ! empty($data['state_id'])
            || ! empty($data['google_maps_url'])
            || ! empty($data['lat'])
            || ! empty($data['lng'])
        ) {
            $model->address()->create([
                'type' => $type,
                'line1' => $data['line1'] ?? null,
                'line2' => $data['line2'] ?? null,
                'postcode' => $data['postcode'] ?? null,
                'country_id' => self::normalizeLocationId($data['country_id'] ?? null) ?? PreferredCountryResolver::MALAYSIA_ID,
                'state_id' => $data['state_id'] ?? null,
                'district_id' => $data['district_id'] ?? null,
                'subdistrict_id' => $data['subdistrict_id'] ?? null,
                'lat' => isset($data['lat']) && $data['lat'] !== '' ? (float) $data['lat'] : null,
                'lng' => isset($data['lng']) && $data['lng'] !== '' ? (float) $data['lng'] : null,
                'google_maps_url' => $data['google_maps_url'] ?? null,
                'google_place_id' => $data['google_place_id'] ?? null,
                'waze_url' => $data['waze_url'] ?? null,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalizeAddressFormState(array $data): array
    {
        if (! self::shouldNormalizeGoogleMaps($data)) {
            return array_merge($data, [
                'google_place_id' => $data['google_place_id'] ?? null,
                'google_display_name' => $data['google_display_name'] ?? null,
                'google_resolution_source' => null,
                'google_resolution_status' => null,
                'google_resolution_fingerprint' => null,
                'google_resolution_message' => null,
            ]);
        }

        return array_merge($data, app(NormalizeGoogleMapsInputAction::class)->handle([
            'google_maps_url' => $data['google_maps_url'] ?? null,
            'google_place_id' => $data['google_place_id'] ?? null,
            'google_display_name' => $data['google_display_name'] ?? null,
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
            'google_maps_remote_lookup_enabled' => $data['google_maps_remote_lookup_enabled'] ?? null,
            'google_resolution_source' => $data['google_resolution_source'] ?? null,
            'google_resolution_status' => $data['google_resolution_status'] ?? null,
            'google_resolution_fingerprint' => $data['google_resolution_fingerprint'] ?? null,
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function prepareAddressPersistenceData(array $data): array
    {
        $normalized = self::normalizeAddressFormState($data);

        if (FederalTerritoryLocation::isFederalTerritoryStateId($normalized['state_id'] ?? null)) {
            $normalized['district_id'] = null;
        }

        return Arr::only($normalized, [
            'country_id',
            'state_id',
            'district_id',
            'subdistrict_id',
            'line1',
            'line2',
            'postcode',
            'lat',
            'lng',
            'google_maps_url',
            'google_place_id',
            'waze_url',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function hydrateAddressFormState(array $data): array
    {
        $googleMapsUrl = is_string($data['google_maps_url'] ?? null) ? trim($data['google_maps_url']) : null;
        $googlePlaceId = is_string($data['google_place_id'] ?? null) ? trim($data['google_place_id']) : null;
        $lat = $data['lat'] ?? null;
        $lng = $data['lng'] ?? null;

        $status = 'unresolved';

        if ($googlePlaceId !== null && $googlePlaceId !== '') {
            $status = 'resolved';
        } elseif (($googleMapsUrl !== null && $googleMapsUrl !== '') || filled($lat) || filled($lng)) {
            $status = 'partial';
        }

        return array_merge($data, [
            'google_display_name' => $data['google_display_name'] ?? null,
            'google_resolution_source' => $data['google_resolution_source'] ?? ($googleMapsUrl !== null ? 'manual' : null),
            'google_resolution_status' => $data['google_resolution_status'] ?? $status,
            'google_resolution_fingerprint' => $data['google_resolution_fingerprint'] ?? ($googleMapsUrl !== null && $googleMapsUrl !== '' ? sha1($googleMapsUrl) : null),
            'google_resolution_message' => $data['google_resolution_message'] ?? null,
        ]);
    }

    private static function normalizeGoogleMapsFieldState(Get $get, Set $set, ?string $state, ?string $old): void
    {
        $currentValue = is_string($state) ? trim($state) : null;
        $oldValue = is_string($old) ? trim($old) : null;

        if ($currentValue === $oldValue) {
            return;
        }

        if (! self::shouldNormalizeGoogleMaps([
            'google_maps_normalization_enabled' => $get('google_maps_normalization_enabled'),
        ])) {
            foreach ([
                'google_place_id',
                'google_display_name',
                'lat',
                'lng',
                'google_resolution_source',
                'google_resolution_status',
                'google_resolution_fingerprint',
                'google_resolution_message',
            ] as $field) {
                $set($field, null);
            }

            return;
        }

        $resolutionFingerprint = $get('google_resolution_fingerprint');

        if (
            (! is_string($resolutionFingerprint) || $resolutionFingerprint === '')
            && is_string($oldValue)
            && $oldValue !== ''
        ) {
            $resolutionFingerprint = sha1($oldValue);
        }

        $normalized = self::normalizeAddressFormState([
            'google_maps_url' => $state,
            'google_place_id' => $get('google_place_id'),
            'google_display_name' => $get('google_display_name'),
            'lat' => $get('lat'),
            'lng' => $get('lng'),
            'google_maps_remote_lookup_enabled' => $get('google_maps_remote_lookup_enabled'),
            'google_resolution_source' => $get('google_resolution_source'),
            'google_resolution_status' => $get('google_resolution_status'),
            'google_resolution_fingerprint' => $resolutionFingerprint,
        ]);

        foreach ([
            'google_maps_url',
            'google_place_id',
            'google_display_name',
            'lat',
            'lng',
            'google_resolution_source',
            'google_resolution_status',
            'google_resolution_fingerprint',
            'google_resolution_message',
        ] as $field) {
            $set($field, $normalized[$field] ?? null);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function shouldNormalizeGoogleMaps(array $data): bool
    {
        return filter_var($data['google_maps_normalization_enabled'] ?? true, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? true;
    }

    /**
     * Create social media entries for a model.
     *
     * @param  array<string, mixed>  $data
     */
    public static function createSocialMediaFromData(Institution|Speaker|Venue|Reference $model, array $data): void
    {
        if (! empty($data['social_media'])) {
            foreach ($data['social_media'] as $social) {
                $model->socialMedia()->create([
                    'platform' => $social['platform'],
                    'url' => $social['url'] ?? null,
                    'username' => $social['username'] ?? null,
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function createContactsFromData(Institution|Speaker $model, array $data): void
    {
        if (! isset($data['contacts']) || ! is_array($data['contacts'])) {
            return;
        }

        foreach ($data['contacts'] as $contact) {
            if (! is_array($contact)) {
                continue;
            }

            $category = $contact['category'] ?? null;
            $value = $contact['value'] ?? null;

            if (! filled($category) || ! filled($value)) {
                continue;
            }

            $model->contacts()->create([
                'category' => $category,
                'value' => $value,
                'type' => $contact['type'] ?? ContactType::Main->value,
                'is_public' => (bool) ($contact['is_public'] ?? true),
            ]);
        }
    }

    /**
     * @return array<int|string, string>
     */
    public static function districtOptionsForState(int|string|null $stateId): array
    {
        if (! filled($stateId) || FederalTerritoryLocation::isFederalTerritoryStateId($stateId)) {
            return [];
        }

        return District::query()
            ->where('state_id', $stateId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public static function stateOptionsForCountry(int|string|null $countryId): array
    {
        $countryId = self::normalizeLocationId($countryId);

        if ($countryId === null) {
            return [];
        }

        return State::query()
            ->where('country_id', $countryId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int|string, string>
     */
    public static function subdistrictOptionsForSelection(int|string|null $stateId, int|string|null $districtId): array
    {
        if (FederalTerritoryLocation::isFederalTerritoryStateId($stateId)) {
            if (! filled($stateId)) {
                return [];
            }

            return Subdistrict::query()
                ->where('state_id', $stateId)
                ->whereNull('district_id')
                ->orderBy('name')
                ->pluck('name', 'id')
                ->all();
        }

        if (! filled($districtId)) {
            return [];
        }

        return Subdistrict::query()
            ->where('district_id', $districtId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function shouldShowSubdistrictField(int|string|null $stateId, int|string|null $districtId): bool
    {
        if (FederalTerritoryLocation::isFederalTerritoryStateId($stateId)) {
            return filled($stateId);
        }

        return filled($districtId);
    }

    public static function preferredPublicCountryId(): int
    {
        return app(PreferredCountryResolver::class)->resolveId();
    }

    public static function publicLocationPickerCascadeResetGuard(): int
    {
        return 2;
    }

    public static function normalizeLocationId(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '' || ! ctype_digit($trimmed)) {
            return null;
        }

        return (int) $trimmed;
    }
}
