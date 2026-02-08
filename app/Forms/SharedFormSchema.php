<?php

namespace App\Forms;

use App\Enums\SocialMediaPlatform;
use App\Models\District;
use App\Models\State;
use App\Models\Subdistrict;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

class SharedFormSchema
{
    /**
     * Address fields (line1, line2, postcode, state/district/subdistrict cascades, maps URLs).
     *
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function addressFields(): array
    {
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

            Select::make('state_id')
                ->label(__('Negeri'))
                ->options(fn () => State::where('country_id', 132)->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdatedJs(<<<'JS'
                    $set('district_id', null)
                    $set('subdistrict_id', null)
                    JS),

            Select::make('district_id')
                ->label(__('Daerah'))
                ->options(function (Get $get) {
                    $stateId = $get('state_id');
                    if (! $stateId) {
                        return [];
                    }

                    return District::where('state_id', $stateId)
                        ->orderBy('name')
                        ->pluck('name', 'id');
                })
                ->searchable()
                ->live()
                ->afterStateUpdatedJs(<<<'JS'
                    $set('subdistrict_id', null)
                    JS)
                ->visible(fn (Get $get): bool => filled($get('state_id'))),

            Select::make('subdistrict_id')
                ->label(__('Daerah Kecil / Bandar / Mukim'))
                ->options(function (Get $get) {
                    $districtId = $get('district_id');
                    if (! $districtId) {
                        return [];
                    }

                    return Subdistrict::where('district_id', $districtId)
                        ->orderBy('name')
                        ->pluck('name', 'id');
                })
                ->searchable()
                ->visible(fn (Get $get): bool => filled($get('district_id'))),

            TextInput::make('google_maps_url')
                ->label(__('Google Maps URL'))
                ->url()
                ->maxLength(255)
                ->placeholder(__('https://maps.google.com/...')),

            TextInput::make('waze_url')
                ->label(__('Waze URL'))
                ->url()
                ->maxLength(255)
                ->placeholder(__('https://waze.com/ul/...')),
        ];
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
                TextInput::make('url')
                    ->label(__('URL'))
                    ->required()
                    ->url()
                    ->maxLength(255)
                    ->placeholder(__('https://...')),
                TextInput::make('username')
                    ->label(__('Username'))
                    ->maxLength(255)
                    ->placeholder(__('@username')),
            ])
            ->collapsible()
            ->defaultItems(0)
            ->addActionLabel(__('Add Social Media'))
            ->helperText(__($helperText));
    }

    /**
     * Create address record for a model that has an address() relationship.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  array<string, mixed>  $data
     */
    public static function createAddressFromData($model, array $data, string $type = 'main'): void
    {
        if (! empty($data['line1']) || ! empty($data['state_id'])) {
            $model->address()->create([
                'type' => $type,
                'line1' => $data['line1'] ?? null,
                'line2' => $data['line2'] ?? null,
                'postcode' => $data['postcode'] ?? null,
                'country_id' => 132, // Malaysia
                'state_id' => $data['state_id'] ?? null,
                'district_id' => $data['district_id'] ?? null,
                'subdistrict_id' => $data['subdistrict_id'] ?? null,
                'google_maps_url' => $data['google_maps_url'] ?? null,
                'waze_url' => $data['waze_url'] ?? null,
            ]);
        }
    }

    /**
     * Create social media entries for a model.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  array<string, mixed>  $data
     */
    public static function createSocialMediaFromData($model, array $data): void
    {
        if (! empty($data['social_media'])) {
            foreach ($data['social_media'] as $social) {
                $model->socialMedia()->create([
                    'platform' => $social['platform'],
                    'url' => $social['url'],
                    'username' => $social['username'] ?? null,
                ]);
            }
        }
    }
}
