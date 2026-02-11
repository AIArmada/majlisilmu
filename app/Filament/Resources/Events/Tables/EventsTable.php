<?php

namespace App\Filament\Resources\Events\Tables;

use A909M\FilamentStateFusion\Tables\Filters\StateFusionSelectFilter;
use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\TimingMode;
use App\Models\Event;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Collection;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('poster')
                    ->label('Poster')
                    ->collection('poster')
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
                TextColumn::make('event_type')
                    ->label('Type')
                    ->formatStateUsing(fn (mixed $state): string => self::formatEnumCollection($state, EventType::class))
                    ->wrap()
                    ->searchable(query: fn ($query, string $search) => $query->whereJsonContains('event_type', EventType::tryFrom($search)?->value ?? $search)),
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->description(fn (Event $record): ?string => $record->timing_mode === TimingMode::PrayerRelative ? $record->prayer_display_text : null)
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (mixed $state): string => match ((string) $state) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'needs_changes' => 'info',
                        'rejected' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (mixed $state): string => match ((string) $state) {
                        'draft' => 'Draft',
                        'pending' => 'Pending Review',
                        'needs_changes' => 'Needs Changes',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        default => (string) $state,
                    })
                    ->sortable(),
                TextColumn::make('visibility')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => self::formatEnumValue($state, EventVisibility::class))
                    ->sortable(),
                TextColumn::make('event_format')
                    ->label('Format')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => self::formatEnumValue($state, EventFormat::class))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('gender')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => self::formatEnumValue($state, EventGenderRestriction::class))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('age_group')
                    ->label('Age Group')
                    ->formatStateUsing(fn (mixed $state): string => self::formatEnumCollection($state, EventAgeGroup::class))
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
                \Filament\Tables\Columns\ToggleColumn::make('is_featured')
                    ->label('Featured'),
                \Filament\Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
                IconColumn::make('is_muslim_only')
                    ->boolean()
                    ->label('Muslim Only')
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('settings.registration_required')
                    ->boolean()
                    ->label('Reg?'),
                TextColumn::make('event_url')
                    ->label('Event URL')
                    ->url(fn (Event $record): ?string => $record->event_url)
                    ->openUrlInNewTab()
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('live_url')
                    ->label('Live URL')
                    ->url(fn (Event $record): ?string => $record->live_url)
                    ->openUrlInNewTab()
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('submitter.email')
                    ->label('Submitter')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                \Filament\Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
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
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected static function formatEnumCollection(mixed $state, string $enumClass): string
    {
        $items = $state instanceof Collection
            ? $state->all()
            : (is_array($state) ? $state : [$state]);

        return collect($items)
            ->filter(fn (mixed $value): bool => filled($value))
            ->map(fn (mixed $value): string => self::formatEnumValue($value, $enumClass))
            ->filter()
            ->implode(', ');
    }

    protected static function formatEnumValue(mixed $state, ?string $enumClass = null): string
    {
        if ($state instanceof BackedEnum) {
            return self::resolveEnumLabel($state);
        }

        if (is_string($state) && $enumClass !== null && is_subclass_of($enumClass, BackedEnum::class)) {
            $enum = $enumClass::tryFrom($state);

            if ($enum instanceof BackedEnum) {
                return self::resolveEnumLabel($enum);
            }
        }

        return is_scalar($state) ? (string) $state : '';
    }

    protected static function resolveEnumLabel(BackedEnum $enum): string
    {
        if (method_exists($enum, 'getLabel')) {
            return (string) $enum->getLabel();
        }

        if (method_exists($enum, 'label')) {
            return (string) $enum->label();
        }

        return (string) $enum->value;
    }
}
