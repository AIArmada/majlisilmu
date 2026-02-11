<?php

namespace App\Filament\Resources\Speakers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class SpeakerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Profile')
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('gender')
                            ->options([
                                'male' => 'Male',
                                'female' => 'Female',
                            ])
                            ->default('male')
                            ->required(),
                        \Filament\Forms\Components\Toggle::make('is_freelance')
                            ->label('Freelance / Independent')
                            ->live(),
                        TextInput::make('job_title')
                            ->label('Job Title / Primary Designation')
                            ->placeholder('e.g. Freelance Da\'i, Independent Researcher')
                            ->maxLength(255)
                            ->visible(fn (Get $get) => $get('is_freelance')),
                        TextInput::make('honorific')
                            ->label('Honorific')
                            ->placeholder('e.g. Dato’, Datin, Tan Sri')
                            ->maxLength(255),
                        TextInput::make('pre_nominal')
                            ->label('Pre-nominal')
                            ->placeholder('e.g. Dr, Prof, Ir, Ustaz')
                            ->maxLength(255),
                        TextInput::make('post_nominal')
                            ->label('Post-nominal')
                            ->placeholder('e.g. PhD, HONS, MSc')
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Textarea::make('bio')
                            ->columnSpanFull()
                            ->maxLength(5000),

                        // Address Components
                        Section::make('Location / Base')
                            ->relationship('address')
                            ->schema([
                                Select::make('state_id')
                                    ->label('State')
                                    ->relationship('state', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->live(),
                                Select::make('district_id')
                                    ->label('District')
                                    ->relationship('district', 'name', fn (Builder $query, Get $get) => $query->where('state_id', $get('state_id')))
                                    ->searchable()
                                    ->preload()
                                    ->live()
                                    ->visible(fn (Get $get) => $get('state_id')),
                                Select::make('subdistrict_id')
                                    ->label('Subdistrict / Mukim')
                                    ->relationship('subdistrict', 'name', fn (Builder $query, Get $get) => $query->where('district_id', $get('district_id')))
                                    ->searchable()
                                    ->preload()
                                    ->visible(fn (Get $get) => $get('district_id')),
                            ])
                            ->columns(2),

                        Select::make('languages')
                            ->relationship('languages', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ])
                    ->columns(2),
                Section::make('Education')
                    ->components([
                        \Filament\Forms\Components\Repeater::make('qualifications')
                            ->schema([
                                TextInput::make('institution')
                                    ->required(),
                                TextInput::make('degree')
                                    ->label('Degree / Level')
                                    ->required(),
                                TextInput::make('field')
                                    ->label('Field of Study'),
                                TextInput::make('year')
                                    ->numeric()
                                    ->length(4),
                            ])
                            ->columns(2)
                            ->itemLabel(fn (array $state): string => ($state['degree'] ?? '').' - '.($state['institution'] ?? '')),
                    ]),
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
                            ->itemLabel(fn (array $state): string => ($state['category'] ?? 'Contact').': '.($state['value'] ?? '')),
                    ]),
                Section::make('Media')
                    ->components([
                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('avatar')
                            ->collection('avatar')
                            ->image()
                            ->imageEditor()
                            ->avatar()
                            ->conversion('thumb')
                            ->helperText('Speaker photo (recommended: 400x400)'),
                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('main')
                            ->collection('main')
                            ->label('Main Image')
                            ->image()
                            ->imageEditor()
                            ->responsiveImages()
                            ->conversion('banner')
                            ->helperText('Main featured image'),
                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('gallery')
                            ->collection('gallery')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->responsiveImages()
                            ->conversion('gallery_thumb')
                            ->helperText('Additional images'),
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
            ]);
    }
}
