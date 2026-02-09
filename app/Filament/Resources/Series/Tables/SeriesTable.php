<?php

namespace App\Filament\Resources\Series\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SeriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('cover')
                    ->label('Cover')
                    ->collection('cover')
                    ->conversion('thumb')
                    ->square()
                    ->size(56),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('institution.name')
                    ->sortable()
                    ->searchable()
                    ->url(fn ($record): ?string => $record->institution?->id
                        ? \App\Filament\Resources\Institutions\InstitutionResource::getUrl('edit', ['record' => $record->institution->id])
                        : null),
                TextColumn::make('venue.name')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->url(fn ($record): ?string => $record->venue?->id
                        ? \App\Filament\Resources\Venues\VenueResource::getUrl('edit', ['record' => $record->venue->id])
                        : null),
                TextColumn::make('visibility')
                    ->badge()
                    ->sortable(),
                \Filament\Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
                TextColumn::make('languages.name')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('audience')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('visibility')
                    ->options([
                        'public' => 'Public',
                        'unlisted' => 'Unlisted',
                        'private' => 'Private',
                    ]),
                SelectFilter::make('institution')
                    ->relationship('institution', 'name'),
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
