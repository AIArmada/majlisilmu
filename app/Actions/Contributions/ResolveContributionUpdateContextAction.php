<?php

namespace App\Actions\Contributions;

use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use App\Services\ContributionEntityMutationService;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveContributionUpdateContextAction
{
    use AsAction;

    public function __construct(
        protected ContributionEntityMutationService $contributionEntityMutationService,
        protected ResolveContributionSubjectAction $resolveContributionSubjectAction,
    ) {}

    /**
     * @return array{
     *     entity: Event|Institution|Reference|Speaker,
     *     initial_state: array<string, mixed>
     * }
     */
    public function handle(string $subjectType, string $subjectId): array
    {
        $entity = $this->resolveContributionSubjectAction->handle($subjectType, $subjectId);

        return [
            'entity' => $entity,
            'initial_state' => $this->contributionEntityMutationService->stateFor($entity),
        ];
    }
}
