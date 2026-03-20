<?php

namespace App\Actions\Membership;

use App\Enums\MemberSubjectType;
use App\Models\Institution;
use App\Models\Speaker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveMembershipClaimSubjectAction
{
    use AsAction;

    public function handle(string $subjectType, string $subjectId): Institution|Speaker
    {
        $resolvedSubjectType = MemberSubjectType::fromRouteSegment($subjectType);

        abort_unless($resolvedSubjectType?->isClaimable(), 404);

        return match ($resolvedSubjectType) {
            MemberSubjectType::Institution => $this->resolveSlugOrUuid(Institution::query(), 'institutions.slug', $subjectId),
            MemberSubjectType::Speaker => $this->resolveSlugOrUuid(Speaker::query(), 'speakers.slug', $subjectId),
            default => abort(404),
        };
    }

    /**
     * @template TModel of Institution|Speaker
     *
     * @param  Builder<TModel>  $query
     * @return TModel
     */
    private function resolveSlugOrUuid(Builder $query, string $slugColumn, string $subjectId): Institution|Speaker
    {
        $query->where($slugColumn, $subjectId);

        if (Str::isUuid($subjectId)) {
            $query->orWhere($query->getModel()->getQualifiedKeyName(), $subjectId);
        }

        return $query->firstOrFail();
    }
}
