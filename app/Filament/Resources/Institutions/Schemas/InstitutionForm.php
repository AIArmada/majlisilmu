<?php

namespace App\Filament\Resources\Institutions\Schemas;

use App\Enums\InstitutionType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class InstitutionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')
                    ->components([
                        Select::make('type')
                            ->options(InstitutionType::class)
                            ->required(),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Textarea::make('description')
                            ->columnSpanFull()
                            ->maxLength(5000),
                    ])
                    ->columns(2),
                Section::make('Media')
                    ->components([
                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('logo')
                            ->collection('logo')
                            ->image()
                            ->imageEditor()
                            ->avatar()
                            ->helperText('Institution logo (recommended: 400x400)'),
                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('main')
                            ->collection('main')
                            ->label('Main Image')
                            ->image()
                            ->imageEditor()
                            ->responsiveImages()
                            ->helperText('Main image (recommended: 1200x800)'),
                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('gallery')
                            ->collection('gallery')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->columnSpanFull()
                            ->helperText('Additional images for gallery'),
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
                Section::make('Status')
                    ->components([
                        Select::make('status')
                            ->options([
                                'unverified' => 'Unverified',
                                'pending' => 'Pending',
                                'verified' => 'Verified',
                                'rejected' => 'Rejected',
                            ])
                            ->required(),
                    ])
                    ->columns(1),
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
