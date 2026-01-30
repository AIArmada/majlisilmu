<?php

namespace App\Filament\Resources\Venues\Schemas;

use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class VenueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Details')
                    ->components([
                        Select::make('institution_id')
                            ->relationship('institution', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('type')
                            ->options([
                                'main_hall' => 'Main Hall',
                                'seminar_room' => 'Seminar Room',
                                'classroom' => 'Classroom',
                                'meeting_room' => 'Meeting Room',
                                'auditorium' => 'Auditorium',
                                'field' => 'Field',
                                'foyer' => 'Foyer',
                                'other' => 'Other',
                            ])
                            ->default('main_hall')
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
                                    ->options([
                                        'email' => 'Email',
                                        'phone' => 'Phone',
                                    ])
                                    ->required()
                                    ->live(),
                                TextInput::make('value')
                                    ->required()
                                    ->maxLength(255)
                                    ->label(fn (Get $get) => match ($get('category')) {
                                        'email' => 'Email Address',
                                        'phone' => 'Phone Number',
                                        default => 'Value',
                                    })
                                    ->email(fn (Get $get) => $get('category') === 'email')
                                    ->tel(fn (Get $get) => $get('category') === 'phone'),
                                Select::make('type')
                                    ->options([
                                        'main' => 'Main',
                                        'work' => 'Work',
                                        'personal' => 'Personal',
                                        'whatsapp' => 'WhatsApp',
                                    ])
                                    ->default('main')
                                    ->required(),
                                \Filament\Forms\Components\Toggle::make('is_public')
                                    ->label('Public')
                                    ->default(true),
                            ])
                            ->columns(4)
                            ->itemLabel(fn (array $state): ?string => ($state['category'] ?? 'Contact').': '.($state['value'] ?? '')),
                    ]),
                Section::make('Location')
                    ->relationship('address')
                    ->components([
                        Select::make('country_id')
                            ->relationship('country', 'name')
                            ->searchable()
                            ->preload()
                            ->live(),
                        Select::make('state_id')
                            ->relationship('state', 'name', fn ($query, $get) => $query->where('country_id', $get('country_id')))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->hidden(fn ($get) => ! $get('country_id')),
                        Select::make('district_id')
                            ->relationship('district', 'name', fn ($query, $get) => $query->where('state_id', $get('state_id')))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->hidden(fn ($get) => ! $get('state_id')),
                        Select::make('city_id')
                            ->relationship('city', 'name', fn ($query, $get) => $query->where('state_id', $get('state_id')))
                            ->searchable()
                            ->preload()
                            ->hidden(fn ($get) => ! $get('state_id')),
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
                            ->dehydrateStateUsing(function ($state): array {
                                return array_fill_keys($state ?? [], true);
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
                Section::make('Media')
                    ->components([
                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('main')
                            ->collection('main')
                            ->image()
                            ->imageEditor()
                            ->responsiveImages()
                            ->helperText('Main venue image (recommended: 1200x800)'),
                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('gallery')
                            ->collection('gallery')
                            ->multiple()
                            ->image()
                            ->imageEditor()
                            ->reorderable()
                            ->helperText('Additional images for gallery'),
                    ])
                    ->columns(2),
                Section::make('Social Media')
                    ->components([
                        \Filament\Forms\Components\Repeater::make('socialMedia')
                            ->relationship()
                            ->schema([
                                Select::make('platform')
                                    ->options([
                                        'facebook' => 'Facebook',
                                        'instagram' => 'Instagram',
                                        'youtube' => 'YouTube',
                                        'tiktok' => 'TikTok',
                                        'twitter' => 'X (Twitter)',
                                        'linkedin' => 'LinkedIn',
                                        'website' => 'Website',
                                        'other' => 'Other',
                                    ])
                                    ->searchable()
                                    ->required()
                                    ->columnSpan(1),
                                TextInput::make('username')
                                    ->label('Username / Handle')
                                    ->placeholder('@username')
                                    ->columnSpan(1),
                                TextInput::make('url')
                                    ->label('URL')
                                    ->url()
                                    ->required()
                                    ->columnSpanFull(),
                            ])
                            ->columns(2)
                            ->itemLabel(fn (array $state): ?string => $state['platform'] ?? null),
                    ]),
            ]);
    }
}
