<?php

namespace App\Filament\Resources\Speakers\Pages;

use App\Filament\Resources\Speakers\SpeakerResource;
use App\Models\Speaker;
use App\Models\User;
use App\Support\Submission\PublicSubmissionLockService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditSpeaker extends EditRecord
{
    protected static string $resource = SpeakerResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            $this->getLockPublicSubmissionAction(),
            $this->getUnlockPublicSubmissionAction(),
            DeleteAction::make(),
        ];
    }

    protected function getLockPublicSubmissionAction(): Action
    {
        return Action::make('lock_public_submission')
            ->label('Lock Public Submission')
            ->icon(Heroicon::OutlinedLockClosed)
            ->color('warning')
            ->requiresConfirmation()
            ->schema([
                Textarea::make('reason')
                    ->label('Lock Reason (optional)')
                    ->rows(3)
                    ->maxLength(2000),
            ])
            ->tooltip(fn (): ?string => $this->lockActionTooltip())
            ->disabled(fn (): bool => ! $this->lockEligibility()->eligible)
            ->visible(fn (): bool => $this->canManagePublicSubmissionLock() && $this->speakerRecord()->allow_public_event_submission)
            ->action(function (array $data, PublicSubmissionLockService $lockService): void {
                $actor = $this->currentUser();

                if (! $actor instanceof User) {
                    abort(403);
                }

                $lockService->lockSpeaker($this->speakerRecord(), $actor, $data['reason'] ?? null);

                Notification::make()
                    ->title('Public submission locked for this speaker.')
                    ->success()
                    ->send();

                $this->refreshFormData([
                    'allow_public_event_submission',
                    'public_submission_locked_at',
                    'public_submission_locked_by',
                    'public_submission_lock_reason',
                ]);
            });
    }

    protected function getUnlockPublicSubmissionAction(): Action
    {
        return Action::make('unlock_public_submission')
            ->label('Unlock Public Submission')
            ->icon(Heroicon::OutlinedLockOpen)
            ->color('success')
            ->requiresConfirmation()
            ->visible(fn (): bool => $this->canManagePublicSubmissionLock() && ! $this->speakerRecord()->allow_public_event_submission)
            ->action(function (PublicSubmissionLockService $lockService): void {
                $actor = $this->currentUser();

                if (! $actor instanceof User) {
                    abort(403);
                }

                $lockService->unlockSpeaker($this->speakerRecord(), $actor);

                Notification::make()
                    ->title('Public submission unlocked for this speaker.')
                    ->success()
                    ->send();

                $this->refreshFormData([
                    'allow_public_event_submission',
                ]);
            });
    }

    private function canManagePublicSubmissionLock(): bool
    {
        return $this->currentUser()?->hasAnyRole(['super_admin', 'admin', 'moderator']) ?? false;
    }

    private function lockActionTooltip(): ?string
    {
        $eligibility = $this->lockEligibility();

        if ($eligibility->eligible) {
            return null;
        }

        return implode(' ', $eligibility->reasons);
    }

    private function lockEligibility(): \App\Support\Submission\SubmissionLockEligibilityResult
    {
        return app(PublicSubmissionLockService::class)->speakerEligibility($this->speakerRecord());
    }

    private function speakerRecord(): Speaker
    {
        $record = $this->getRecord();

        if (! $record instanceof Speaker) {
            throw new \RuntimeException('Expected Filament record to be a Speaker instance.');
        }

        return $record;
    }

    private function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
