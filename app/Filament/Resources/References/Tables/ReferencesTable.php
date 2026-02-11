<?php

namespace App\Filament\Resources\References\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReferencesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\SpatieMediaLibraryImageColumn::make('front_cover')
                    ->label('Cover')
                    ->collection('front_cover')
                    ->conversion('thumb')
                    ->square()
                    ->size(56),
                \Filament\Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('author')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->colors([
                        'primary',
                        'success' => 'kitab',
                        'warning' => 'book',
                        'info' => 'video',
                    ]),
                \Filament\Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                \Filament\Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
                \Filament\Tables\Columns\IconColumn::make('is_canonical')
                    ->boolean(),
                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'verified' => 'Verified',
                    ]),
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
