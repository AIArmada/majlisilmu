<?php

namespace App\Actions\Membership;

use App\Enums\MemberSubjectType;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use App\Models\User;
use App\Support\Authz\MemberRoleCatalog;
use Lorisleiva\Actions\Concerns\AsAction;

final class AssignOwnerToNewSubject
{
    use AsAction;

    public function __construct(
        private readonly AddMemberToSubject $addMemberToSubject,
        private readonly MemberRoleCatalog $memberRoleCatalog,
    ) {}

    public function handle(Institution|Speaker|Event|Reference $subject, User $member): void
    {
        $subjectType = MemberSubjectType::forSubject($subject);

        $this->addMemberToSubject->handle(
            $subject,
            $member,
            $this->memberRoleCatalog->primaryRoleName($subjectType),
        );
    }
}
