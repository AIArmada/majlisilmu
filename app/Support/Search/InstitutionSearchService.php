<?php

namespace App\Support\Search;

use App\Models\Institution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class InstitutionSearchService
{
    private const int PUBLIC_SEARCH_CACHE_TTL = 600;

    private const string PUBLIC_SEARCH_CACHE_VERSION_KEY = 'institution_search_public_version_v1';

    /**
     * @param  Builder<Institution>  $query
     * @return Builder<Institution>
     */
    public function applySearch(Builder $query, string $search): Builder
    {
        $normalizedSearch = $this->normalizedSearch($search);

        if ($normalizedSearch === null) {
            return $query->whereRaw('1 = 0');
        }

        $operator = DB::connection($query->getModel()->getConnectionName())->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
        $collapsedWildcardSearch = '%'.str_replace(' ', '%', $normalizedSearch).'%';
        $searchTokens = array_values(array_filter(explode(' ', $normalizedSearch), static fn (string $token): bool => $token !== ''));

        return $query->where(function (Builder $innerQuery) use ($normalizedSearch, $operator, $collapsedWildcardSearch, $searchTokens): void {
            $innerQuery->searchNameOrNickname($normalizedSearch)
                ->orWhere('institutions.description', $operator, "%{$normalizedSearch}%")
                ->orWhere('institutions.description', $operator, $collapsedWildcardSearch);

            if (count($searchTokens) < 2) {
                return;
            }

            $innerQuery->orWhere(function (Builder $tokenQuery) use ($searchTokens, $operator): void {
                foreach ($searchTokens as $token) {
                    if (mb_strlen($token) < 2) {
                        continue;
                    }

                    $tokenQuery->where(function (Builder $tokenMatchQuery) use ($token, $operator): void {
                        $tokenMatchQuery->searchNameOrNickname($token)
                            ->orWhere('institutions.description', $operator, "%{$token}%");
                    });
                }
            });
        });
    }

    /**
     * @return list<string>
     */
    public function publicSearchIds(string $search): array
    {
        $normalizedSearch = $this->normalizedSearch($search);

        if ($normalizedSearch === null) {
            return [];
        }

        $cacheKey = sprintf(
            'institution_search_public:%s:%s',
            $this->publicSearchCacheVersion(),
            md5($normalizedSearch),
        );

        /** @var list<string> $ids */
        $ids = Cache::remember($cacheKey, self::PUBLIC_SEARCH_CACHE_TTL, function () use ($normalizedSearch): array {
            return Institution::query()
                ->active()
                ->where('status', 'verified')
                ->select('institutions.id')
                ->tap(fn (Builder $query): Builder => $this->applySearch($query, $normalizedSearch))
                ->orderBy('name')
                ->pluck('institutions.id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->values()
                ->all();
        });

        return $ids;
    }

    /**
     * @return list<string>
     */
    public function publicFuzzySearchIds(string $search): array
    {
        $normalizedSearch = $this->normalizedSearch($search);

        if ($normalizedSearch === null) {
            return [];
        }

        $cacheKey = sprintf(
            'institution_search_public_fuzzy:%s:%s',
            $this->publicSearchCacheVersion(),
            md5($normalizedSearch),
        );

        /** @var list<string> $ids */
        $ids = Cache::remember($cacheKey, self::PUBLIC_SEARCH_CACHE_TTL, function () use ($normalizedSearch): array {
            return Institution::query()
                ->active()
                ->where('status', 'verified')
                ->select(['id', 'name', 'nickname', 'description'])
                ->get()
                ->map(function (Institution $institution) use ($normalizedSearch): array {
                    $candidates = array_values(array_filter([
                        $this->normalizeText((string) $institution->name),
                        $this->normalizeText((string) $institution->nickname),
                        $this->normalizeText((string) $institution->description),
                    ], static fn (string $candidate): bool => $candidate !== ''));

                    $scoreCandidates = [];

                    foreach ($candidates as $candidate) {
                        $scoreCandidates[] = $this->fuzzyComparable($normalizedSearch, $candidate)
                            ? $this->similarityScore($normalizedSearch, $candidate)
                            : 0.0;

                        $tokens = array_values(array_filter(
                            explode(' ', $candidate),
                            static fn (string $token): bool => mb_strlen($token) >= 2
                        ));

                        foreach ($tokens as $token) {
                            $scoreCandidates[] = $this->fuzzyComparable($normalizedSearch, $token)
                                ? $this->similarityScore($normalizedSearch, $token)
                                : 0.0;
                        }
                    }

                    return [
                        'id' => (string) $institution->id,
                        'score' => $scoreCandidates === [] ? 0.0 : max($scoreCandidates),
                    ];
                })
                ->filter(static fn (array $candidate): bool => $candidate['score'] >= 0.70)
                ->sortByDesc('score')
                ->pluck('id')
                ->values()
                ->all();
        });

        return $ids;
    }

    public function bustPublicSearchCache(): void
    {
        Cache::put(
            self::PUBLIC_SEARCH_CACHE_VERSION_KEY,
            $this->publicSearchCacheVersion() + 1,
            now()->addDays(30),
        );
    }

    public function normalizedSearch(string $search): ?string
    {
        $normalized = $this->normalizeText($search);

        return $normalized === '' ? null : $normalized;
    }

    private function publicSearchCacheVersion(): int
    {
        return (int) Cache::get(self::PUBLIC_SEARCH_CACHE_VERSION_KEY, 1);
    }

    private function fuzzyComparable(string $search, string $candidate): bool
    {
        if ($search === '' || $candidate === '') {
            return false;
        }

        return mb_substr($search, 0, 1) === mb_substr($candidate, 0, 1);
    }

    private function normalizeText(string $value): string
    {
        return (string) Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/[^a-z0-9\s]+/u', ' ')
            ->replaceMatches('/\s+/u', ' ')
            ->trim();
    }

    private function similarityScore(string $search, string $candidate): float
    {
        if ($search === '' || $candidate === '') {
            return 0.0;
        }

        $distance = levenshtein($search, $candidate);
        $maxLength = max(mb_strlen($search), mb_strlen($candidate));
        $distanceScore = $maxLength > 0 ? 1 - ($distance / $maxLength) : 0.0;

        similar_text($search, $candidate, $similarityPercent);
        $similarityScore = $similarityPercent / 100;

        return max($distanceScore, $similarityScore);
    }
}
