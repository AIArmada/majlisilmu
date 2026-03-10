<?php

namespace App\Services\DawahShare;

use App\Models\DawahShareLink;
use App\Models\DawahShareOutcome;
use App\Models\DawahShareShareEvent;
use App\Models\DawahShareVisit;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DawahShareAnalyticsService
{
    /**
     * @return array{
     *     outbound_shares: int,
     *     visits: int,
     *     unique_visitors: int,
     *     signups: int,
     *     event_registrations: int,
     *     event_checkins: int,
     *     event_submissions: int,
     *     total_outcomes: int
     * }
     */
    public function summaryForUser(User $user): array
    {
        $linkIds = DawahShareLink::query()
            ->where('user_id', $user->id)
            ->pluck('id');

        if ($linkIds->isEmpty()) {
            return [
                'outbound_shares' => 0,
                'visits' => 0,
                'unique_visitors' => 0,
                'signups' => 0,
                'event_registrations' => 0,
                'event_checkins' => 0,
                'event_submissions' => 0,
                'total_outcomes' => 0,
            ];
        }

        return [
            'outbound_shares' => DawahShareShareEvent::query()->whereIn('link_id', $linkIds)->where('event_type', 'outbound_click')->count(),
            'visits' => DawahShareVisit::query()->whereIn('link_id', $linkIds)->count(),
            'unique_visitors' => DawahShareVisit::query()->whereIn('link_id', $linkIds)->distinct('visitor_key')->count('visitor_key'),
            'signups' => DawahShareOutcome::query()->whereIn('link_id', $linkIds)->where('outcome_type', 'signup')->count(),
            'event_registrations' => DawahShareOutcome::query()->whereIn('link_id', $linkIds)->where('outcome_type', 'event_registration')->count(),
            'event_checkins' => DawahShareOutcome::query()->whereIn('link_id', $linkIds)->where('outcome_type', 'event_checkin')->count(),
            'event_submissions' => DawahShareOutcome::query()->whereIn('link_id', $linkIds)->where('outcome_type', 'event_submission')->count(),
            'total_outcomes' => DawahShareOutcome::query()->whereIn('link_id', $linkIds)->count(),
        ];
    }

    /**
     * @return Collection<int, array{
     *     provider: string,
     *     label: string,
     *     outbound_shares: int,
     *     visits: int,
     *     unique_visitors: int,
     *     outcomes: int,
     *     signups: int,
     *     event_registrations: int,
     *     event_checkins: int,
     *     event_submissions: int
     * }>
     */
    public function providerBreakdownForUser(User $user): Collection
    {
        $linkIds = DawahShareLink::query()
            ->where('user_id', $user->id)
            ->pluck('id');

        if ($linkIds->isEmpty()) {
            return collect();
        }

        return $this->providerBreakdown(
            DawahShareShareEvent::query()->whereIn('link_id', $linkIds),
            DawahShareVisit::query()->whereIn('link_id', $linkIds),
            DawahShareOutcome::query()->whereIn('link_id', $linkIds),
        );
    }

    /**
     * @return Collection<int, DawahShareLink>
     */
    public function linksForUser(
        User $user,
        string $sort = 'recent',
        ?string $subjectType = null,
        string $status = 'all',
        string $outcomeType = 'all'
    ): Collection {
        /** @var Collection<int, DawahShareLink> $links */
        $links = $this->baseLinksQuery($user)
            ->when(
                filled($subjectType) && $subjectType !== 'all',
                fn (Builder $query): Builder => $query->where('subject_type', $subjectType),
            )
            ->when(
                filled($outcomeType) && $outcomeType !== 'all',
                fn (Builder $query): Builder => $query->whereHas(
                    'outcomes',
                    fn (Builder $builder): Builder => $builder->where('outcome_type', $outcomeType)
                ),
            )
            ->get()
            ->filter(function (DawahShareLink $link) use ($status): bool {
                if ($status === 'all') {
                    return true;
                }

                $isActive = $this->latestActivityAt($link)?->greaterThanOrEqualTo(
                    now()->subDays((int) config('dawah-share.ttl_days', 30))
                ) ?? false;

                return $status === 'active' ? $isActive : ! $isActive;
            });

        $sorted = match ($sort) {
            'visits' => $links->sortByDesc(fn (DawahShareLink $link): int => (int) ($link->visits_count ?? 0)),
            'signups' => $links->sortByDesc(fn (DawahShareLink $link): int => (int) ($link->signups_count ?? 0)),
            'registrations' => $links->sortByDesc(fn (DawahShareLink $link): int => (int) ($link->event_registrations_count ?? 0)),
            'checkins' => $links->sortByDesc(fn (DawahShareLink $link): int => (int) ($link->event_checkins_count ?? 0)),
            'submissions' => $links->sortByDesc(fn (DawahShareLink $link): int => (int) ($link->event_submissions_count ?? 0)),
            default => $links->sortByDesc(fn (DawahShareLink $link): int => $this->latestActivityAt($link)?->getTimestamp() ?? 0),
        };

        /** @var Collection<int, DawahShareLink> $values */
        $values = $sorted->values();

        return $values;
    }

    /**
     * @return Collection<int, array{
     *     subject_type: string,
     *     label: string,
     *     links: int,
     *     visits: int,
     *     signups: int,
     *     event_registrations: int,
     *     event_checkins: int,
     *     event_submissions: int,
     *     total_outcomes: int
     * }>
     */
    public function subjectSummariesForUser(User $user): Collection
    {
        return $this->linksForUser($user)
            ->groupBy('subject_type')
            ->map(function (Collection $links, string $subjectType): array {
                return [
                    'subject_type' => $subjectType,
                    'label' => $this->subjectTypeLabel($subjectType),
                    'links' => $links->count(),
                    'visits' => (int) $links->sum(fn (DawahShareLink $link): int => (int) ($link->visits_count ?? 0)),
                    'signups' => (int) $links->sum(fn (DawahShareLink $link): int => (int) ($link->signups_count ?? 0)),
                    'event_registrations' => (int) $links->sum(fn (DawahShareLink $link): int => (int) ($link->event_registrations_count ?? 0)),
                    'event_checkins' => (int) $links->sum(fn (DawahShareLink $link): int => (int) ($link->event_checkins_count ?? 0)),
                    'event_submissions' => (int) $links->sum(fn (DawahShareLink $link): int => (int) ($link->event_submissions_count ?? 0)),
                    'total_outcomes' => (int) $links->sum(fn (DawahShareLink $link): int => (int) ($link->outcomes_count ?? 0)),
                ];
            })
            ->sortByDesc('visits')
            ->values();
    }

    /**
     * @return Collection<int, array{
     *     date: string,
     *     visits: int,
     *     outcomes: int,
     *     signups: int,
     *     event_registrations: int,
     *     event_checkins: int,
     *     event_submissions: int
     * }>
     */
    public function dailyPerformanceForLink(DawahShareLink $link, int $days = 14): Collection
    {
        $fromDate = now()->subDays(max($days - 1, 0))->startOfDay();

        /** @var array<string, int> $visits */
        $visits = $link->visits()
            ->where('occurred_at', '>=', $fromDate)
            ->selectRaw('date(occurred_at) as day, count(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day')
            ->map(fn (mixed $count): int => (int) $count)
            ->all();

        /** @var array<string, array{outcomes: int, signups: int, event_registrations: int, event_checkins: int, event_submissions: int}> $outcomes */
        $outcomes = $link->outcomes()
            ->where('occurred_at', '>=', $fromDate)
            ->selectRaw(
                'date(occurred_at) as day, count(*) as outcomes, '.
                "sum(case when outcome_type = 'signup' then 1 else 0 end) as signups, ".
                "sum(case when outcome_type = 'event_registration' then 1 else 0 end) as event_registrations, ".
                "sum(case when outcome_type = 'event_checkin' then 1 else 0 end) as event_checkins, ".
                "sum(case when outcome_type = 'event_submission' then 1 else 0 end) as event_submissions"
            )
            ->groupBy('day')
            ->get()
            ->mapWithKeys(fn (DawahShareOutcome $outcome): array => [
                (string) $outcome->getAttribute('day') => [
                    'outcomes' => (int) $outcome->getAttribute('outcomes'),
                    'signups' => (int) $outcome->getAttribute('signups'),
                    'event_registrations' => (int) $outcome->getAttribute('event_registrations'),
                    'event_checkins' => (int) $outcome->getAttribute('event_checkins'),
                    'event_submissions' => (int) $outcome->getAttribute('event_submissions'),
                ],
            ])
            ->all();

        return collect(range(0, max($days - 1, 0)))
            ->map(function (int $offset) use ($fromDate, $visits, $outcomes): array {
                $date = $fromDate->copy()->addDays($offset)->toDateString();
                $dailyOutcomes = $outcomes[$date] ?? [
                    'outcomes' => 0,
                    'signups' => 0,
                    'event_registrations' => 0,
                    'event_checkins' => 0,
                    'event_submissions' => 0,
                ];

                return [
                    'date' => $date,
                    'visits' => $visits[$date] ?? 0,
                    'outcomes' => $dailyOutcomes['outcomes'],
                    'signups' => $dailyOutcomes['signups'],
                    'event_registrations' => $dailyOutcomes['event_registrations'],
                    'event_checkins' => $dailyOutcomes['event_checkins'],
                    'event_submissions' => $dailyOutcomes['event_submissions'],
                ];
            });
    }

    /**
     * @return Collection<int, array{outcome_type: string, label: string, count: int}>
     */
    public function outcomeBreakdownForLink(DawahShareLink $link): Collection
    {
        return $link->outcomes()
            ->select('outcome_type', DB::raw('count(*) as aggregate'))
            ->groupBy('outcome_type')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn (DawahShareOutcome $outcome): array => [
                'outcome_type' => (string) $outcome->outcome_type,
                'label' => $this->outcomeTypeLabel((string) $outcome->outcome_type),
                'count' => (int) $outcome->getAttribute('aggregate'),
            ]);
    }

    /**
     * @return array{
     *     first_seen_at: CarbonInterface|null,
     *     last_visit_at: CarbonInterface|null,
     *     last_outcome_at: CarbonInterface|null,
     *     latest_activity_at: CarbonInterface|null
     * }
     */
    public function activityWindowForLink(DawahShareLink $link): array
    {
        $firstVisit = $this->asCarbon($link->visits()->min('occurred_at'));
        $lastVisit = $this->asCarbon($link->visits()->max('occurred_at'));
        $lastOutcome = $this->asCarbon($link->outcomes()->max('occurred_at'));
        $latestActivity = collect([$lastVisit, $lastOutcome, $link->last_shared_at])
            ->filter()
            ->map(fn (mixed $value): CarbonInterface => $this->asCarbon($value) ?? now())
            ->sortByDesc(fn (CarbonInterface $value): int => $value->getTimestamp())
            ->first();

        return [
            'first_seen_at' => $firstVisit,
            'last_visit_at' => $lastVisit,
            'last_outcome_at' => $lastOutcome,
            'latest_activity_at' => $latestActivity instanceof Carbon ? $latestActivity : null,
        ];
    }

    /**
     * @return Builder<DawahShareLink>
     */
    protected function baseLinksQuery(User $user): Builder
    {
        return DawahShareLink::query()
            ->where('user_id', $user->id)
            ->withCount([
                'visits',
                'outcomes',
                'outcomes as signups_count' => fn (Builder $builder) => $builder->where('outcome_type', 'signup'),
                'outcomes as event_registrations_count' => fn (Builder $builder) => $builder->where('outcome_type', 'event_registration'),
                'outcomes as event_checkins_count' => fn (Builder $builder) => $builder->where('outcome_type', 'event_checkin'),
                'outcomes as event_submissions_count' => fn (Builder $builder) => $builder->where('outcome_type', 'event_submission'),
            ])
            ->withMax('visits as latest_visit_at', 'occurred_at')
            ->withMax('outcomes as latest_outcome_at', 'occurred_at');
    }

    protected function latestActivityAt(DawahShareLink $link): ?CarbonInterface
    {
        return collect([
            $link->last_shared_at,
            $link->getAttribute('latest_visit_at'),
            $link->getAttribute('latest_outcome_at'),
        ])
            ->filter()
            ->map(fn (mixed $value): ?CarbonInterface => $this->asCarbon($value))
            ->filter()
            ->sortByDesc(fn (CarbonInterface $value): int => $value->getTimestamp())
            ->first();
    }

    protected function asCarbon(mixed $value): ?CarbonInterface
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return null;
    }

    protected function subjectTypeLabel(string $subjectType): string
    {
        return match ($subjectType) {
            'event' => __('Events'),
            'institution' => __('Institutions'),
            'speaker' => __('Speakers'),
            'series' => __('Series'),
            'reference' => __('References'),
            'search' => __('Search Results'),
            default => __('Pages'),
        };
    }

    protected function outcomeTypeLabel(string $outcomeType): string
    {
        return match ($outcomeType) {
            'signup' => __('Signups'),
            'event_registration' => __('Event registrations'),
            'event_checkin' => __('Event check-ins'),
            'event_submission' => __('Event submissions'),
            'event_save' => __('Event saves'),
            'event_interest' => __('Interested responses'),
            'event_going' => __('Going responses'),
            'institution_follow' => __('Institution follows'),
            'speaker_follow' => __('Speaker follows'),
            'series_follow' => __('Series follows'),
            'reference_follow' => __('Reference follows'),
            'saved_search_created' => __('Saved searches created'),
            default => str($outcomeType)->replace('_', ' ')->headline()->toString(),
        };
    }

    /**
     * @return Collection<int, DawahShareOutcome>
     */
    public function recentOutcomesForUser(User $user, int $limit = 12): Collection
    {
        return DawahShareOutcome::query()
            ->where('sharer_user_id', $user->id)
            ->with('link')
            ->latest('occurred_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return array{
     *     outbound_shares: int,
     *     visits: int,
     *     unique_visitors: int,
     *     outcomes: int,
     *     signups: int,
     *     event_registrations: int,
     *     event_checkins: int,
     *     event_submissions: int
     * }
     */
    public function summaryForLink(DawahShareLink $link): array
    {
        return [
            'outbound_shares' => $link->shareEvents()->where('event_type', 'outbound_click')->count(),
            'visits' => $link->visits()->count(),
            'unique_visitors' => $link->visits()->distinct('visitor_key')->count('visitor_key'),
            'outcomes' => $link->outcomes()->count(),
            'signups' => $link->outcomes()->where('outcome_type', 'signup')->count(),
            'event_registrations' => $link->outcomes()->where('outcome_type', 'event_registration')->count(),
            'event_checkins' => $link->outcomes()->where('outcome_type', 'event_checkin')->count(),
            'event_submissions' => $link->outcomes()->where('outcome_type', 'event_submission')->count(),
        ];
    }

    /**
     * @return Collection<int, array{
     *     provider: string,
     *     label: string,
     *     outbound_shares: int,
     *     visits: int,
     *     unique_visitors: int,
     *     outcomes: int,
     *     signups: int,
     *     event_registrations: int,
     *     event_checkins: int,
     *     event_submissions: int
     * }>
     */
    public function providerBreakdownForLink(DawahShareLink $link): Collection
    {
        return $this->providerBreakdown(
            $link->shareEvents()->getQuery(),
            $link->visits()->getQuery(),
            $link->outcomes()->getQuery(),
        );
    }

    /**
     * @return Collection<int, DawahShareVisit>
     */
    public function recentVisitsForLink(DawahShareLink $link, int $limit = 20): Collection
    {
        return $link->visits()
            ->latest('occurred_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, DawahShareOutcome>
     */
    public function recentOutcomesForLink(DawahShareLink $link, int $limit = 20): Collection
    {
        return $link->outcomes()
            ->latest('occurred_at')
            ->limit($limit)
            ->get();
    }

    /**
     * @param  Builder<DawahShareShareEvent>  $shareEvents
     * @param  Builder<DawahShareVisit>  $visits
     * @param  Builder<DawahShareOutcome>  $outcomes
     * @return Collection<int, array{
     *     provider: string,
     *     label: string,
     *     outbound_shares: int,
     *     visits: int,
     *     unique_visitors: int,
     *     outcomes: int,
     *     signups: int,
     *     event_registrations: int,
     *     event_checkins: int,
     *     event_submissions: int
     * }>
     */
    protected function providerBreakdown(Builder $shareEvents, Builder $visits, Builder $outcomes): Collection
    {
        $providers = app(DawahShareService::class)->supportedProviders();

        return collect($providers)
            ->map(function (string $provider) use ($shareEvents, $visits, $outcomes): array {
                return [
                    'provider' => $provider,
                    'label' => $this->shareProviderLabel($provider),
                    'outbound_shares' => (clone $shareEvents)
                        ->where('event_type', 'outbound_click')
                        ->where('provider', $provider)
                        ->count(),
                    'visits' => $this->filterByShareProvider(clone $visits, $provider)
                        ->count(),
                    'unique_visitors' => $this->filterByShareProvider(clone $visits, $provider)
                        ->distinct('visitor_key')
                        ->count('visitor_key'),
                    'outcomes' => $this->filterByShareProvider(clone $outcomes, $provider)
                        ->count(),
                    'signups' => $this->filterByShareProvider(clone $outcomes, $provider)
                        ->where('outcome_type', 'signup')
                        ->count(),
                    'event_registrations' => $this->filterByShareProvider(clone $outcomes, $provider)
                        ->where('outcome_type', 'event_registration')
                        ->count(),
                    'event_checkins' => $this->filterByShareProvider(clone $outcomes, $provider)
                        ->where('outcome_type', 'event_checkin')
                        ->count(),
                    'event_submissions' => $this->filterByShareProvider(clone $outcomes, $provider)
                        ->where('outcome_type', 'event_submission')
                        ->count(),
                ];
            })
            ->filter(fn (array $provider): bool => collect([
                $provider['outbound_shares'],
                $provider['visits'],
                $provider['outcomes'],
            ])->some(fn (int $count): bool => $count > 0))
            ->sort(function (array $left, array $right): int {
                return [
                    $right['outcomes'],
                    $right['visits'],
                    $right['outbound_shares'],
                ] <=> [
                    $left['outcomes'],
                    $left['visits'],
                    $left['outbound_shares'],
                ];
            })
            ->values();
    }

    protected function filterByShareProvider(Builder $query, string $provider): Builder
    {
        $connection = $query->getModel()->getConnection();
        $driver = $connection->getDriverName();

        return $query->whereHas('attribution', function (Builder $builder) use ($driver, $provider): void {
            if ($driver === 'sqlite') {
                $builder->whereRaw("json_extract(metadata, '$.share_provider') = ?", [$provider]);

                return;
            }

            $builder->where('metadata->share_provider', $provider);
        });
    }

    protected function shareProviderLabel(string $provider): string
    {
        return match ($provider) {
            'whatsapp' => __('WhatsApp'),
            'telegram' => __('Telegram'),
            'line' => __('LINE'),
            'facebook' => __('Facebook'),
            'x' => __('X'),
            'instagram' => __('Instagram'),
            'tiktok' => __('TikTok'),
            'email' => __('Email'),
            default => str($provider)->headline()->toString(),
        };
    }
}
