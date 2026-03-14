<?php

namespace App\Services;

use App\Actions\Membership\AssignOwnerToNewSubject;
use App\Enums\ContributionRequestStatus;
use App\Enums\ContributionRequestType;
use App\Enums\ContributionSubjectType;
use App\Models\ContributionRequest;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class ContributionWorkflowService
{
    public function __construct(
        private readonly ModerationService $moderationService,
        private readonly ContributionEntityMutationService $entityMutationService,
        private readonly AssignOwnerToNewSubject $assignOwnerToNewSubject,
    ) {}

    /**
     * @param  array<string, mixed>  $proposedData
     */
    public function submitCreateRequest(
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

    /**
     * @param  array<string, mixed>  $proposedData
     */
    public function submitUpdateRequest(
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

    public function approve(ContributionRequest $request, User $reviewer, ?string $reviewerNote = null): ContributionRequest
    {
        if (! $request->isPending()) {
            throw new RuntimeException('Only pending contribution requests can be approved.');
        }

        DB::transaction(function () use ($request, $reviewer, $reviewerNote): void {
            if ($request->type === ContributionRequestType::Create) {
                $entity = $this->approveCreateRequest($request);

                $request->forceFill([
                    'entity_type' => $entity->getMorphClass(),
                    'entity_id' => (string) $entity->getKey(),
                ]);
            } else {
                $this->applyApprovedUpdate($request);
            }

            $request->forceFill([
                'reviewer_id' => $reviewer->getKey(),
                'reviewer_note' => $reviewerNote,
                'status' => ContributionRequestStatus::Approved,
                'reviewed_at' => now(),
            ])->save();
        });

        return $request->fresh(['entity', 'proposer', 'reviewer']) ?? $request;
    }

    public function reject(
        ContributionRequest $request,
        User $reviewer,
        string $reasonCode,
        ?string $reviewerNote = null,
    ): ContributionRequest {
        if (! $request->isPending()) {
            throw new RuntimeException('Only pending contribution requests can be rejected.');
        }

        $request->forceFill([
            'reviewer_id' => $reviewer->getKey(),
            'reason_code' => $reasonCode,
            'reviewer_note' => $reviewerNote,
            'status' => ContributionRequestStatus::Rejected,
            'reviewed_at' => now(),
        ])->save();

        if ($request->type === ContributionRequestType::Create) {
            $entity = $request->entity;

            if ($entity instanceof Institution || $entity instanceof Speaker) {
                $entity->forceFill([
                    'status' => 'rejected',
                    'is_active' => false,
                ])->save();
            }
        }

        return $request->fresh(['entity', 'proposer', 'reviewer']) ?? $request;
    }

    public function cancel(ContributionRequest $request, User $proposer): ContributionRequest
    {
        if (! $request->isPending()) {
            throw new RuntimeException('Only pending contribution requests can be cancelled.');
        }

        if ((string) $request->proposer_id !== (string) $proposer->getKey()) {
            throw new RuntimeException('Only the original proposer can cancel this request.');
        }

        $request->forceFill([
            'status' => ContributionRequestStatus::Cancelled,
            'cancelled_at' => now(),
        ])->save();

        return $request->fresh(['entity', 'proposer', 'reviewer']) ?? $request;
    }

    private function approveCreateRequest(ContributionRequest $request): Institution|Speaker
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->proposed_data ?? [];

        $entity = $request->entity;

        if ($entity instanceof Institution || $entity instanceof Speaker) {
            $entity->forceFill([
                'status' => 'verified',
                'is_active' => true,
            ])->save();

            $this->attachAsOwner($request->proposer, $entity);

            return $entity;
        }

        return match ($request->subject_type) {
            ContributionSubjectType::Institution => $this->createInstitutionFromRequest($request, $payload),
            ContributionSubjectType::Speaker => $this->createSpeakerFromRequest($request, $payload),
            default => throw new RuntimeException('Unsupported create request subject.'),
        };
    }

    private function applyApprovedUpdate(ContributionRequest $request): void
    {
        $entity = $request->entity;

        if (! $entity instanceof Model) {
            throw new RuntimeException('Update request is missing its target entity.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->proposed_data ?? [];
        $dirtyBeforeSave = $this->entityMutationService->apply($entity, $payload);

        if ($entity instanceof Event && $dirtyBeforeSave !== []) {
            $this->moderationService->handleSensitiveChange($entity, $dirtyBeforeSave);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createInstitutionFromRequest(ContributionRequest $request, array $payload): Institution
    {
        $institution = Institution::create([
            'name' => (string) ($payload['name'] ?? 'Institution'),
            'slug' => Str::slug((string) ($payload['name'] ?? 'institution')).'-'.Str::lower(Str::random(7)),
            'type' => (string) ($payload['type'] ?? 'masjid'),
            'description' => $payload['description'] ?? null,
            'status' => 'verified',
            'is_active' => true,
            'allow_public_event_submission' => true,
        ]);

        $this->attachAsOwner($request->proposer, $institution);

        return $institution;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createSpeakerFromRequest(ContributionRequest $request, array $payload): Speaker
    {
        $speaker = Speaker::create([
            'name' => (string) ($payload['name'] ?? 'Speaker'),
            'gender' => (string) ($payload['gender'] ?? 'male'),
            'honorific' => $payload['honorific'] ?? null,
            'pre_nominal' => $payload['pre_nominal'] ?? null,
            'post_nominal' => $payload['post_nominal'] ?? null,
            'job_title' => $payload['job_title'] ?? null,
            'bio' => $payload['bio'] ?? null,
            'is_freelance' => (bool) ($payload['is_freelance'] ?? false),
            'slug' => Str::slug((string) ($payload['name'] ?? 'speaker')).'-'.Str::lower(Str::random(7)),
            'status' => 'verified',
            'is_active' => true,
            'allow_public_event_submission' => true,
        ]);

        $this->attachAsOwner($request->proposer, $speaker);

        return $speaker;
    }

    private function attachAsOwner(User $user, Institution|Speaker $entity): void
    {
        $this->assignOwnerToNewSubject->handle($entity, $user);
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
