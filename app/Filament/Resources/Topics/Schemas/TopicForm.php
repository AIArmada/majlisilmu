<?php

namespace App\Filament\Resources\Topics\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TopicForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Topic')
                    ->components([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        Select::make('category')
                            ->options([
                                'aqidah' => 'Aqidah',
                                'fiqh' => 'Fiqh',
                                'sirah' => 'Sirah',
                                'akhlak' => 'Akhlak',
                                'quran' => 'Quran',
                                'hadith' => 'Hadith',
                                'tarbiah' => 'Tarbiah',
                                'family' => 'Family',
                            ])
                            ->required(),
                        Toggle::make('is_official')
                            ->label('Official topic'),
                    ])
                    ->columns(2),
            ]);
    }
}
