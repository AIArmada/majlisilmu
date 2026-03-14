<?php

namespace App\Actions\Membership;

use App\Enums\MemberSubjectType;
use App\Models\Event;
use App\Models\Institution;
use App\Models\MemberInvitation;
use App\Models\Reference;
use App\Models\Speaker;
use App\Models\User;
use App\Support\Authz\MemberRoleCatalog;
use App\Support\Authz\ScopedMemberRoleSeeder;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Lorisleiva\Actions\Concerns\AsAction;

final class InviteSubjectMember
{
    use AsAction;

    public function __construct(
        private readonly MemberRoleCatalog $memberRoleCatalog,
        private readonly ScopedMemberRoleSeeder $scopedMemberRoleSeeder,
    ) {}

    public function handle(
        Institution|Speaker|Event|Reference $subject,
        string $email,
        string $roleSlug,
        User $inviter,
        ?CarbonInterface $expiresAt = null,
    ): MemberInvitation {
        $normalizedEmail = mb_strtolower(trim($email));

        if ($normalizedEmail === '') {
            throw new InvalidArgumentException('Invitation email is required.');
        }

        $subjectType = MemberSubjectType::forSubject($subject);
        $this->scopedMemberRoleSeeder->ensure($subjectType);
        $this->memberRoleCatalog->resolveRoleId($subjectType, $roleSlug);

        return MemberInvitation::create([
            'subject_type' => $subjectType,
            'subject_id' => $subject->getKey(),
            'email' => $normalizedEmail,
            'role_slug' => $roleSlug,
            'token' => Str::random(64),
            'invited_by' => $inviter->getKey(),
            'expires_at' => $expiresAt,
        ]);
    }
}
