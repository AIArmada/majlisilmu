<?php

declare(strict_types=1);

namespace App\Support\Api;

use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use App\Support\Search\InstitutionSearchService;
use App\Support\Search\ReferenceSearchService;
use App\Support\Search\SpeakerSearchService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ResourceSearchDispatcher
{
    public function __construct(
        private readonly InstitutionSearchService $institutionSearchService,
        private readonly ReferenceSearchService $referenceSearchService,
        private readonly SpeakerSearchService $speakerSearchService,
    ) {}

    /**
     * @param  Builder<Model>  $query
     */
    public function apply(Builder $query, string $search): bool
    {
        $model = $query->getModel();

        if ($model instanceof Speaker) {
            /** @var Builder<Speaker> $speakerQuery */
            $speakerQuery = $query;

            return $this->applyMatchingIds($query, $this->speakerSearchService->scopedSearchIds($speakerQuery, $search));
        }

        if ($model instanceof Institution) {
            /** @var Builder<Institution> $institutionQuery */
            $institutionQuery = $query;

            return $this->applyMatchingIds($query, $this->institutionSearchService->scopedSearchIds($institutionQuery, $search));
        }

        if ($model instanceof Reference) {
            /** @var Builder<Reference> $referenceQuery */
            $referenceQuery = $query;

            return $this->applyMatchingIds($query, $this->referenceSearchService->scopedSearchIds($referenceQuery, $search));
        }

        if ($model instanceof Event) {
            /** @var Builder<Event> $eventQuery */
            $eventQuery = $query;
            $this->applyEventSearch($eventQuery, $search);

            return true;
        }

        return false;
    }

    /**
     * @param  Builder<Model>  $query
     * @param  list<string>  $matchingIds
     */
    private function applyMatchingIds(Builder $query, array $matchingIds): bool
    {
        if ($matchingIds === []) {
            $query->whereRaw('1 = 0');

            return true;
        }

        $model = $query->getModel();

        $query->whereIn($model->qualifyColumn($model->getKeyName()), $matchingIds);

        return true;
    }

    /**
     * Applies a broad OR search against event title, slug, related institution/venue
     * names, key person free-text names, and indexed speaker names.
     *
     * @param  Builder<Event>  $query
     */
    private function applyEventSearch(Builder $query, string $search): void
    {
        $operator = DB::connection($query->getModel()->getConnectionName())->getDriverName() === 'pgsql'
            ? 'ILIKE'
            : 'LIKE';

        $matchingSpeakerIds = $this->speakerSearchService->scopedSearchIds(
            Speaker::query()->select('id'),
            $search,
        );

        $query->where(function (Builder $q) use ($search, $operator, $matchingSpeakerIds): void {
            $q->where('events.title', $operator, "%{$search}%")
                ->orWhere('events.slug', $operator, "%{$search}%")
                ->orWhereHas(
                    'institution',
                    fn (Builder $institutionQuery) => $institutionQuery->where('institutions.name', $operator, "%{$search}%"),
                )
                ->orWhereHas(
                    'venue',
                    fn (Builder $venueQuery) => $venueQuery->where('venues.name', $operator, "%{$search}%"),
                )
                ->orWhereHas(
                    'keyPeople',
                    fn (Builder $keyPersonQuery) => $keyPersonQuery->where('event_key_people.name', $operator, "%{$search}%"),
                );

            if ($matchingSpeakerIds !== []) {
                $q->orWhereHas(
                    'keyPeople',
                    fn (Builder $keyPersonQuery) => $keyPersonQuery->whereIn('event_key_people.speaker_id', $matchingSpeakerIds),
                );
            }
        });
    }
}
