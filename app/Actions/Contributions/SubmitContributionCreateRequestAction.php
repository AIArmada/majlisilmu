<?php

namespace App\Actions\Contributions;

use App\Enums\ContributionRequestStatus;
use App\Enums\ContributionRequestType;
use App\Enums\ContributionSubjectType;
use App\Models\ContributionRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

class SubmitContributionCreateRequestAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $proposedData
     */
    public function handle(
        ContributionSubjectType $subjectType,
        User $proposer,
        array $proposedData,
        ?string $proposerNote = null,
        ?Model $entity = null,
    ): ContributionRequest {
        if (! in_array($subjectType, [ContributionSubjectType::Institution, ContributionSubjectType::Speaker], true)) {
            throw new RuntimeException('Only institution and speaker creation requests are currently supported.');
        }

        return ContributionRequest::create([
            'type' => ContributionRequestType::Create,
            'subject_type' => $subjectType,
            'entity_type' => $entity?->getMorphClass(),
            'entity_id' => $entity?->getKey(),
            'proposer_id' => $proposer->getKey(),
            'status' => ContributionRequestStatus::Pending,
            'proposed_data' => $proposedData,
            'proposer_note' => $proposerNote,
        ]);
    }
}
