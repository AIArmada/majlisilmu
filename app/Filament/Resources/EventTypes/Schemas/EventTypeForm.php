<?php

namespace App\Filament\Resources\EventTypes\Schemas;

use Filament\Schemas\Schema;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;

class EventTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('General Information')
                    ->components([
                        Select::make('parent_id')
                            ->label('Parent Category')
                            ->relationship('parent', 'name', fn($query) => $query->whereNull('parent_id'))
                            ->searchable()
                            ->preload()
                            ->placeholder('None (Root Category)'),
                        TextInput::make('name')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($state, Set $set) => $set('slug', Str::slug($state))),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true),
                        Select::make('status')
                            ->options([
                                'verified' => 'Verified',
                                'pending' => 'Pending',
                                'rejected' => 'Rejected',
                            ])
                            ->default('verified')
                            ->required(),
                        Toggle::make('is_active')
                            ->default(true),
                        TextInput::make('order_column')
                            ->numeric()
                            ->default(0),
                    ])
                    ->columns(2),
            ]);
    }
}
