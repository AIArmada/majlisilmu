<?php

namespace App\Filament\Resources\Topics\Schemas;

use App\Models\Topic;
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
                        Select::make('parent_id')
                            ->label('Parent Topic')
                            ->relationship(
                                name: 'parent',
                                titleAttribute: 'name',
                            )
                            ->getOptionLabelFromRecordUsing(fn (Topic $record) => $record->getFullPath())
                            ->searchable()
                            ->preload()
                            ->placeholder('None (Root Topic)')
                            ->helperText('Leave empty to create a root-level topic.'),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('sort_order')
                            ->label('Sort Order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower numbers appear first within the same parent.'),
                        Toggle::make('is_official')
                            ->label('Official topic')
                            ->helperText('Official topics are curated by the platform.'),
                    ])
                    ->columns(2),
            ]);
    }
}
