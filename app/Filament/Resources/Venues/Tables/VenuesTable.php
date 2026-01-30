<?php

namespace App\Filament\Resources\Venues\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class VenuesTable
{
    public static function configure(Table $table): Table
    {
        // Configuration for Venues Table
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('institution.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'main_hall' => 'Main Hall',
                        'seminar_room' => 'Seminar Room',
                        'classroom' => 'Classroom',
                        'meeting_room' => 'Meeting Room',
                        'auditorium' => 'Auditorium',
                        'field' => 'Field',
                        'foyer' => 'Foyer',
                        'other' => 'Other',
                        default => $state,
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'verified' => 'success',
                        'pending' => 'warning',
                        'rejected' => 'danger',
                        'unverified' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('address.state.name')
                    ->label('State')
                    ->sortable(),
                TextColumn::make('address.district.name')
                    ->label('District')
                    ->sortable(),
                TextColumn::make('address.city.name')
                    ->label('City')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('institution')
                    ->relationship('institution', 'name'),
                SelectFilter::make('type')
                    ->options([
                        'main_hall' => 'Main Hall',
                        'seminar_room' => 'Seminar Room',
                        'classroom' => 'Classroom',
                        'meeting_room' => 'Meeting Room',
                        'auditorium' => 'Auditorium',
                        'field' => 'Field',
                        'foyer' => 'Foyer',
                        'other' => 'Other',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'unverified' => 'Unverified',
                        'pending' => 'Pending',
                        'verified' => 'Verified',
                        'rejected' => 'Rejected',
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
