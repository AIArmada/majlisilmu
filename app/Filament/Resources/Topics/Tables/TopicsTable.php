<?php

namespace App\Filament\Resources\Topics\Tables;

use App\Models\Topic;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TopicsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (Topic $record) => $record->parent?->name),
                TextColumn::make('parent.name')
                    ->label('Parent')
                    ->placeholder('Root')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('fullPath')
                    ->label('Full Path')
                    ->state(fn (Topic $record) => $record->getFullPath())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('children_count')
                    ->label('Children')
                    ->counts('children')
                    ->sortable(),
                IconColumn::make('is_official')
                    ->boolean()
                    ->label('Official'),
                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('slug')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('parent_id')
                    ->label('Parent Topic')
                    ->relationship('parent', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('All Topics'),
                TernaryFilter::make('is_root')
                    ->label('Root Topics Only')
                    ->queries(
                        true: fn ($query) => $query->whereNull('parent_id'),
                        false: fn ($query) => $query->whereNotNull('parent_id'),
                    ),
                TernaryFilter::make('is_official')
                    ->label('Official')
                    ->placeholder('All')
                    ->trueLabel('Official Only')
                    ->falseLabel('Community Only'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
