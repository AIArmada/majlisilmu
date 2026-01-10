<?php

namespace App\Filament\Resources\Events\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basics')
                    ->components([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Textarea::make('description')
                            ->columnSpanFull()
                            ->maxLength(5000),
                        Select::make('institution_id')
                            ->relationship('institution', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('venue_id')
                            ->relationship('venue', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('series_id')
                            ->relationship('series', 'title')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),
                Section::make('Schedule')
                    ->components([
                        DateTimePicker::make('starts_at')
                            ->required(),
                        DateTimePicker::make('ends_at'),
                        TextInput::make('timezone')
                            ->default('Asia/Kuala_Lumpur')
                            ->required()
                            ->maxLength(64),
                    ])
                    ->columns(2),
                Section::make('Location & Classification')
                    ->components([
                        Select::make('state_id')
                            ->relationship('state', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('district_id')
                            ->relationship('district', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('language')
                            ->options([
                                'bm' => 'Bahasa Melayu',
                                'en' => 'English',
                                'ar' => 'Arabic',
                            ]),
                        Select::make('genre')
                            ->options([
                                'kuliah' => 'Kuliah',
                                'ceramah' => 'Ceramah',
                                'tazkirah' => 'Tazkirah',
                                'forum' => 'Forum',
                            ]),
                        Select::make('audience')
                            ->options([
                                'general' => 'General',
                                'youth' => 'Youth',
                                'muslimah' => 'Muslimah',
                                'family' => 'Family',
                            ]),
                    ])
                    ->columns(2),
                Section::make('Visibility & Status')
                    ->components([
                        Select::make('visibility')
                            ->options([
                                'public' => 'Public',
                                'unlisted' => 'Unlisted',
                                'private' => 'Private',
                            ])
                            ->required()
                            ->default('public'),
                        Select::make('status')
                            ->options([
                                'draft' => 'Draft',
                                'pending' => 'Pending',
                                'approved' => 'Approved',
                                'rejected' => 'Rejected',
                                'cancelled' => 'Cancelled',
                                'postponed' => 'Postponed',
                            ])
                            ->required()
                            ->default('pending'),
                        DateTimePicker::make('published_at'),
                    ])
                    ->columns(2),
                Section::make('Media & Donation')
                    ->components([
                        TextInput::make('livestream_url')
                            ->url()
                            ->maxLength(255),
                        TextInput::make('recording_url')
                            ->url()
                            ->maxLength(255),
                        Select::make('donation_account_id')
                            ->relationship('donationAccount', 'recipient_name')
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),
                Section::make('Registration')
                    ->components([
                        Toggle::make('registration_required')
                            ->label('Registration required'),
                        TextInput::make('capacity')
                            ->numeric()
                            ->minValue(1),
                        DateTimePicker::make('registration_opens_at'),
                        DateTimePicker::make('registration_closes_at'),
                    ])
                    ->columns(2),
                Section::make('Speakers & Topics')
                    ->components([
                        Select::make('speakers')
                            ->relationship('speakers', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload(),
                        Select::make('topics')
                            ->relationship('topics', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload(),
                    ])
                    ->columns(2),
            ]);
    }
}
