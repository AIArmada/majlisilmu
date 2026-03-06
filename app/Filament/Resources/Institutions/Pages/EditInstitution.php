<?php

namespace App\Filament\Resources\Institutions\Pages;

use App\Filament\Resources\Institutions\InstitutionResource;
use App\Models\Institution;
use App\Models\User;
use App\Support\Submission\PublicSubmissionLockService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditInstitution extends EditRecord
{
    protected static string $resource = InstitutionResource::class;

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
            ->visible(fn (): bool => $this->canManagePublicSubmissionLock() && $this->institutionRecord()->allow_public_event_submission)
            ->action(function (array $data, PublicSubmissionLockService $lockService): void {
                $actor = $this->currentUser();

                if (! $actor instanceof User) {
                    abort(403);
                }

                $lockService->lockInstitution($this->institutionRecord(), $actor, $data['reason'] ?? null);

                Notification::make()
                    ->title('Public submission locked for this institution.')
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
            ->visible(fn (): bool => $this->canManagePublicSubmissionLock() && ! $this->institutionRecord()->allow_public_event_submission)
            ->action(function (PublicSubmissionLockService $lockService): void {
                $actor = $this->currentUser();

                if (! $actor instanceof User) {
                    abort(403);
                }

                $lockService->unlockInstitution($this->institutionRecord(), $actor);

                Notification::make()
                    ->title('Public submission unlocked for this institution.')
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
        return app(PublicSubmissionLockService::class)->institutionEligibility($this->institutionRecord());
    }

    private function institutionRecord(): Institution
    {
        $record = $this->getRecord();

        if (! $record instanceof Institution) {
            throw new \RuntimeException('Expected Filament record to be an Institution instance.');
        }

        return $record;
    }

    private function currentUser(): ?User
    {
        $user = auth()->user();

        return $user instanceof User ? $user : null;
    }
}
