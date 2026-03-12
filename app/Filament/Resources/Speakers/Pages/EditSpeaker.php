<?php

namespace App\Filament\Resources\Speakers\Pages;

use App\Filament\Resources\Speakers\SpeakerResource;
use App\Models\Speaker;
use App\Models\User;
use App\Support\Submission\PublicSubmissionLockService;
use App\Support\Submission\PublicSubmissionUiEvents;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

class EditSpeaker extends EditRecord
{
    protected static string $resource = SpeakerResource::class;

    #[\Override]
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[\Override]
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $actor = $this->currentUser();

        if (! $actor instanceof User) {
            abort(403);
        }

        $speaker = $this->speakerRecord();
        $requestedPublicSubmission = (bool) ($data['allow_public_event_submission'] ?? $speaker->allow_public_event_submission);
        $currentPublicSubmission = (bool) $speaker->allow_public_event_submission;

        if ($requestedPublicSubmission === $currentPublicSubmission) {
            return $data;
        }

        $lockService = app(PublicSubmissionLockService::class);

        if ($requestedPublicSubmission) {
            $lockService->unlockSpeaker($speaker, $actor);

            return $data;
        }

        $eligibility = $lockService->speakerEligibility($speaker);

        if (! $eligibility->eligible) {
            throw ValidationException::withMessages([
                'data.allow_public_event_submission' => $eligibility->reasons,
            ]);
        }

        $lockService->lockSpeaker($speaker, $actor);

        return $data;
    }

    #[On(PublicSubmissionUiEvents::REFRESH_TOGGLE)]
    public function refreshPublicSubmissionToggleState(): void
    {
        $this->speakerRecord()->refresh();
        $this->refreshFormData(['allow_public_event_submission']);
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
