<?php

namespace App\Forms;

use App\Enums\ContactCategory;
use App\Enums\ContactType;
use App\Enums\InstitutionType;
use App\Enums\SocialMediaPlatform;
use App\Models\District;
use App\Models\State;
use App\Models\Subdistrict;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;

class InstitutionContributionFormSchema
{
    /**
     * @return array<int, Component>
     */
    public static function components(bool $includeMedia = true): array
    {
        $components = [
            Section::make(__('Profil Institusi'))
                ->schema([
                    Select::make('type')
                        ->label(__('Institution Type'))
                        ->options(InstitutionType::class)
                        ->required(),
                    TextInput::make('name')
                        ->label(__('Institution Name'))
                        ->required()
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label(__('Description'))
                        ->rows(5)
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make(__('Contact'))
                ->schema([
                    Repeater::make('contacts')
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
                        ->columns(4),
                ]),
            Section::make(__('Location'))
                ->schema([
                    Select::make('address.country_id')
                        ->label(__('Country'))
                        ->options([132 => 'Malaysia'])
                        ->default(132)
                        ->required(),
                    Select::make('address.state_id')
                        ->label(__('State'))
                        ->options(fn (): array => State::query()->where('country_id', 132)->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live(),
                    Select::make('address.district_id')
                        ->label(__('District'))
                        ->options(function (Get $get): array {
                            $stateId = $get('address.state_id');

                            if (! $stateId) {
                                return [];
                            }

                            return District::query()->where('state_id', $stateId)->orderBy('name')->pluck('name', 'id')->all();
                        })
                        ->searchable()
                        ->preload()
                        ->live(),
                    Select::make('address.subdistrict_id')
                        ->label(__('Subdistrict / Mukim'))
                        ->options(function (Get $get): array {
                            $districtId = $get('address.district_id');

                            if (! $districtId) {
                                return [];
                            }

                            return Subdistrict::query()->where('district_id', $districtId)->orderBy('name')->pluck('name', 'id')->all();
                        })
                        ->searchable()
                        ->preload(),
                    TextInput::make('address.line1')
                        ->label(__('Address Line 1'))
                        ->maxLength(255),
                    TextInput::make('address.line2')
                        ->label(__('Address Line 2'))
                        ->maxLength(255),
                    TextInput::make('address.postcode')
                        ->label(__('Postcode'))
                        ->maxLength(16),
                    TextInput::make('address.google_maps_url')
                        ->label(__('Google Maps URL'))
                        ->url()
                        ->maxLength(500),
                    TextInput::make('address.waze_url')
                        ->label(__('Waze URL'))
                        ->url()
                        ->maxLength(255),
                ])
                ->columns(2),
            Section::make(__('Social Media'))
                ->schema([
                    Repeater::make('social_media')
                        ->label(__('Social Media'))
                        ->default([])
                        ->schema([
                            Select::make('platform')
                                ->label(__('Platform'))
                                ->options(SocialMediaPlatform::class)
                                ->searchable()
                                ->required(),
                            TextInput::make('username')
                                ->label(__('Username / Handle'))
                                ->requiredWithout('url')
                                ->maxLength(255),
                            TextInput::make('url')
                                ->label(__('URL'))
                                ->requiredWithout('username')
                                ->url()
                                ->maxLength(255),
                        ])
                        ->columns(2),
                ]),
        ];

        if ($includeMedia) {
            array_splice($components, 1, 0, [
                Section::make(__('Media'))
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('logo')
                            ->label(__('Logo'))
                            ->collection('logo')
                            ->image()
                            ->imageEditor()
                            ->avatar()
                            ->conversion('thumb'),
                        SpatieMediaLibraryFileUpload::make('cover')
                            ->label(__('Cover Image'))
                            ->collection('cover')
                            ->image()
                            ->imageEditor()
                            ->imageAspectRatio('16:9')
                            ->automaticallyOpenImageEditorForAspectRatio()
                            ->imageEditorAspectRatioOptions(['16:9'])
                            ->automaticallyCropImagesToAspectRatio()
                            ->conversion('banner')
                            ->responsiveImages(),
                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->label(__('Gallery'))
                            ->collection('gallery')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->conversion('gallery_thumb')
                            ->responsiveImages()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
        }

        return $components;
    }
}
