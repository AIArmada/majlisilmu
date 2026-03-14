<?php

namespace App\Actions\Membership;

use App\Enums\MemberSubjectType;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use App\Models\User;
use App\Support\Submission\PublicSubmissionLockService;
use Lorisleiva\Actions\Concerns\AsAction;

final class AddMemberToSubject
{
    use AsAction;

    public function __construct(
        private readonly ChangeSubjectMemberRole $changeSubjectMemberRole,
        private readonly PublicSubmissionLockService $publicSubmissionLockService,
    ) {}

    public function handle(Institution|Speaker|Event|Reference $subject, User $member, ?string $roleIdentifier = null): void
    {
        $subjectType = MemberSubjectType::forSubject($subject);

        match ($subjectType) {
            MemberSubjectType::Institution,
            MemberSubjectType::Speaker,
            MemberSubjectType::Reference => $subject->members()->syncWithoutDetaching([$member->getKey()]),
            MemberSubjectType::Event => $subject->members()->syncWithoutDetaching([
                $member->getKey() => ['joined_at' => now()],
            ]),
        };

        if ($roleIdentifier !== null && $roleIdentifier !== '') {
            $this->changeSubjectMemberRole->handle($subject, $member, $roleIdentifier);
        }

        if ($subject instanceof Institution) {
            $this->publicSubmissionLockService->ensureInstitutionUnlockedIfIneligible($subject->fresh());
        }

        if ($subject instanceof Speaker) {
            $this->publicSubmissionLockService->ensureSpeakerUnlockedIfIneligible($subject->fresh());
        }
    }
}
