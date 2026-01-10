<?php

namespace App\Filament\Resources\Series\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SeriesForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Series')
                    ->components([
                        Select::make('institution_id')
                            ->relationship('institution', 'name')
                            ->searchable()
                            ->preload(),
                        Select::make('venue_id')
                            ->relationship('venue', 'name')
                            ->searchable()
                            ->preload(),
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
                        Select::make('visibility')
                            ->options([
                                'public' => 'Public',
                                'unlisted' => 'Unlisted',
                                'private' => 'Private',
                            ])
                            ->required(),
                        Select::make('default_language')
                            ->options([
                                'bm' => 'Bahasa Melayu',
                                'en' => 'English',
                                'ar' => 'Arabic',
                            ])
                            ->label('Default language'),
                        Select::make('default_audience')
                            ->options([
                                'general' => 'General',
                                'youth' => 'Youth',
                                'muslimah' => 'Muslimah',
                                'family' => 'Family',
                            ])
                            ->label('Default audience'),
                    ])
                    ->columns(2),
            ]);
    }
}
