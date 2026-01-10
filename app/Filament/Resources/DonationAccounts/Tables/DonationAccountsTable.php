<?php

namespace App\Filament\Resources\DonationAccounts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DonationAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('recipient_name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('label')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('institution.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('verification_status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('bank_name')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('duitnow_id')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('institution')
                    ->relationship('institution', 'name'),
                SelectFilter::make('verification_status')
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
