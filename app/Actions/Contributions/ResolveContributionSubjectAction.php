<?php

namespace App\Actions\Contributions;

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
        return match ($subjectType) {
            'event' => $this->resolveSlugOrUuid(Event::query(), 'events.slug', $subjectId),
            'institution' => $this->resolveSlugOrUuid(Institution::query(), 'institutions.slug', $subjectId),
            'speaker' => $this->resolveSlugOrUuid(Speaker::query(), 'speakers.slug', $subjectId),
            'reference' => $this->resolveReference($subjectId),
            default => abort(404),
        };
    }

    /**
     * @template TModel of Event|Institution|Speaker
     *
     * @param  Builder<TModel>  $query
     * @return TModel
     */
    private function resolveSlugOrUuid(Builder $query, string $slugColumn, string $subjectId): Event|Institution|Speaker
    {
        $query->where($slugColumn, $subjectId);

        if (Str::isUuid($subjectId)) {
            $query->orWhere($query->getModel()->getQualifiedKeyName(), $subjectId);
        }

        return $query->firstOrFail();
    }

    private function resolveReference(string $subjectId): Reference
    {
        abort_unless(Str::isUuid($subjectId), 404);

        return Reference::query()->whereKey($subjectId)->firstOrFail();
    }
}
