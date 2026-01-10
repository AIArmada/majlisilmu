<?php

namespace App\Filament\Resources\Institutions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
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
                            ->options([
                                'masjid' => 'Masjid',
                                'surau' => 'Surau',
                                'others' => 'Others',
                            ])
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
                Section::make('Contact')
                    ->components([
                        TextInput::make('phone')
                            ->tel()
                            ->maxLength(50),
                        TextInput::make('email')
                            ->email()
                            ->maxLength(255),
                        TextInput::make('website_url')
                            ->url()
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make('Location')
                    ->components([
                        Select::make('state_id')
                            ->relationship('state', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('district_id')
                            ->relationship('district', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('address_line1')
                            ->maxLength(255),
                        TextInput::make('address_line2')
                            ->maxLength(255),
                        TextInput::make('postcode')
                            ->maxLength(16),
                        TextInput::make('city')
                            ->maxLength(255),
                        TextInput::make('lat')
                            ->numeric()
                            ->minValue(-90)
                            ->maxValue(90),
                        TextInput::make('lng')
                            ->numeric()
                            ->minValue(-180)
                            ->maxValue(180),
                    ])
                    ->columns(2),
                Section::make('Trust & Verification')
                    ->components([
                        Select::make('verification_status')
                            ->options([
                                'unverified' => 'Unverified',
                                'pending' => 'Pending',
                                'verified' => 'Verified',
                                'rejected' => 'Rejected',
                            ])
                            ->required(),
                        TextInput::make('trust_score')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100),
                    ])
                    ->columns(2),
            ]);
    }
}
