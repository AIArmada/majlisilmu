<?php

namespace App\Filament\Resources\Speakers\RelationManagers;

use Filament\Actions\AttachAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InstitutionsRelationManager extends RelationManager
{
    protected static string $relationship = 'institutions';

    #[\Override]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record): ?string => $record?->id
                        ? \App\Filament\Resources\Institutions\InstitutionResource::getUrl('edit', ['record' => $record->id])
                        : null),
                TextColumn::make('position')
                    ->label('Position')
                    ->searchable(),
                \Filament\Tables\Columns\IconColumn::make('is_primary')
                    ->label('Primary')
                    ->boolean(),
                TextColumn::make('joined_at')
                    ->date()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                // CreateAction::make(), // Usually don't want to create fresh institutions from here, just attach existing
                AttachAction::make()
                    ->preloadRecordSelect()
                    ->form(fn (AttachAction $action): array => [
                        $action->getRecordSelect(),
                        TextInput::make('position')
                            ->placeholder('e.g. Imam, Guest Speaker'),
                        \Filament\Forms\Components\Toggle::make('is_primary')
                            ->label('Primary Affiliation'),
                        \Filament\Forms\Components\DatePicker::make('joined_at')
                            ->label('Joined At'),
                    ]),
            ])
            ->recordActions([
                EditAction::make()
                    ->form([
                        TextInput::make('position'),
                        \Filament\Forms\Components\Toggle::make('is_primary'),
                        \Filament\Forms\Components\DatePicker::make('joined_at'),
                    ]),
                DetachAction::make(),
                // DeleteAction::make(), // Should not delete institution, only detach
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
