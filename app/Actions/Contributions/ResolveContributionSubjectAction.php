<?php

namespace App\Actions\Contributions;

use App\Enums\ContributionSubjectType;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveContributionSubjectAction
{
    use AsAction;

    public function handle(string $subjectType, string $subjectId): Event|Institution|Reference|Speaker
    {
        $resolvedSubjectType = ContributionSubjectType::fromRouteSegment($subjectType);

        return match ($resolvedSubjectType) {
            ContributionSubjectType::Event => $this->resolveSlugOrUuid(Event::query(), 'events.slug', $subjectId),
            ContributionSubjectType::Institution => $this->resolveSlugOrUuid(Institution::query(), 'institutions.slug', $subjectId),
            ContributionSubjectType::Speaker => $this->resolveSlugOrUuid(Speaker::query(), 'speakers.slug', $subjectId),
            ContributionSubjectType::Reference => $this->resolveSlugOrUuid(Reference::query(), 'references.slug', $subjectId),
            default => abort(404),
        };
    }

    /**
     * @template TModel of Event|Institution|Reference|Speaker
     *
     * @param  Builder<TModel>  $query
     * @return TModel
     */
    private function resolveSlugOrUuid(Builder $query, string $slugColumn, string $subjectId): Event|Institution|Reference|Speaker
    {
        $query->where($slugColumn, $subjectId);

        if (Str::isUuid($subjectId)) {
            $query->orWhere($query->getModel()->getQualifiedKeyName(), $subjectId);
        }

        return $query->firstOrFail();
    }
}
