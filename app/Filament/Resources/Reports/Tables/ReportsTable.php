<?php

namespace App\Filament\Resources\Reports\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ReportsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('evidence')
                    ->label('Evidence')
                    ->collection('evidence')
                    ->conversion('thumb')
                    ->square()
                    ->size(52),
                TextColumn::make('entity_type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('category')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('reporter.email')
                    ->label('Reporter')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('handler.email')
                    ->label('Handled by')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('entity_type')
                    ->options([
                        'event' => 'Event',
                        'institution' => 'Institution',
                        'speaker' => 'Speaker',
                        'donation_channel' => 'Donation Channel',
                    ]),
                SelectFilter::make('category')
                    ->options([
                        'wrong_info' => 'Wrong info',
                        'cancelled_not_updated' => 'Cancelled not updated',
                        'fake_speaker' => 'Fake speaker',
                        'inappropriate_content' => 'Inappropriate content',
                        'donation_scam' => 'Donation channel scam',
                        'other' => 'Other',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'open' => 'Open',
                        'triaged' => 'Triaged',
                        'resolved' => 'Resolved',
                        'dismissed' => 'Dismissed',
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
