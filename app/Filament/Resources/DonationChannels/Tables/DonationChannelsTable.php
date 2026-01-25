<?php

namespace App\Filament\Resources\DonationChannels\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DonationChannelsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('donatable.name')
                    ->label('Owner')
                    ->description(fn ($record) => class_basename($record->donatable_type))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('recipient_name')
                    ->label('Recipient')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('method_display')
                    ->label('Method')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'Bank Account' => 'info',
                        'DuitNow' => 'success',
                        'E-Wallet' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('payment_details')
                    ->label('Details')
                    ->searchable(['bank_name', 'account_number', 'duitnow_value', 'ewallet_handle']),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'verified' => 'success',
                        'unverified' => 'warning',
                        'rejected' => 'danger',
                        'inactive' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
