<?php

namespace App\Filament\Resources\References\Schemas;

use Filament\Schemas\Schema;

class ReferenceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Reference Details')
                    ->components([
                        \Filament\Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255),
                        \Filament\Forms\Components\TextInput::make('author')
                            ->maxLength(255),
                        \Filament\Forms\Components\Select::make('type')
                            ->options([
                                'book' => 'Book',
                                'kitab' => 'Kitab',
                                'article' => 'Article',
                                'video' => 'Video',
                                'other' => 'Other',
                            ])
                            ->default('kitab')
                            ->required(),
                        \Filament\Forms\Components\TextInput::make('publication_year')
                            ->maxLength(255),
                        \Filament\Forms\Components\TextInput::make('publisher')
                            ->maxLength(255),
                        \Filament\Forms\Components\TextInput::make('external_link')
                            ->url()
                            ->maxLength(255),
                        \Filament\Forms\Components\Toggle::make('is_canonical')
                            ->label('Canonical / Official')
                            ->helperText('Is this a standard reference?'),
                    ])->columns(2),
                \Filament\Schemas\Components\Section::make('Imagery')
                    ->components([
                        \Filament\Forms\Components\SpatieMediaLibraryFileUpload::make('cover')
                            ->collection('cover')
                            ->image()
                            ->imageEditor()
                            ->columnSpanFull(),
                    ]),
                \Filament\Schemas\Components\Section::make('Description')
                    ->components([
                        \Filament\Forms\Components\Textarea::make('description')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
