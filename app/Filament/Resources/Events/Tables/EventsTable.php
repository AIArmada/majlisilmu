<?php

namespace App\Filament\Resources\Events\Tables;

use A909M\FilamentStateFusion\Tables\Columns\StateFusionSelectColumn;
use A909M\FilamentStateFusion\Tables\Filters\StateFusionSelectFilter;
use App\Enums\TimingMode;
use App\Models\Event;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('institution.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('event_type')
                    ->label('Type')
                    ->badge()
                    ->sortable()
                    ->searchable(),
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->description(fn (Event $record) => $record->timing_mode === TimingMode::PrayerRelative->value ? $record->prayer_display_text : null)
                    ->sortable(),
                StateFusionSelectColumn::make('status')
                    ->sortable(),
                TextColumn::make('visibility')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'success',
                        'unlisted' => 'warning',
                        'private' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),
                \Filament\Tables\Columns\ToggleColumn::make('is_featured')
                    ->label('Featured'),
                IconColumn::make('is_muslim_only')
                    ->boolean()
                    ->label('Muslim Only')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('registration_required')
                    ->boolean()
                    ->label('Reg?'),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                StateFusionSelectFilter::make('status'),
                SelectFilter::make('visibility')
                    ->options([
                        'public' => 'Public',
                        'unlisted' => 'Unlisted',
                        'private' => 'Private',
                    ]),
                SelectFilter::make('institution')
                    ->relationship('institution', 'name'),
                SelectFilter::make('event_type')
                    ->label('Event Type')
                    ->options(\App\Enums\EventType::class)
                    ->query(
                        fn (\Illuminate\Database\Eloquent\Builder $query, array $data) => $query
                            ->when(
                                $data['value'],
                                fn ($q, $value) => $q->whereJsonContains('event_type', $value)
                            )
                    ),
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
