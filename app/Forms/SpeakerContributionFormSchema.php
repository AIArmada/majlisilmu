<?php

namespace App\Forms;

use App\Enums\ContactCategory;
use App\Enums\ContactType;
use App\Enums\Gender;
use App\Enums\Honorific;
use App\Enums\PostNominal;
use App\Enums\PreNominal;
use App\Enums\SocialMediaPlatform;
use App\Models\District;
use App\Models\State;
use App\Models\Subdistrict;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Nnjeim\World\Models\Language;

class SpeakerContributionFormSchema
{
    /**
     * @return array<int, Component>
     */
    public static function components(bool $includeMedia = true): array
    {
        $components = [
            Section::make(__('Profil Penceramah'))
                ->schema([
                    TextInput::make('name')
                        ->label(__('Speaker Name'))
                        ->required()
                        ->maxLength(255),
                    Select::make('gender')
                        ->label(__('Gender'))
                        ->options(Gender::class)
                        ->default(Gender::Male->value)
                        ->required(),
                    Toggle::make('is_freelance')
                        ->label(__('Penceramah Bebas'))
                        ->default(false)
                        ->live(),
                    TextInput::make('job_title')
                        ->label(__('Job Title'))
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => (bool) $get('is_freelance')),
                    Select::make('honorific')
                        ->label(__('Honorific'))
                        ->options(Honorific::class)
                        ->multiple()
                        ->preload()
                        ->searchable(),
                    Select::make('pre_nominal')
                        ->label(__('Pre-nominal'))
                        ->options(PreNominal::class)
                        ->multiple()
                        ->preload()
                        ->searchable(),
                    Select::make('post_nominal')
                        ->label(__('Post-nominal'))
                        ->options(PostNominal::class)
                        ->multiple()
                        ->preload()
                        ->searchable(),
                    RichEditor::make('bio')
                        ->label(__('Biography'))
                        ->json()
                        ->columnSpanFull(),
                    Select::make('language_ids')
                        ->label(__('Languages'))
                        ->options(fn (): array => Language::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->multiple()
                        ->searchable()
                        ->preload(),
                ])
                ->columns(2),
            Section::make(__('Location / Base'))
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
                ])
                ->columns(2),
            Section::make(__('Education'))
                ->schema([
                    Repeater::make('qualifications')
                        ->label(__('Qualifications'))
                        ->default([])
                        ->schema([
                            TextInput::make('institution')
                                ->label(__('Institution'))
                                ->required(),
                            TextInput::make('degree')
                                ->label(__('Degree / Level'))
                                ->required(),
                            TextInput::make('field')
                                ->label(__('Field of Study')),
                            TextInput::make('year')
                                ->label(__('Year'))
                                ->numeric()
                                ->length(4),
                        ])
                        ->columns(2),
                ]),
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
            array_splice($components, 3, 0, [
                Section::make(__('Media'))
                    ->schema([
                        SpatieMediaLibraryFileUpload::make('avatar')
                            ->label(__('Avatar'))
                            ->collection('avatar')
                            ->image()
                            ->imageEditor()
                            ->circleCropper()
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
                            ->responsiveImages()
                            ->conversion('banner'),
                        SpatieMediaLibraryFileUpload::make('gallery')
                            ->label(__('Gallery'))
                            ->collection('gallery')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->responsiveImages()
                            ->conversion('gallery_thumb')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
        }

        return $components;
    }
}
