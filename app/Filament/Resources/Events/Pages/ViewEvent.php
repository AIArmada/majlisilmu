<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Services\ModerationService;
use App\States\EventStatus\Approved;
use App\States\EventStatus\NeedsChanges;
use App\States\EventStatus\Pending;
use App\States\EventStatus\Rejected;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class ViewEvent extends ViewRecord
{
    protected static string $resource = EventResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            $this->getApproveAction(),
            $this->getRequestChangesAction(),
            $this->getRejectAction(),
            $this->getReconsiderAction(),
            $this->getRemoderateAction(),
            $this->getRevertToDraftAction(),
            EditAction::make(),
        ];
    }

    protected function getApproveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve Event')
            ->modalDescription('Are you sure you want to approve this event? It will be published and made searchable.')
            ->schema([
                Textarea::make('note')
                    ->label('Note (optional)')
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (array $data, ModerationService $service): void {
                $service->approve($this->getRecord(), auth()->user(), $data['note'] ?? null);

                Notification::make()
                    ->title('Event approved')
                    ->success()
                    ->send();

                $this->refreshFormData(['status', 'published_at']);
            })
            ->visible(fn (): bool => $this->canModerate() && $this->getRecord()->status instanceof Pending);
    }

    protected function getRequestChangesAction(): Action
    {
        return Action::make('request_changes')
            ->label('Request Changes')
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->color('warning')
            ->modalHeading('Request Changes')
            ->modalDescription('Specify what changes the submitter needs to make.')
            ->schema([
                Select::make('reason_code')
                    ->label('Reason')
                    ->options(self::getReasonCodeOptions())
                    ->required(),
                Textarea::make('note')
                    ->label('Details for Submitter')
                    ->required()
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (array $data, ModerationService $service): void {
                $service->requestChanges(
                    $this->getRecord(),
                    auth()->user(),
                    $data['reason_code'],
                    $data['note']
                );

                Notification::make()
                    ->title('Changes requested')
                    ->warning()
                    ->send();

                $this->refreshFormData(['status']);
            })
            ->visible(fn (): bool => $this->canModerate() && $this->getRecord()->status instanceof Pending);
    }

    protected function getRejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->modalHeading('Reject Event')
            ->modalDescription('This event will be rejected and removed from search.')
            ->schema([
                Select::make('reason_code')
                    ->label('Reason')
                    ->options(self::getReasonCodeOptions())
                    ->required(),
                Textarea::make('note')
                    ->label('Note to Submitter')
                    ->required()
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (array $data, ModerationService $service): void {
                $service->reject(
                    $this->getRecord(),
                    auth()->user(),
                    $data['reason_code'],
                    $data['note']
                );

                Notification::make()
                    ->title('Event rejected')
                    ->danger()
                    ->send();

                $this->refreshFormData(['status']);
            })
            ->visible(fn (): bool => $this->canModerate() && $this->getRecord()->status instanceof Pending);
    }

    protected function getReconsiderAction(): Action
    {
        return Action::make('reconsider')
            ->label('Reconsider')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Reconsider Rejected Event')
            ->modalDescription('Move this rejected event back to pending for re-review.')
            ->schema([
                Textarea::make('note')
                    ->label('Reason for reconsideration')
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (array $data, ModerationService $service): void {
                $service->reconsider($this->getRecord(), auth()->user(), $data['note'] ?? null);

                Notification::make()
                    ->title('Event moved back to pending review')
                    ->success()
                    ->send();

                $this->refreshFormData(['status']);
            })
            ->visible(fn (): bool => $this->canModerate() && $this->getRecord()->status instanceof Rejected);
    }

    protected function getRemoderateAction(): Action
    {
        return Action::make('remoderate')
            ->label('Send for Re-moderation')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Re-moderate Event')
            ->modalDescription('Send this approved event back to pending for re-review. It will be temporarily removed from search.')
            ->schema([
                Textarea::make('note')
                    ->label('Reason for re-moderation')
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (array $data, ModerationService $service): void {
                $service->remoderate($this->getRecord(), auth()->user(), $data['note'] ?? null);

                Notification::make()
                    ->title('Event sent for re-moderation')
                    ->warning()
                    ->send();

                $this->refreshFormData(['status', 'published_at']);
            })
            ->visible(fn (): bool => $this->canModerate() && $this->getRecord()->status instanceof Approved);
    }

    protected function getRevertToDraftAction(): Action
    {
        return Action::make('revert_to_draft')
            ->label('Revert to Draft')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Revert to Draft')
            ->modalDescription('Move this event back to draft status.')
            ->schema([
                Textarea::make('note')
                    ->label('Reason (optional)')
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (array $data, ModerationService $service): void {
                $service->revertToDraft($this->getRecord(), auth()->user(), $data['note'] ?? null);

                Notification::make()
                    ->title('Event reverted to draft')
                    ->send();

                $this->refreshFormData(['status', 'published_at']);
            })
            ->visible(fn (): bool => $this->canModerate() && (
                $this->getRecord()->status instanceof Rejected
                || $this->getRecord()->status instanceof NeedsChanges
            ));
    }

    protected function canModerate(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'moderator']) ?? false;
    }

    /**
     * @return array<string, string>
     */
    protected static function getReasonCodeOptions(): array
    {
        return [
            'incomplete_info' => 'Incomplete Information',
            'duplicate' => 'Duplicate Event',
            'inappropriate' => 'Inappropriate Content',
            'spam' => 'Spam',
            'wrong_category' => 'Wrong Category',
            'inaccurate_details' => 'Inaccurate Details',
            'missing_speaker' => 'Missing Speaker Information',
            'missing_venue' => 'Missing Venue Information',
            'other' => 'Other',
        ];
    }
}
