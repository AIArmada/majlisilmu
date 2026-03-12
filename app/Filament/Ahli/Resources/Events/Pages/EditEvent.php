<?php

namespace App\Filament\Ahli\Resources\Events\Pages;

use App\Enums\RegistrationMode;
use App\Enums\TagType;
use App\Filament\Ahli\Resources\Events\EventResource;
use App\Models\Event;
use App\Models\Tag;
use App\Models\User;
use App\Services\ModerationService;
use App\States\EventStatus\Approved;
use App\States\EventStatus\Draft;
use App\States\EventStatus\NeedsChanges;
use App\States\EventStatus\Pending;
use App\States\EventStatus\Rejected;
use App\Support\Events\AdminEventTimeMapper;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    #[\Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $event = $this->eventRecord();
        $event->loadMissing(['languages:id', 'tags:id,type']);

        $data['languages'] = $event->languages->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $data['domain_tags'] = $this->getTagIdsByType(TagType::Domain);
        $data['discipline_tags'] = $this->getTagIdsByType(TagType::Discipline);
        $data['source_tags'] = $this->getTagIdsByType(TagType::Source);
        $data['issue_tags'] = $this->getTagIdsByType(TagType::Issue);
        $data['registration_mode'] = $this->resolveRegistrationMode($event)->value;

        $event->loadMissing(['keyPeople']);

        $data['speakers'] = $event->keyPeople
            ->filter(fn ($p) => $p->role === \App\Enums\EventParticipantRole::Speaker)
            ->pluck('speaker_id')
            ->filter()
            ->values()
            ->all();

        $data['other_key_people'] = $event->keyPeople
            ->filter(fn ($p) => $p->role !== \App\Enums\EventParticipantRole::Speaker)
            ->map(fn ($p) => [
                'role' => $p->role instanceof \App\Enums\EventParticipantRole ? $p->role->value : $p->role,
                'speaker_id' => $p->speaker_id,
                'name' => $p->name,
                'is_public' => $p->is_public,
                'notes' => $p->notes,
            ])
            ->values()
            ->all();

        return AdminEventTimeMapper::injectFormTimeFields($data);
    }

    #[\Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = AdminEventTimeMapper::normalizeForPersistence($data);

        if (! $this->currentUser()?->hasApplicationAdminAccess()) {
            unset($data['is_featured']);
        }

        unset(
            $data['escalated_at'],
            $data['languages'],
            $data['domain_tags'],
            $data['discipline_tags'],
            $data['source_tags'],
            $data['issue_tags'],
            $data['registration_mode'],
            $data['speakers'],
            $data['other_key_people'],
        );

        return $data;
    }

    protected function afterSave(): void
    {
        $event = $this->eventRecord();
        $requestedRegistrationMode = (string) ($this->form->getState()['registration_mode'] ?? RegistrationMode::Event->value);
        $currentRegistrationMode = $this->resolveRegistrationMode($event)->value;
        $modeToPersist = $requestedRegistrationMode;

        if ($event->registrations()->exists() && $requestedRegistrationMode !== $currentRegistrationMode) {
            $modeToPersist = $currentRegistrationMode;
        }

        $event->settings()->updateOrCreate(
            ['event_id' => $event->id],
            ['registration_mode' => $modeToPersist]
        );

        $this->syncRelationState($event, $this->form->getState());
    }

    /**
     * @param  array<string, mixed>  $state
     */
    protected function syncRelationState(Event $event, array $state): void
    {
        $rawLanguageIds = is_array($state['languages'] ?? null) ? $state['languages'] : [];

        $languageIds = collect($rawLanguageIds)
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->values()
            ->all();

        $event->syncLanguages($languageIds);

        $domainTagIds = is_array($state['domain_tags'] ?? null) ? $state['domain_tags'] : [];
        $disciplineTagIds = is_array($state['discipline_tags'] ?? null) ? $state['discipline_tags'] : [];
        $sourceTagIds = is_array($state['source_tags'] ?? null) ? $state['source_tags'] : [];
        $issueTagIds = is_array($state['issue_tags'] ?? null) ? $state['issue_tags'] : [];

        $tagIds = collect(array_merge($domainTagIds, $disciplineTagIds, $sourceTagIds, $issueTagIds))
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        $tags = Tag::query()->whereKey($tagIds)->get();

        $event->syncTags($tags);
    }

    /**
     * @return list<string>
     */
    protected function getTagIdsByType(TagType $type): array
    {
        return $this->eventRecord()->tags
            ->where('type', $type->value)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    protected function resolveRegistrationMode(Event $event): RegistrationMode
    {
        $rawMode = $event->settings?->registration_mode;

        if ($rawMode instanceof RegistrationMode) {
            return $rawMode;
        }

        if (is_string($rawMode)) {
            return RegistrationMode::tryFrom($rawMode) ?? RegistrationMode::Event;
        }

        return RegistrationMode::Event;
    }

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('add_child_event')
                ->label('Add Child Event')
                ->icon(Heroicon::OutlinedPlus)
                ->url(fn (): string => route('submit-event.create', ['parent' => $this->eventRecord()->getKey()]))
                ->visible(fn (): bool => $this->eventRecord()->isParentProgram()),
            $this->getSubmitForReviewAction(),
            $this->getApproveAction(),
            $this->getRejectAction(),
            $this->getReconsiderAction(),
            $this->getRemoderateAction(),
            $this->getRevertToDraftAction(),
            Action::make('view_public')
                ->label('View Public Page')
                ->icon(Heroicon::OutlinedEye)
                ->url(fn (): string => route('events.show', $this->eventRecord()))
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }

    protected function getSubmitForReviewAction(): Action
    {
        return Action::make('submit_for_review')
            ->label('Submit for Review')
            ->icon('heroicon-o-paper-airplane')
            ->color('warning')
            ->requiresConfirmation()
            ->modalHeading('Submit Event for Review')
            ->modalDescription('Move this submitted draft event to pending so it can be reviewed.')
            ->action(function (ModerationService $service): void {
                abort_unless($this->canSubmitSubmittedDraftForReview(), 403);

                $this->forModerationHierarchy(fn (Event $event) => $service->submitForModeration($event));

                $this->eventRecord()->refresh();
                $this->refreshFormData(['status']);

                Notification::make()
                    ->title('Event submitted for review')
                    ->success()
                    ->send();
            })
            ->visible(fn (): bool => $this->canSubmitSubmittedDraftForReview());
    }

    protected function getApproveAction(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Approve Event')
            ->modalDescription('Approve this public-submitted event for your institution or speaker.')
            ->schema([
                Textarea::make('note')
                    ->label('Note (optional)')
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->action(function (array $data, ModerationService $service): void {
                abort_unless($this->canApproveSubmittedEvent(), 403);

                $this->forModerationHierarchy(fn (Event $event) => $service->approve($event, $this->currentUser(), $data['note'] ?? null));

                $this->eventRecord()->refresh();
                $this->refreshFormData(['status']);

                Notification::make()
                    ->title('Event approved')
                    ->success()
                    ->send();
            })
            ->visible(fn (): bool => $this->canApproveSubmittedEvent() && $this->eventRecord()->status instanceof Pending);
    }

    protected function getRejectAction(): Action
    {
        return Action::make('reject')
            ->label('Reject')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->modalHeading('Reject Event')
            ->modalDescription('This event will be rejected. The submitter will be notified.')
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
                abort_unless($this->canApproveSubmittedEvent(), 403);

                $this->forModerationHierarchy(fn (Event $event) => $service->reject(
                    $event,
                    $this->currentUser(),
                    $data['reason_code'],
                    $data['note']
                ));

                Notification::make()
                    ->title('Event rejected')
                    ->danger()
                    ->send();

                $this->eventRecord()->refresh();
                $this->refreshFormData(['status']);
            })
            ->visible(fn (): bool => $this->canApproveSubmittedEvent() && $this->eventRecord()->status instanceof Pending);
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
                abort_unless($this->canApproveSubmittedEvent(), 403);

                $this->forModerationHierarchy(fn (Event $event) => $service->reconsider($event, $this->currentUser(), $data['note'] ?? null));

                Notification::make()
                    ->title('Event moved back to pending review')
                    ->success()
                    ->send();

                $this->eventRecord()->refresh();
                $this->refreshFormData(['status']);
            })
            ->visible(fn (): bool => $this->canApproveSubmittedEvent() && $this->eventRecord()->status instanceof Rejected);
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
                abort_unless($this->canApproveSubmittedEvent(), 403);

                $this->forModerationHierarchy(fn (Event $event) => $service->remoderate($event, $this->currentUser(), $data['note'] ?? null));

                Notification::make()
                    ->title('Event sent for re-moderation')
                    ->warning()
                    ->send();

                $this->eventRecord()->refresh();
                $this->refreshFormData(['status']);
            })
            ->visible(fn (): bool => $this->canApproveSubmittedEvent() && $this->eventRecord()->status instanceof Approved);
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
                abort_unless($this->canApproveSubmittedEvent(), 403);

                $this->forModerationHierarchy(fn (Event $event) => $service->revertToDraft($event, $this->currentUser(), $data['note'] ?? null));

                Notification::make()
                    ->title('Event reverted to draft')
                    ->send();

                $this->eventRecord()->refresh();
                $this->refreshFormData(['status']);
            })
            ->visible(fn (): bool => $this->canApproveSubmittedEvent() && (
                $this->eventRecord()->status instanceof Rejected
                || $this->eventRecord()->status instanceof NeedsChanges
            ));
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

    protected function eventRecord(): Event
    {
        $record = $this->getRecord();

        if (! $record instanceof Event) {
            throw new \RuntimeException('Expected Filament record to be an Event instance.');
        }

        return $record;
    }

    protected function canApproveSubmittedEvent(): bool
    {
        $user = $this->currentUser();

        return $user instanceof User && $user->can('approve', $this->eventRecord());
    }

    protected function canSubmitSubmittedDraftForReview(): bool
    {
        $user = $this->currentUser();
        $event = $this->eventRecord();

        if (! $user instanceof User || ! $event->status instanceof Draft) {
            return false;
        }

        if (! $event->submissions()->exists()) {
            return false;
        }

        if ($user->hasAnyRole(['super_admin', 'moderator'])) {
            return true;
        }

        return $event->userHasScopedEventPermission($user, 'event.approve', includeEventScope: false);
    }

    private function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }

    /**
     * @param  callable(Event): void  $callback
     */
    private function forModerationHierarchy(callable $callback): void
    {
        $event = $this->eventRecord()->loadMissing('childEvents');

        $callback($event);

        if (! $event->isParentProgram()) {
            return;
        }

        foreach ($event->childEvents as $childEvent) {
            $callback($childEvent);
        }
    }
}
