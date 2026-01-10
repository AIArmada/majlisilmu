<?php

namespace App\Filament\Resources\Events\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventSubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'submissions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('source')
                    ->options([
                        'institution' => 'Institution',
                        'speaker' => 'Speaker',
                        'public' => 'Public',
                        'import' => 'Import',
                    ])
                    ->required(),
                TextInput::make('submitter_name')
                    ->maxLength(255),
                TextInput::make('submitter_contact')
                    ->maxLength(255),
                Select::make('submitted_by')
                    ->relationship('submitter', 'email')
                    ->searchable()
                    ->preload(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('source')
                    ->badge()
                    ->sortable(),
                TextColumn::make('submitter_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('submitter_contact')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('submitter.email')
                    ->label('Submitter')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
