<?php

namespace App\Filament\Resources\Series\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
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
                        Select::make('languages')
                            ->relationship('languages', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->label('Languages'),
                        Select::make('audience')
                            ->options([
                                'Umum' => 'Umum',
                                'Belia' => 'Belia',
                                'Muslimah' => 'Muslimah',
                                'Keluarga' => 'Keluarga',
                                'Pelajar IPT' => 'Pelajar IPT',
                                'Profesional' => 'Profesional',
                            ])
                            ->label('Audience'),
                    ])
                    ->columns(2),
                Section::make('Media')
                    ->components([
                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('cover')
                            ->collection('cover')
                            ->image()
                            ->imageEditor()
                            ->columnSpanFull(),
                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('gallery')
                            ->collection('gallery')
                            ->multiple()
                            ->reorderable()
                            ->image()
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }
}
