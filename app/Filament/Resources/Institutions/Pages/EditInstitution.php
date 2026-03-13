<?php

namespace App\Filament\Resources\Institutions\Pages;

use App\Filament\Resources\Institutions\InstitutionResource;
use App\Models\Institution;
use App\Models\User;
use App\Support\Submission\PublicSubmissionLockService;
use App\Support\Submission\PublicSubmissionUiEvents;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;

class EditInstitution extends EditRecord
{
    protected static string $resource = InstitutionResource::class;

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

        $institution = $this->institutionRecord();
        $requestedPublicSubmission = (bool) ($data['allow_public_event_submission'] ?? $institution->allow_public_event_submission);
        $currentPublicSubmission = (bool) $institution->allow_public_event_submission;

        if ($requestedPublicSubmission === $currentPublicSubmission) {
            return $data;
        }

        $lockService = app(PublicSubmissionLockService::class);

        if ($requestedPublicSubmission) {
            $lockService->unlockInstitution($institution, $actor);

            return $data;
        }

        $eligibility = $lockService->institutionEligibility($institution);

        if (! $eligibility->eligible) {
            throw ValidationException::withMessages([
                'data.allow_public_event_submission' => $eligibility->reasons,
            ]);
        }

        $lockService->lockInstitution($institution, $actor);

        return $data;
    }

    #[On(PublicSubmissionUiEvents::REFRESH_TOGGLE)]
    public function refreshPublicSubmissionToggleState(): void
    {
        $this->institutionRecord()->refresh();
        $this->refreshFormData(['allow_public_event_submission']);
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
