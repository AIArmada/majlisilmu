<?php

namespace App\Actions\Contributions;

use App\Enums\ContributionRequestStatus;
use App\Enums\ContributionRequestType;
use App\Enums\ContributionSubjectType;
use App\Models\ContributionRequest;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use App\Models\User;
use App\Services\ContributionEntityMutationService;
use Illuminate\Database\Eloquent\Model;
use Lorisleiva\Actions\Concerns\AsAction;
use RuntimeException;

class SubmitContributionUpdateRequestAction
{
    use AsAction;

    public function __construct(
        private readonly ContributionEntityMutationService $entityMutationService,
    ) {}

    /**
     * @param  array<string, mixed>  $proposedData
     */
    public function handle(
        Model $entity,
        User $proposer,
        array $proposedData,
        ?string $proposerNote = null,
    ): ContributionRequest {
        $subjectType = $this->subjectTypeForModel($entity);
        $originalData = array_intersect_key(
            $this->entityMutationService->stateFor($entity),
            $proposedData,
        );

        return ContributionRequest::create([
            'type' => ContributionRequestType::Update,
            'subject_type' => $subjectType,
            'entity_type' => $entity->getMorphClass(),
            'entity_id' => (string) $entity->getKey(),
            'proposer_id' => $proposer->getKey(),
            'status' => ContributionRequestStatus::Pending,
            'proposed_data' => $proposedData,
            'original_data' => $originalData,
            'proposer_note' => $proposerNote,
        ]);
    }

    private function subjectTypeForModel(Model $entity): ContributionSubjectType
    {
        return match ($entity::class) {
            Event::class => ContributionSubjectType::Event,
            Institution::class => ContributionSubjectType::Institution,
            Speaker::class => ContributionSubjectType::Speaker,
            Reference::class => ContributionSubjectType::Reference,
            default => throw new RuntimeException('Unsupported contribution entity type.'),
        };
    }
}
