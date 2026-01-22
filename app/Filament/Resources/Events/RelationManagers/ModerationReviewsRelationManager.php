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

class ModerationReviewsRelationManager extends RelationManager
{
    protected static string $relationship = 'moderationReviews';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('decision')
                    ->options([
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'needs_changes' => 'Needs changes',
                    ])
                    ->required(),
                TextInput::make('reason_code')
                    ->maxLength(255),
                Select::make('reviewer_id')
                    ->relationship('reviewer', 'email')
                    ->searchable()
                    ->preload(),
                Textarea::make('note')
                    ->columnSpanFull()
                    ->maxLength(2000),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('decision')
                    ->badge()
                    ->sortable(),
                TextColumn::make('reason_code')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reviewer.email')
                    ->label('Reviewer')
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
