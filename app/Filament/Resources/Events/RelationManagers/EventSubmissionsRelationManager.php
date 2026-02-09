<?php

namespace App\Filament\Resources\Events\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                Select::make('submitted_by')
                    ->relationship('submitter', 'email')
                    ->searchable()
                    ->preload(),
                TextInput::make('submitter_name')
                    ->maxLength(255),
                Textarea::make('notes')
                    ->columnSpanFull()
                    ->maxLength(2000),
            ])
            ->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('submitter.email')
                    ->label('User')
                    ->sortable()
                    ->searchable()
                    ->url(fn ($record): ?string => $record->submitted_by ? url('/admin') : null),
                TextColumn::make('submitter_name')
                    ->label('Guest Name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Guest Email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Guest Phone')
                    ->toggleable(),
                TextColumn::make('notes')
                    ->limit(80)
                    ->wrap()
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
