<?php

namespace App\Filament\Resources\Topics\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TopicsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('category')
                    ->sortable(),
                IconColumn::make('is_official')
                    ->boolean()
                    ->label('Official'),
                TextColumn::make('slug')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category')
                    ->options([
                        'aqidah' => 'Aqidah',
                        'fiqh' => 'Fiqh',
                        'sirah' => 'Sirah',
                        'akhlak' => 'Akhlak',
                        'quran' => 'Quran',
                        'hadith' => 'Hadith',
                        'tarbiah' => 'Tarbiah',
                        'family' => 'Family',
                    ]),
                SelectFilter::make('is_official')
                    ->options([
                        '1' => 'Official',
                        '0' => 'Community',
                    ])
                    ->label('Official'),
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
