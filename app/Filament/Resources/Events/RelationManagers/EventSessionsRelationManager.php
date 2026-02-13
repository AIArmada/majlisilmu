<?php

namespace App\Filament\Resources\Events\RelationManagers;

use App\Enums\PrayerOffset;
use App\Enums\PrayerReference;
use App\Enums\SessionStatus;
use App\Enums\TimingMode;
use App\Models\Event;
use App\Models\EventSession;
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
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventSessionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sessions';

    #[\Override]
    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DateTimePicker::make('starts_at')
                    ->required()
                    ->seconds(false)
                    ->minutesStep(5),
                DateTimePicker::make('ends_at')
                    ->seconds(false)
                    ->minutesStep(5),
                TextInput::make('timezone')
                    ->default('Asia/Kuala_Lumpur')
                    ->required(),
                Select::make('status')
                    ->options(collect(SessionStatus::cases())->mapWithKeys(fn (SessionStatus $status): array => [
                        $status->value => $status->label(),
                    ])->all())
                    ->default(SessionStatus::Scheduled->value)
                    ->required(),
                TextInput::make('capacity')
                    ->numeric()
                    ->minValue(1),
                Select::make('timing_mode')
                    ->options(collect(TimingMode::cases())->mapWithKeys(fn (TimingMode $mode): array => [
                        $mode->value => $mode->label(),
                    ])->all())
                    ->default(TimingMode::Absolute->value)
                    ->required()
                    ->live(),
                Select::make('prayer_reference')
                    ->options(collect(PrayerReference::cases())->mapWithKeys(fn (PrayerReference $reference): array => [
                        $reference->value => $reference->label(),
                    ])->all())
                    ->visible(fn (Get $get): bool => in_array($get('timing_mode'), [TimingMode::PrayerRelative, TimingMode::PrayerRelative->value], true)),
                Select::make('prayer_offset')
                    ->options(collect(PrayerOffset::cases())->mapWithKeys(fn (PrayerOffset $offset): array => [
                        $offset->value => $offset->label(),
                    ])->all())
                    ->visible(fn (Get $get): bool => in_array($get('timing_mode'), [TimingMode::PrayerRelative, TimingMode::PrayerRelative->value], true)),
                TextInput::make('prayer_display_text')
                    ->visible(fn (Get $get): bool => in_array($get('timing_mode'), [TimingMode::PrayerRelative, TimingMode::PrayerRelative->value], true)),
            ])
            ->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('capacity')
                    ->placeholder('-'),
                IconColumn::make('is_generated')
                    ->label('Generated')
                    ->boolean(),
                TextColumn::make('recurrenceRule.frequency')
                    ->label('Rule')
                    ->placeholder('Manual'),
            ])
            ->defaultSort('starts_at')
            ->headerActions([
                CreateAction::make()
                    ->after(function (EventSession $record, EventScheduleProjectorService $projector, ModerationService $moderationService): void {
                        if (! $record->event instanceof Event) {
                            return;
                        }

                        $this->syncAfterSessionMutation($record->event, $projector, $moderationService, 'Schedule session created.');
                    }),
            ])
            ->recordActions([
                Action::make('cancel_session')
                    ->label('Cancel Session')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->action(function (EventSession $record, EventScheduleGeneratorService $generator): void {
                        $generator->cancelSession($record);
                    })
                    ->visible(fn (EventSession $record): bool => $record->status !== SessionStatus::Cancelled),
                EditAction::make()
                    ->after(function (EventSession $record, EventScheduleProjectorService $projector, ModerationService $moderationService): void {
                        if (! $record->event instanceof Event) {
                            return;
                        }

                        $this->syncAfterSessionMutation($record->event, $projector, $moderationService, 'Schedule session updated.');
                    }),
                DeleteAction::make()
                    ->action(function (EventSession $record, EventScheduleProjectorService $projector, ModerationService $moderationService): void {
                        $event = $record->event;
                        $record->delete();

                        if (! $event instanceof Event) {
                            return;
                        }

                        $this->syncAfterSessionMutation($event, $projector, $moderationService, 'Schedule session removed.');
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    protected function syncAfterSessionMutation(Event $event, EventScheduleProjectorService $projector, ModerationService $moderationService, string $note): void
    {
        $projector->project($event->fresh());

        if ($event->status instanceof Approved) {
            $moderationService->remoderate($event->fresh(), auth()->user(), $note, 'schedule_changed');
        }
    }
}
