<?php

namespace App\Filament\Resources\Venues\Schemas;

use App\Enums\ContactCategory;
use App\Enums\ContactType;
use App\Enums\VenueType;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class VenueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Details')
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('type')
                            ->options(VenueType::class)
                            ->required(),
                        Select::make('status')
                            ->options([
                                'unverified' => 'Unverified',
                                'pending' => 'Pending',
                                'verified' => 'Verified',
                                'rejected' => 'Rejected',
                            ])
                            ->required()
                            ->default('verified'),
                        \Filament\Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                    ])
                    ->columns(2),
                Section::make('Contact')
                    ->components([
                        \Filament\Forms\Components\Repeater::make('contacts')
                            ->relationship()
                            ->schema([
                                Select::make('category')
                                    ->options(ContactCategory::class)
                                    ->required()
                                    ->live(),
                                TextInput::make('value')
                                    ->required()
                                    ->maxLength(255)
                                    ->label(fn (Get $get) => match ($get('category')) {
                                        ContactCategory::Email => 'Email Address',
                                        ContactCategory::Phone => 'Phone Number',
                                        ContactCategory::WhatsApp => 'WhatsApp Number',
                                        'email' => 'Email Address',
                                        'phone' => 'Phone Number',
                                        'whatsapp' => 'WhatsApp Number',
                                        default => 'Value',
                                    })
                                    ->email(fn (Get $get): bool => in_array($get('category'), [ContactCategory::Email, ContactCategory::Email->value], true))
                                    ->tel(fn (Get $get): bool => in_array($get('category'), [ContactCategory::Phone, ContactCategory::Phone->value, ContactCategory::WhatsApp, ContactCategory::WhatsApp->value], true)),
                                Select::make('type')
                                    ->options(ContactType::class)
                                    ->default(ContactType::Main)
                                    ->required(),
                                \Filament\Forms\Components\Toggle::make('is_public')
                                    ->label('Public')
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
                                    $categoryLabel = 'Contact';
                                }

                                return $categoryLabel.': '.($state['value'] ?? '');
                            }),
                    ]),
                Section::make('Location')
                    ->relationship('address')
                    ->components([
                        Select::make('country_id')
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
                            ->label('State')
                            ->relationship('state', 'name', fn ($query, $get) => $query->where('country_id', $get('country_id')))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('district_id', null);
                                $set('subdistrict_id', null);
                            }),
                        Select::make('district_id')
                            ->label('District')
                            ->relationship('district', 'name', fn ($query, $get) => $query->where('state_id', $get('state_id')))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('subdistrict_id', null)),
                        Select::make('subdistrict_id')
                            ->label('Subdistrict / Mukim')
                            ->relationship('subdistrict', 'name', fn ($query, $get) => $query->where('district_id', $get('district_id')))
                            ->searchable()
                            ->preload(),
                        TextInput::make('line1')
                            ->maxLength(255),
                        TextInput::make('line2')
                            ->maxLength(255),
                        TextInput::make('postcode')
                            ->maxLength(16),
                        TextInput::make('lat')
                            ->numeric()
                            ->minValue(-90)
                            ->maxValue(90),
                        TextInput::make('lng')
                            ->numeric()
                            ->minValue(-180)
                            ->maxValue(180),
                        TextInput::make('google_maps_url')
                            ->label('Google Maps URL')
                            ->url()
                            ->maxLength(500)
                            ->helperText('Paste the full Google Maps link from your browser'),
                        TextInput::make('google_place_id')
                            ->label('Google Place ID')
                            ->maxLength(255)
                            ->helperText('Optional: For advanced integrations'),
                        TextInput::make('waze_url')
                            ->label('Waze URL')
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make('Facilities')
                    ->components([
                        CheckboxList::make('facilities')
                            ->options([
                                'parking' => 'Parking',
                                'oku' => 'OKU Access',
                                'women_section' => 'Women Section',
                                'ablution_area' => 'Ablution Area',
                            ])
                            ->columns(2)
                            ->afterStateHydrated(function (CheckboxList $component, $state): void {
                                if (! is_array($state)) {
                                    return;
                                }

                                $component->state(array_keys(array_filter($state)));
                            })
                            ->dehydrateStateUsing(fn ($state): array => array_fill_keys($state ?? [], true))
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
                Section::make('Media')
                    ->components([
                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('cover')
                            ->collection('cover')
                            ->image()
                            ->imageEditor()
                            ->imageAspectRatio('16:9')
                            ->automaticallyOpenImageEditorForAspectRatio()
                            ->imageEditorAspectRatioOptions(['16:9'])
                            ->automaticallyCropImagesToAspectRatio()
                            ->responsiveImages()
                            ->conversion('banner')
                            ->helperText('Cover venue image (recommended: 1200x675)'),
                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('gallery')
                            ->collection('gallery')
                            ->multiple()
                            ->image()
                            ->imageEditor()
                            ->reorderable()
                            ->responsiveImages()
                            ->conversion('thumb')
                            ->helperText('Additional images for gallery'),
                    ])
                    ->columns(2),
                Section::make('Social Media')
                    ->components([
                        \Filament\Forms\Components\Repeater::make('socialMedia')
                            ->relationship()
                            ->schema([
                                Select::make('platform')
                                    ->options(\App\Enums\SocialMediaPlatform::class)
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(1),
                                TextInput::make('username')
                                    ->label('Username / Handle')
                                    ->requiredWithout('url')
                                    ->placeholder('@username / https://...')
                                    ->columnSpan(1),
                                TextInput::make('url')
                                    ->label('URL')
                                    ->requiredWithout('username')
                                    ->url()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->itemLabel(fn (array $state): ?string => $state['platform'] instanceof \App\Enums\SocialMediaPlatform
                                ? $state['platform']->getLabel()
                                : ($state['platform'] ?? null)),
                    ]),
            ]);
    }
}
