<?php

namespace App\Enums;

use AIArmada\FilamentAuthz\Models\AuthzScope;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use App\Models\User;
use App\Support\Authz\MemberRoleScopes;
use Illuminate\Database\Eloquent\ModelNotFoundException;

enum MemberSubjectType: string
{
    case Institution = 'institution';
    case Speaker = 'speaker';
    case Event = 'event';
    case Reference = 'reference';

    public static function forSubject(Institution|Speaker|Event|Reference $subject): self
    {
        return match (true) {
            $subject instanceof Institution => self::Institution,
            $subject instanceof Speaker => self::Speaker,
            $subject instanceof Event => self::Event,
            $subject instanceof Reference => self::Reference,
        };
    }

    public function authzScope(MemberRoleScopes $memberRoleScopes): AuthzScope
    {
        return match ($this) {
            self::Institution => $memberRoleScopes->institution(),
            self::Speaker => $memberRoleScopes->speaker(),
            self::Event => $memberRoleScopes->event(),
            self::Reference => $memberRoleScopes->reference(),
        };
    }

    public function primaryRoleName(): string
    {
        return match ($this) {
            self::Event => 'organizer',
            default => 'owner',
        };
    }

    public function usesJoinedAtPivot(): bool
    {
        return $this === self::Event;
    }

    public function usesPublicSubmissionLocks(): bool
    {
        return in_array($this, [self::Institution, self::Speaker], true);
    }

    public function userHasAnyMembership(User $user): bool
    {
        return match ($this) {
            self::Institution => $user->institutions()->exists(),
            self::Speaker => $user->speakers()->exists(),
            self::Event => $user->memberEvents()->exists(),
            self::Reference => $user->references()->exists(),
        };
    }

    /**
     * @return class-string<Institution|Speaker|Event|Reference>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Institution => Institution::class,
            self::Speaker => Speaker::class,
            self::Event => Event::class,
            self::Reference => Reference::class,
        };
    }

    /**
     * @throws ModelNotFoundException
     */
    public function resolveSubject(string $subjectId): Institution|Speaker|Event|Reference
    {
        $modelClass = $this->modelClass();
        $subject = $modelClass::query()->findOrFail($subjectId);

        if (
            ! $subject instanceof Institution &&
            ! $subject instanceof Speaker &&
            ! $subject instanceof Event &&
            ! $subject instanceof Reference
        ) {
            throw (new ModelNotFoundException)->setModel($modelClass, [$subjectId]);
        }

        return $subject;
    }
}
