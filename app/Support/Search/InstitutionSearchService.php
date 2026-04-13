<?php

namespace App\Support\Search;

use App\Models\Institution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class InstitutionSearchService
{
    private const int PUBLIC_SEARCH_CACHE_TTL = 600;

    private const string PUBLIC_SEARCH_CACHE_VERSION_KEY = 'institution_search_public_version_v2';

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

        if ($this->shouldUseScoutSearch()) {
            try {
                return $this->applyScoutSearch($query, $normalizedSearch);
            } catch (\Throwable $exception) {
                $this->logScoutFallback('Institution Typesense search failed, falling back to database search', $exception, $normalizedSearch);
            }
        }

        return $this->applyDatabaseSearch($query, $normalizedSearch);
    }

    /**
     * @param  Builder<Institution>  $query
     * @return Builder<Institution>
     */
    private function applyDatabaseSearch(Builder $query, string $normalizedSearch): Builder
    {

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
            if ($this->shouldUseTypesenseSearch()) {
                try {
                    return $this->searchIdsWithScout($normalizedSearch, [
                        'filter_by' => 'is_active:=true && status:=verified',
                        'num_typos' => 0,
                    ]);
                } catch (\Throwable $exception) {
                    $this->logScoutFallback('Institution Typesense public search failed, falling back to database search', $exception, $normalizedSearch);
                }
            }

            if ($this->shouldUseScoutDatabaseSearch()) {
                return $this->publicSearchIdsFromScoutDatabase($normalizedSearch);
            }

            return $this->publicSearchIdsFromDatabase($normalizedSearch);
        });

        return $ids;
    }

    /**
     * @return list<string>
     */
    private function publicSearchIdsFromScoutDatabase(string $normalizedSearch): array
    {
        return Institution::search($normalizedSearch)
            ->query(function (Builder $query): Builder {
                return $query
                    ->where('institutions.is_active', true)
                    ->where('status', 'verified')
                    ->limit($this->typesenseResultLimit());
            })
            ->get()
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function publicSearchIdsFromDatabase(string $normalizedSearch): array
    {
        return Institution::query()
            ->active()
            ->where('status', 'verified')
            ->select('institutions.id')
            ->tap(fn (Builder $query): Builder => $this->applyDatabaseSearch($query, $normalizedSearch))
            ->orderBy('name')
            ->pluck('institutions.id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
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

        $minimumScore = $this->minimumFuzzyScore($normalizedSearch);

        $cacheKey = sprintf(
            'institution_search_public_fuzzy:%s:%s',
            $this->publicSearchCacheVersion(),
            md5($normalizedSearch),
        );

        /** @var list<string> $ids */
        $ids = Cache::remember($cacheKey, self::PUBLIC_SEARCH_CACHE_TTL, function () use ($minimumScore, $normalizedSearch): array {
            if ($this->shouldUseTypesenseSearch()) {
                try {
                    return $this->searchIdsWithScout($normalizedSearch, [
                        'filter_by' => 'is_active:=true && status:=verified',
                        'prioritize_exact_match' => true,
                    ]);
                } catch (\Throwable $exception) {
                    $this->logScoutFallback('Institution Typesense fuzzy search failed, falling back to database fuzzy search', $exception, $normalizedSearch);
                }
            }

            return $this->publicFuzzySearchIdsFromDatabase($normalizedSearch, $minimumScore);
        });

        return $ids;
    }

    /**
     * @return list<string>
     */
    private function publicFuzzySearchIdsFromDatabase(string $normalizedSearch, float $minimumScore): array
    {
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
                    $scoreCandidates[] = $this->fuzzyScore($normalizedSearch, $candidate);

                    $tokens = array_values(array_filter(
                        explode(' ', $candidate),
                        static fn (string $token): bool => mb_strlen($token) >= 2
                    ));

                    foreach ($tokens as $token) {
                        $scoreCandidates[] = $this->fuzzyScore($normalizedSearch, $token);
                    }
                }

                return [
                    'id' => (string) $institution->id,
                    'score' => $scoreCandidates === [] ? 0.0 : max($scoreCandidates),
                ];
            })
            ->filter(static fn (array $candidate): bool => $candidate['score'] >= $minimumScore)
            ->sortByDesc('score')
            ->pluck('id')
            ->values()
            ->all();
    }

    protected function shouldUseScoutSearch(): bool
    {
        return in_array($this->scoutDriver(), ['typesense', 'database'], true);
    }

    protected function shouldUseTypesenseSearch(): bool
    {
        return $this->scoutDriver() === 'typesense';
    }

    protected function shouldUseScoutDatabaseSearch(): bool
    {
        return $this->scoutDriver() === 'database';
    }

    /**
     * @param  Builder<Institution>  $query
     * @return Builder<Institution>
     */
    protected function applyScoutSearch(Builder $query, string $search): Builder
    {
        $ids = $this->searchIdsWithScout($search, [
            'num_typos' => 0,
        ]);

        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn($query->getModel()->qualifyColumn('id'), $ids);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return list<string>
     */
    protected function searchIdsWithScout(string $search, array $options = []): array
    {
        if ($this->scoutDriver() === 'database') {
            return Institution::search($search)
                ->query(fn (Builder $query): Builder => $query->limit($this->typesenseResultLimit()))
                ->get()
                ->pluck('id')
                ->map(static fn (mixed $id): string => (string) $id)
                ->values()
                ->all();
        }

        $rawResults = Institution::search($search)
            ->options([
                'query_by' => 'display_name,name,nickname,description,search_text',
                'per_page' => $this->typesenseResultLimit(),
                ...$options,
            ])
            ->raw();

        /** @var array<int, array<string, mixed>> $hits */
        $hits = is_array($rawResults) && is_array($rawResults['hits'] ?? null)
            ? $rawResults['hits']
            : [];

        return collect($hits)
            ->pluck('document.id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->values()
            ->all();
    }

    protected function typesenseResultLimit(): int
    {
        return max(50, (int) (config('scout.typesense.max_total_results') ?? 250));
    }

    protected function logScoutFallback(string $message, \Throwable $exception, string $search): void
    {
        Log::warning($message, [
            'error' => $exception->getMessage(),
            'search' => $search,
        ]);
    }

    protected function scoutDriver(): string
    {
        return (string) config('scout.driver');
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

    private function fuzzyScore(string $search, string $candidate): float
    {
        if (! $this->fuzzyComparable($search, $candidate)) {
            return 0.0;
        }

        return $this->similarityScore($search, $candidate);
    }

    private function fuzzyComparable(string $search, string $candidate): bool
    {
        if ($search === '' || $candidate === '') {
            return false;
        }

        if (mb_substr($search, 0, 1) !== mb_substr($candidate, 0, 1)) {
            return false;
        }

        return levenshtein($search, $candidate) <= $this->maximumFuzzyDistance($search);
    }

    private function minimumFuzzyScore(string $search): float
    {
        return mb_strlen($search) >= 6 ? 0.80 : 0.70;
    }

    private function maximumFuzzyDistance(string $search): int
    {
        return mb_strlen($search) >= 5 ? 2 : 1;
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
