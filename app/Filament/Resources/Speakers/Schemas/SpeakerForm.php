<?php

namespace App\Filament\Resources\Speakers\Schemas;

use App\Enums\ContactCategory;
use App\Enums\ContactType;
use App\Enums\Gender;
use App\Enums\Honorific;
use App\Enums\PostNominal;
use App\Enums\PreNominal;
use App\Enums\SocialMediaPlatform;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SpeakerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Profile'))
                    ->components([
                        TextInput::make('name')
                            ->label(__('Speaker Name'))
                            ->required()
                            ->maxLength(255),
                        Select::make('gender')
                            ->label(__('Gender'))
                            ->options(Gender::class)
                            ->default(Gender::Male->value)
                            ->required(),
                        \Filament\Forms\Components\Toggle::make('is_freelance')
                            ->label(__('Penceramah Bebas'))
                            ->live(),
                        TextInput::make('job_title')
                            ->label(__('Job Title'))
                            ->placeholder(__('e.g., Imam, Lecturer'))
                            ->maxLength(255)
                            ->visible(fn (Get $get) => $get('is_freelance')),
                        Select::make('honorific')
                            ->label(__('Honorific'))
                            ->options(Honorific::class)
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->placeholder(__('Select honorifics')),
                        Select::make('pre_nominal')
                            ->label(__('Pre-nominal'))
                            ->options(PreNominal::class)
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->placeholder(__('Select pre-nominals')),
                        Select::make('post_nominal')
                            ->label(__('Post-nominal'))
                            ->options(PostNominal::class)
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->placeholder(__('Select post-nominals')),
                        TextInput::make('slug')
                            ->label(__('Slug'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        RichEditor::make('bio')
                            ->label(__('Biography'))
                            ->json()
                            ->columnSpanFull()
                            ->placeholder(__('Share a short biography of the speaker')),

                        Select::make('languages')
                            ->label(__('Languages'))
                            ->relationship('languages', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ])
                    ->columns(2),
                Section::make(__('Location / Base'))
                    ->relationship('address')
                    ->components([
                        Select::make('country_id')
                            ->label(__('Country'))
                            ->relationship('country', 'name')
                            ->default(132) // Malaysia
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('state_id', null);
                                $set('district_id', null);
                                $set('subdistrict_id', null);
                            }),
                        Select::make('state_id')
                            ->label(__('Negeri'))
                            ->relationship('state', 'name', fn ($query, $get) => $query->where('country_id', $get('country_id')))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('district_id', null);
                                $set('subdistrict_id', null);
                            }),
                        Select::make('district_id')
                            ->label(__('Daerah'))
                            ->relationship('district', 'name', fn ($query, $get) => $query->where('state_id', $get('state_id')))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('subdistrict_id', null)),
                        Select::make('subdistrict_id')
                            ->label(__('Daerah Kecil / Bandar / Mukim'))
                            ->relationship('subdistrict', 'name', fn ($query, $get) => $query->where('district_id', $get('district_id')))
                            ->searchable()
                            ->preload(),
                        TextInput::make('line1')
                            ->label(__('Address Line 1'))
                            ->maxLength(255),
                        TextInput::make('line2')
                            ->label(__('Address Line 2'))
                            ->maxLength(255),
                        TextInput::make('postcode')
                            ->label(__('Postcode'))
                            ->maxLength(16),
                    ])
                    ->columns(2),
                Section::make(__('Education'))
                    ->components([
                        \Filament\Forms\Components\Repeater::make('qualifications')
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
                            ->columns(2)
                            ->itemLabel(fn (array $state): string => ($state['degree'] ?? '').' - '.($state['institution'] ?? '')),
                    ]),
                Section::make(__('Contact'))
                    ->components([
                        \Filament\Forms\Components\Repeater::make('contacts')
                            ->label(__('Contact Details'))
                            ->relationship()
                            ->default([])
                            ->schema([
                                Select::make('category')
                                    ->label(__('Category'))
                                    ->options(ContactCategory::class)
                                    ->required()
                                    ->live(),
                                TextInput::make('value')
                                    ->required()
                                    ->maxLength(255)
                                    ->label(fn (Get $get) => match ($get('category')) {
                                        ContactCategory::Email => __('Email Address'),
                                        ContactCategory::Phone => __('Phone Number'),
                                        ContactCategory::WhatsApp => __('WhatsApp Number'),
                                        'email' => __('Email Address'),
                                        'phone' => __('Phone Number'),
                                        'whatsapp' => __('WhatsApp Number'),
                                        default => __('Value'),
                                    })
                                    ->email(fn (Get $get): bool => in_array($get('category'), [ContactCategory::Email, ContactCategory::Email->value], true))
                                    ->tel(fn (Get $get): bool => in_array($get('category'), [ContactCategory::Phone, ContactCategory::Phone->value, ContactCategory::WhatsApp, ContactCategory::WhatsApp->value], true)),
                                Select::make('type')
                                    ->label(__('Type'))
                                    ->options(ContactType::class)
                                    ->default(ContactType::Main)
                                    ->required(),
                                \Filament\Forms\Components\Toggle::make('is_public')
                                    ->label(__('Public'))
                                    ->default(true),
                            ])
                            ->columns(4)
                            ->itemLabel(function (array $state): string {
                                $category = $state['category'] ?? null;

                                if ($category instanceof ContactCategory) {
                                    $categoryLabel = $category->getLabel();
                                } elseif (is_string($category)) {
                                    $categoryLabel = ContactCategory::tryFrom($category)?->getLabel() ?? $category;
                                } else {
                                    $categoryLabel = __('Contact');
                                }

                                return $categoryLabel.': '.($state['value'] ?? '');
                            }),
                    ]),
                Section::make(__('Media'))
                    ->components([
                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('avatar')
                            ->label(__('Avatar'))
                            ->collection('avatar')
                            ->image()
                            ->imageEditor()
                            ->circleCropper()
                            ->avatar()
                            ->conversion('thumb')
                            ->helperText(__('Speaker photo (recommended: 400x400)')),
                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('cover')
                            ->collection('cover')
                            ->label(__('Cover Image'))
                            ->image()
                            ->imageEditor()
                            ->imageAspectRatio('16:9')
                            ->automaticallyOpenImageEditorForAspectRatio()
                            ->imageEditorAspectRatioOptions(['16:9'])
                            ->automaticallyCropImagesToAspectRatio()
                            ->responsiveImages()
                            ->conversion('banner')
                            ->helperText(__('Cover featured image')),
                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('gallery')
                            ->label(__('Gallery'))
                            ->collection('gallery')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->responsiveImages()
                            ->conversion('gallery_thumb')
                            ->helperText(__('Additional images')),
                    ])
                    ->columns(2),
                Section::make(__('Social Media'))
                    ->components([
                        \Filament\Forms\Components\Repeater::make('socialMedia')
                            ->label(__('Social Media Links'))
                            ->relationship()
                            ->default([])
                            ->schema([
                                Select::make('platform')
                                    ->label(__('Platform'))
                                    ->options(\App\Enums\SocialMediaPlatform::class)
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(1),
                                TextInput::make('username')
                                    ->label(__('Username / Handle'))
                                    ->requiredWithout('url')
                                    ->placeholder('@username / https://...')
                                    ->columnSpan(1),
                                TextInput::make('url')
                                    ->label(__('URL'))
                                    ->requiredWithout('username')
                                    ->url()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->itemLabel(function (array $state): ?string {
                                $platform = $state['platform'] ?? null;

                                if ($platform instanceof SocialMediaPlatform) {
                                    return $platform->getLabel();
                                }

                                if (is_string($platform) && $platform !== '') {
                                    return SocialMediaPlatform::tryFrom($platform)?->getLabel() ?? $platform;
                                }

                                return null;
                            }),
                    ]),
                Section::make(__('Status'))
                    ->components([
                        Select::make('status')
                            ->label(__('Status'))
                            ->options([
                                'pending' => __('Pending'),
                                'verified' => __('Verified'),
                                'rejected' => __('Rejected'),
                            ])
                            ->live()
                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                if ($state === 'rejected') {
                                    $set('is_active', false);
                                }
                            })
                            ->required(),
                        \Filament\Forms\Components\Toggle::make('is_active')
                            ->label(__('Active'))
                            ->disabled(fn (Get $get): bool => $get('status') === 'rejected')
                            ->default(true),
                    ])
                    ->columns(1),
            ]);
    }
}
