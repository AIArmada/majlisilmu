<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Enums\RecurrenceFrequency;
use App\Enums\ScheduleState;
use App\Enums\TimingMode;
use App\Models\Event;
use App\Models\EventRecurrenceRule;
use App\Services\EventScheduleGeneratorService;
use App\Services\EventScheduleProjectorService;
use App\Services\ModerationService;
use App\States\EventStatus\Approved;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventRecurrenceRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'recurrenceRules';

    #[\Override]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('frequency')
                    ->options(collect(RecurrenceFrequency::cases())->mapWithKeys(fn (RecurrenceFrequency $frequency): array => [
                        $frequency->value => $frequency->label(),
                    ])->all())
                    ->required()
                    ->default(RecurrenceFrequency::Weekly->value),
                TextInput::make('interval')
                    ->numeric()
                    ->default(1)
                    ->required()
                    ->minValue(1),
                CheckboxList::make('by_weekdays')
                    ->options([
                        0 => 'Sun',
                        1 => 'Mon',
                        2 => 'Tue',
                        3 => 'Wed',
                        4 => 'Thu',
                        5 => 'Fri',
                        6 => 'Sat',
                    ])
                    ->columns(4),
                TextInput::make('by_month_day')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(31),
                DatePicker::make('start_date')
                    ->required(),
                DatePicker::make('until_date')
                    ->requiredWithout('occurrence_count'),
                TextInput::make('occurrence_count')
                    ->numeric()
                    ->minValue(1)
                    ->requiredWithout('until_date'),
                TimePicker::make('starts_time')
                    ->seconds(false)
                    ->visible(fn (Get $get): bool => ! in_array($get('timing_mode'), [TimingMode::PrayerRelative, TimingMode::PrayerRelative->value], true)),
                TimePicker::make('ends_time')
                    ->seconds(false)
                    ->visible(fn (Get $get): bool => ! in_array($get('timing_mode'), [TimingMode::PrayerRelative, TimingMode::PrayerRelative->value], true)),
                TextInput::make('timezone')
                    ->default('Asia/Kuala_Lumpur')
                    ->required(),
                Select::make('timing_mode')
                    ->options(collect(TimingMode::cases())->mapWithKeys(fn (TimingMode $mode): array => [
                        $mode->value => $mode->label(),
                    ])->all())
                    ->default(TimingMode::Absolute->value)
                    ->required(),
                Select::make('prayer_reference')
                    ->options(collect(\App\Enums\PrayerReference::cases())->mapWithKeys(fn (\App\Enums\PrayerReference $reference): array => [
                        $reference->value => $reference->label(),
                    ])->all())
                    ->visible(fn (Get $get): bool => in_array($get('timing_mode'), [TimingMode::PrayerRelative, TimingMode::PrayerRelative->value], true)),
                Select::make('prayer_offset')
                    ->options(collect(\App\Enums\PrayerOffset::cases())->mapWithKeys(fn (\App\Enums\PrayerOffset $offset): array => [
                        $offset->value => $offset->label(),
                    ])->all())
                    ->visible(fn (Get $get): bool => in_array($get('timing_mode'), [TimingMode::PrayerRelative, TimingMode::PrayerRelative->value], true)),
                TextInput::make('prayer_display_text')
                    ->visible(fn (Get $get): bool => in_array($get('timing_mode'), [TimingMode::PrayerRelative, TimingMode::PrayerRelative->value], true)),
                Select::make('status')
                    ->options(collect(ScheduleState::cases())->mapWithKeys(fn (ScheduleState $state): array => [
                        $state->value => $state->label(),
                    ])->all())
                    ->default(ScheduleState::Active->value)
                    ->required(),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('frequency')
                    ->badge(),
                TextColumn::make('interval'),
                TextColumn::make('start_date')
                    ->date(),
                TextColumn::make('until_date')
                    ->date()
                    ->placeholder('-'),
                TextColumn::make('occurrence_count')
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('generated_until')
                    ->date()
                    ->placeholder('-'),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->after(function (EventRecurrenceRule $record, EventScheduleProjectorService $projector, ModerationService $moderationService): void {
                        if (! $record->event instanceof Event) {
                            return;
                        }

                        $this->syncAfterRuleMutation($record->event, $projector, $moderationService, 'Recurring schedule created.', false);
                    }),
                Action::make('pause_series')
                    ->label('Pause Series')
                    ->requiresConfirmation()
                    ->color('warning')
                    ->action(function (EventScheduleGeneratorService $generator): void {
                        /** @var \App\Models\Event $event */
                        $event = $this->getOwnerRecord();
                        $generator->pauseSeries($event);
                    }),
                Action::make('resume_series')
                    ->label('Resume Series')
                    ->requiresConfirmation()
                    ->color('success')
                    ->action(function (EventScheduleGeneratorService $generator): void {
                        /** @var \App\Models\Event $event */
                        $event = $this->getOwnerRecord();
                        $generator->resumeSeries($event);
                    }),
                Action::make('cancel_series')
                    ->label('Cancel Series')
                    ->requiresConfirmation()
                    ->color('danger')
                    ->action(function (EventScheduleGeneratorService $generator): void {
                        /** @var \App\Models\Event $event */
                        $event = $this->getOwnerRecord();
                        $generator->cancelSeries($event);
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->after(function (EventRecurrenceRule $record, EventScheduleProjectorService $projector, ModerationService $moderationService): void {
                        if (! $record->event instanceof Event) {
                            return;
                        }

                        $this->syncAfterRuleMutation($record->event, $projector, $moderationService, 'Recurring schedule updated.', false);
                    }),
                DeleteAction::make()
                    ->action(function (EventRecurrenceRule $record, EventScheduleProjectorService $projector, ModerationService $moderationService): void {
                        $event = $record->event;
                        $record->delete();

                        if (! $event instanceof Event) {
                            return;
                        }

                        $this->syncAfterRuleMutation($event, $projector, $moderationService, 'Recurring schedule removed.');
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function syncAfterRuleMutation(Event $event, EventScheduleProjectorService $projector, ModerationService $moderationService, string $note, bool $project = true): void
    {
        if ($project) {
            $projector->project($event->fresh());
        }

        if ($event->status instanceof Approved) {
            $moderationService->remoderate($event->fresh(), auth()->user(), $note, 'schedule_changed');
        }
    }
}
