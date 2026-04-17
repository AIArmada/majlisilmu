<?php

namespace App\Http\Controllers\Api;

use App\Data\Api\Event\EventPayloadData;
use App\Data\Api\Frontend\Search\EventListData;
use App\Enums\EventKeyPersonRole;
use App\Enums\EventPrayerTime;
use App\Enums\EventVisibility;
use App\Enums\PrayerReference;
use App\Enums\TimingMode;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\Signals\ProductSignalsService;
use App\Support\Api\ApiPagination;
use App\Support\Timezone\UserDateTimeFormatter;
use Dedoc\Scramble\Attributes\Endpoint;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class EventController extends Controller
{
    public function __construct(
        private readonly ProductSignalsService $productSignalsService,
    ) {}

    /**
     * @var list<string>
     */
    private const array PUBLIC_STATUSES = Event::PUBLIC_STATUSES;

    /**
     * List events with filtering, sorting, and includes.
     *
     * Example API calls:
     * /api/v1/events?filter[status]=approved
     * /api/v1/events?filter[event_format]=online
     * /api/v1/events?filter[starts_after]=2026-02-01
     * /api/v1/events?include=venue,speakers
     * /api/v1/events?sort=-starts_at
     * /api/v1/events?filter[search]=kuliah
     */
    public function index(Request $request): JsonResponse
    {
        /** @var list<AllowedFilter> $allowedFilters */
        $allowedFilters = [
            AllowedFilter::callback('status', function (Builder $query, mixed $value): void {
                $statuses = array_values(array_intersect($this->normalizeArrayFilter($value), self::PUBLIC_STATUSES));

                if ($statuses === []) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->whereIn('status', $statuses);
            }),
            AllowedFilter::exact('visibility'),
            AllowedFilter::exact('event_format'),
            AllowedFilter::exact('institution_id'),
            AllowedFilter::exact('venue_id'),
            AllowedFilter::callback('event_type', function (Builder $query, mixed $value): void {
                $eventTypes = $this->normalizeArrayFilter($value);

                if ($eventTypes === []) {
                    return;
                }

                $query->where(function (Builder $eventTypeQuery) use ($eventTypes): void {
                    foreach ($eventTypes as $eventType) {
                        $eventTypeQuery->orWhereJsonContains('event_type', $eventType);
                    }
                });
            }),
            AllowedFilter::callback('starts_after', function (Builder $query, mixed $value): void {
                $startsAfter = $this->parseDate($value, false);
                if ($startsAfter instanceof Carbon) {
                    $query->where('starts_at', '>=', $startsAfter);
                }
            }),
            AllowedFilter::callback('starts_before', function (Builder $query, mixed $value): void {
                $startsBefore = $this->parseDate($value, true);
                if ($startsBefore instanceof Carbon) {
                    $query->where('starts_at', '<=', $startsBefore);
                }
            }),
            AllowedFilter::callback('ends_after', function (Builder $query, mixed $value): void {
                $endsAfter = $this->parseDate($value, false);
                if ($endsAfter instanceof Carbon) {
                    $query->where('ends_at', '>=', $endsAfter);
                }
            }),
            AllowedFilter::callback('ends_before', function (Builder $query, mixed $value): void {
                $endsBefore = $this->parseDate($value, true);
                if ($endsBefore instanceof Carbon) {
                    $query->where('ends_at', '<=', $endsBefore);
                }
            }),
            AllowedFilter::callback('state_id', function (Builder $query, mixed $value): void {
                $stateIds = $this->normalizeArrayFilter($value);
                if ($stateIds === []) {
                    return;
                }

                $query->whereHas('venue.address', function (Builder $addressQuery) use ($stateIds): void {
                    $addressQuery->whereIn('state_id', $stateIds);
                });
            }),
            AllowedFilter::callback('district_id', function (Builder $query, mixed $value): void {
                $districtIds = $this->normalizeArrayFilter($value);
                if ($districtIds === []) {
                    return;
                }

                $query->whereHas('venue.address', function (Builder $addressQuery) use ($districtIds): void {
                    $addressQuery->whereIn('district_id', $districtIds);
                });
            }),
            AllowedFilter::callback('subdistrict_id', function (Builder $query, mixed $value): void {
                $subdistrictIds = $this->normalizeArrayFilter($value);
                if ($subdistrictIds === []) {
                    return;
                }

                $query->whereHas('venue.address', function (Builder $addressQuery) use ($subdistrictIds): void {
                    $addressQuery->whereIn('subdistrict_id', $subdistrictIds);
                });
            }),
            AllowedFilter::callback('city_id', function (Builder $query, mixed $value): void {
                $cityIds = $this->normalizeArrayFilter($value);
                if ($cityIds === []) {
                    return;
                }

                $query->whereHas('venue.address', function (Builder $addressQuery) use ($cityIds): void {
                    $addressQuery->whereIn('city_id', $cityIds);
                });
            }),
            AllowedFilter::callback('speaker', function (Builder $query, mixed $value): void {
                $speakerIds = $this->normalizeArrayFilter($value);
                if ($speakerIds === []) {
                    return;
                }

                $query->whereHas('speakers', function (Builder $speakerQuery) use ($speakerIds): void {
                    $speakerQuery->whereIn('speakers.id', $speakerIds);
                });
            }),
            AllowedFilter::callback('key_person_roles', function (Builder $query, mixed $value): void {
                $keyPersonRoles = $this->normalizeKeyPersonRoles($value);

                if ($keyPersonRoles === []) {
                    return;
                }

                $query->whereHas('keyPeople', function (Builder $keyPersonQuery) use ($keyPersonRoles): void {
                    $keyPersonQuery->whereIn('role', $keyPersonRoles);
                });
            }),
            AllowedFilter::callback('moderator_ids', function (Builder $query, mixed $value): void {
                $speakerIds = $this->normalizeArrayFilter($value);

                if ($speakerIds === []) {
                    return;
                }

                $query->whereHas('keyPeople', function (Builder $keyPersonQuery) use ($speakerIds): void {
                    $keyPersonQuery
                        ->where('role', EventKeyPersonRole::Moderator->value)
                        ->whereIn('speaker_id', $speakerIds);
                });
            }),
            AllowedFilter::callback('imam_ids', function (Builder $query, mixed $value): void {
                $speakerIds = $this->normalizeArrayFilter($value);

                if ($speakerIds === []) {
                    return;
                }

                $query->whereHas('keyPeople', function (Builder $keyPersonQuery) use ($speakerIds): void {
                    $keyPersonQuery
                        ->where('role', EventKeyPersonRole::Imam->value)
                        ->whereIn('speaker_id', $speakerIds);
                });
            }),
            AllowedFilter::callback('khatib_ids', function (Builder $query, mixed $value): void {
                $speakerIds = $this->normalizeArrayFilter($value);

                if ($speakerIds === []) {
                    return;
                }

                $query->whereHas('keyPeople', function (Builder $keyPersonQuery) use ($speakerIds): void {
                    $keyPersonQuery
                        ->where('role', EventKeyPersonRole::Khatib->value)
                        ->whereIn('speaker_id', $speakerIds);
                });
            }),
            AllowedFilter::callback('bilal_ids', function (Builder $query, mixed $value): void {
                $speakerIds = $this->normalizeArrayFilter($value);

                if ($speakerIds === []) {
                    return;
                }

                $query->whereHas('keyPeople', function (Builder $keyPersonQuery) use ($speakerIds): void {
                    $keyPersonQuery
                        ->where('role', EventKeyPersonRole::Bilal->value)
                        ->whereIn('speaker_id', $speakerIds);
                });
            }),
            AllowedFilter::callback('series', function (Builder $query, mixed $value): void {
                $seriesIds = $this->normalizeArrayFilter($value);
                if ($seriesIds === []) {
                    return;
                }

                $query->whereHas('series', function (Builder $seriesQuery) use ($seriesIds): void {
                    $seriesQuery->whereIn('series.id', $seriesIds);
                });
            }),
            AllowedFilter::callback('search', function (Builder $query, mixed $value): void {
                $searchTerm = is_string($value) ? trim($value) : '';
                if ($searchTerm === '') {
                    return;
                }

                $operator = $this->databaseLikeOperator();
                $query->where(function (Builder $searchQuery) use ($searchTerm, $operator): void {
                    $searchQuery
                        ->where('title', $operator, "%{$searchTerm}%")
                        ->orWhereRaw($this->descriptionSearchSql($operator), ["%{$searchTerm}%"]);
                });
            }),
            AllowedFilter::callback('prayer_time', function (Builder $query, mixed $value) use ($request): void {
                $prayerTime = is_string($value) ? trim($value) : '';

                if ($prayerTime === '') {
                    return;
                }

                $prayerTimeGroup = $this->normalizePrayerTimeGroup($prayerTime);

                if ($prayerTimeGroup !== null) {
                    $this->applyPrayerTimeGroupFilter($query, $prayerTimeGroup, $request);

                    return;
                }

                $enum = EventPrayerTime::tryFrom(Str::lower($prayerTime));

                if ($enum instanceof EventPrayerTime) {
                    $prayerTime = $enum->getLabel();
                }

                $normalized = Str::lower($prayerTime);
                $operator = $this->databaseLikeOperator();

                $query
                    ->where('timing_mode', 'prayer_relative')
                    ->where(function (Builder $prayerQuery) use ($normalized, $operator): void {
                        $prayerQuery->whereRaw('LOWER(prayer_display_text) '.$operator.' ?', ["%{$normalized}%"]);

                        $reference = $this->resolvePrayerReference($normalized);

                        if ($reference !== null) {
                            $prayerQuery->orWhere('prayer_reference', $reference);
                        }
                    });
            }),
        ];
        /** @var list<string> $allowedIncludes */
        $allowedIncludes = [
            'venue',
            'venue.address',
            'venue.address.district',
            'venue.address.subdistrict',
            'institution',
            'keyPeople',
            'keyPeople.speaker',
            'speakers',
            'series',
            'mediaLinks',
            'settings',
            'languages',
            'address',
            'address.state',
            'address.district',
            'address.subdistrict',
            'address.city',
        ];
        /** @var list<string> $allowedSorts */
        $allowedSorts = [
            'title',
            'starts_at',
            'ends_at',
            'created_at',
            'updated_at',
            'views_count',
        ];

        $events = QueryBuilder::for(Event::query()->with([
            'institution.address',
            'institution.media' => fn ($query) => $query->whereIn('collection_name', ['logo', 'cover']),
            'venue.address',
            'speakers.media' => fn ($query) => $query->where('collection_name', 'avatar'),
            'media' => fn ($query) => $query->where('collection_name', 'poster'),
            'references',
        ]))
            ->allowedFilters(...$allowedFilters)
            ->allowedIncludes(...$allowedIncludes)
            ->allowedSorts(...$allowedSorts)
            ->defaultSort('-starts_at')
            ->where('is_active', true)
            ->whereIn('status', self::PUBLIC_STATUSES)
            ->where('visibility', 'public')
            ->paginate(ApiPagination::normalizePerPage($request->integer('per_page', 20), default: 20, max: 50))
            ->appends($request->query());

        /** @var array<string, mixed> $payload */
        $payload = [
            'current_page' => $events->currentPage(),
            'data' => $events->getCollection()
                ->map(fn (Event $event): array => $this->serializeEventListPayload($event))
                ->all(),
            'first_page_url' => $events->url(1),
            'from' => $events->firstItem(),
            'last_page' => $events->lastPage(),
            'last_page_url' => $events->url($events->lastPage()),
            'links' => $events->linkCollection()->toArray(),
            'next_page_url' => $events->nextPageUrl(),
            'path' => $events->path(),
            'per_page' => $events->perPage(),
            'prev_page_url' => $events->previousPageUrl(),
            'to' => $events->lastItem(),
            'total' => $events->total(),
        ];

        $user = $request->user();

        $this->productSignalsService->recordSearchExecuted(
            user: $user instanceof User ? $user : null,
            request: $request,
            surface: 'api.events.index',
            query: is_string(data_get($request->query(), 'filter.search')) ? data_get($request->query(), 'filter.search') : null,
            filters: is_array($request->query('filter')) ? $request->query('filter') : [],
            resultCount: $events->total(),
        );

        return response()->json($payload);
    }

    /**
     * Show a single event by ID or slug.
     */
    #[Endpoint(
        title: 'Get a public event',
        description: 'Returns the public event detail payload by slug or UUID, including all allowed related resources requested through `include` parameters.',
    )]
    public function show(Request $request, Event $event): JsonResponse
    {
        /** @var list<string> $allowedIncludes */
        $allowedIncludes = [
            'venue',
            'venue.address',
            'venue.address.state',
            'venue.address.district',
            'venue.address.subdistrict',
            'venue.address.city',
            'institution',
            'institution.address',
            'keyPeople',
            'keyPeople.speaker',
            'speakers',
            'series',
            'mediaLinks',
            'settings',
            'languages',
            'donationChannels',
            'address',
            'address.state',
            'address.district',
            'address.subdistrict',
            'address.city',
        ];

        $status = (string) $event->getRawOriginal('status');
        $visibility = (string) $event->getRawOriginal('visibility');

        abort_unless(
            $event->is_active
                && in_array($status, self::PUBLIC_STATUSES, true)
                && $visibility === EventVisibility::Public->value,
            404,
        );

        $event = QueryBuilder::for(Event::query()->with(['keyPeople.speaker', 'institution.media', 'media', 'references']))
            ->allowedIncludes(...$allowedIncludes)
            ->whereKey($event->getKey())
            ->firstOrFail();

        return response()->json(['data' => $this->serializeEventPayload($event)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEventPayload(Event $event): array
    {
        return EventPayloadData::fromModel($event)->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeEventListPayload(Event $event): array
    {
        return EventListData::fromModel($event)->toArray();
    }

    private function parseDate(mixed $value, bool $endOfDay): ?Carbon
    {
        $date = UserDateTimeFormatter::parseUserDateToUtc($value, $endOfDay);

        return $date instanceof Carbon ? $date : null;
    }

    private function resolvePrayerReference(string $prayerTime): ?string
    {
        if (Str::contains($prayerTime, ['jumaat', 'friday'])) {
            return 'friday_prayer';
        }

        if (Str::contains($prayerTime, ['maghrib'])) {
            return 'maghrib';
        }

        if (Str::contains($prayerTime, ['asar', 'asr'])) {
            return 'asr';
        }

        if (Str::contains($prayerTime, ['subuh', 'fajr'])) {
            return 'fajr';
        }

        if (Str::contains($prayerTime, ['zohor', 'zuhur', 'dhuhr'])) {
            return 'dhuhr';
        }

        if (Str::contains($prayerTime, ['isyak', 'isha'])) {
            return 'isha';
        }

        return null;
    }

    private function normalizePrayerTimeGroup(string $prayerTime): ?string
    {
        $normalized = Str::lower(trim($prayerTime));

        return match (true) {
            in_array($normalized, ['subuh', 'fajr'], true) => 'subuh',
            $normalized === 'dhuha' => 'dhuha',
            in_array($normalized, ['jumaat', 'friday'], true) => 'jumaat',
            in_array($normalized, ['zuhur', 'zohor', 'dhuhr'], true) => 'zuhur',
            in_array($normalized, ['asar', 'asr'], true) => 'asar',
            $normalized === 'maghrib' => 'maghrib',
            in_array($normalized, ['isya', 'isyak', 'isha'], true) => 'isya',
            default => null,
        };
    }

    /**
     * @param  Builder<Event>  $query
     */
    private function applyPrayerTimeGroupFilter(Builder $query, string $group, Request $request): void
    {
        match ($group) {
            'subuh' => $this->applyPrayerReferenceOrLabelFilter($query, PrayerReference::Fajr, ['subuh', 'fajr']),
            'jumaat' => $this->applyPrayerReferenceOrLabelFilter($query, PrayerReference::FridayPrayer, ['jumaat', 'friday']),
            'zuhur' => $this->applyPrayerReferenceOrLabelFilter($query, PrayerReference::Dhuhr, ['zohor', 'zuhur', 'dhuhr']),
            'asar' => $this->applyPrayerReferenceOrLabelFilter($query, PrayerReference::Asr, ['asar', 'asr']),
            'maghrib' => $this->applyPrayerReferenceOrLabelFilter($query, PrayerReference::Maghrib, ['maghrib']),
            'isya' => $this->applyPrayerReferenceOrLabelFilter($query, PrayerReference::Isha, ['isya', 'isyak', 'isha']),
            'dhuha' => $this->applyDhuhaPrayerTimeFilter($query, $request),
            default => null,
        };
    }

    /**
     * @param  list<string>  $terms
     */
    /**
     * @param  Builder<Event>  $query
     * @param  list<string>  $terms
     */
    private function applyPrayerReferenceOrLabelFilter(Builder $query, PrayerReference $reference, array $terms): void
    {
        $operator = $this->databaseLikeOperator();

        $query->where(function (Builder $prayerQuery) use ($reference, $terms, $operator): void {
            $prayerQuery->where('prayer_reference', $reference->value)
                ->orWhere(function (Builder $labelQuery) use ($terms, $operator): void {
                    foreach ($terms as $index => $term) {
                        if ($index === 0) {
                            $labelQuery->whereRaw('LOWER(prayer_display_text) '.$operator.' ?', ["%{$term}%"]);

                            continue;
                        }

                        $labelQuery->orWhereRaw('LOWER(prayer_display_text) '.$operator.' ?', ["%{$term}%"]);
                    }
                });
        });
    }

    /**
     * @param  Builder<Event>  $query
     */
    private function applyDhuhaPrayerTimeFilter(Builder $query, Request $request): void
    {
        $operator = $this->databaseLikeOperator();
        $startsAtUserTimeSql = $this->startsAtUserTimeSqlExpression($this->userUtcOffsetMinutes($request));

        $query->where(function (Builder $dhuhaQuery) use ($operator, $startsAtUserTimeSql): void {
            $dhuhaQuery
                ->where(function (Builder $relativeQuery) use ($operator): void {
                    $relativeQuery
                        ->where('timing_mode', TimingMode::PrayerRelative->value)
                        ->where(function (Builder $labelQuery) use ($operator): void {
                            $labelQuery->whereRaw('LOWER(prayer_display_text) '.$operator.' ?', ['%dhuha%'])
                                ->orWhereRaw('LOWER(prayer_display_text) '.$operator.' ?', ['%pagi%'])
                                ->orWhereRaw('LOWER(prayer_display_text) '.$operator.' ?', ['%morning%']);
                        })
                        ->where(function (Builder $excludeQuery): void {
                            $excludeQuery->whereNull('prayer_reference')
                                ->orWhereNotIn('prayer_reference', [
                                    PrayerReference::Fajr->value,
                                    PrayerReference::Dhuhr->value,
                                    PrayerReference::FridayPrayer->value,
                                ]);
                        });
                })
                ->orWhere(function (Builder $absoluteQuery) use ($startsAtUserTimeSql): void {
                    $absoluteQuery
                        ->where('timing_mode', TimingMode::Absolute->value)
                        ->whereRaw("{$startsAtUserTimeSql} >= ?", ['07:30'])
                        ->whereRaw("{$startsAtUserTimeSql} < ?", ['11:30']);
                });
        });
    }

    /**
     * @return list<string>
     */
    private function normalizeArrayFilter(mixed $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value) && str_contains($value, ',')) {
            $value = array_map(trim(...), explode(',', $value));
        }

        $values = is_array($value) ? $value : [$value];

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => (string) $item, $values),
            static fn (string $item): bool => $item !== ''
        ));
    }

    /**
     * @return list<string>
     */
    private function normalizeKeyPersonRoles(mixed $value): array
    {
        return array_values(array_filter(
            array_map(
                static fn (string $role): ?string => EventKeyPersonRole::tryFrom($role)?->value,
                $this->normalizeArrayFilter($value)
            )
        ));
    }

    private function databaseLikeOperator(): string
    {
        return $this->databaseDriver() === 'pgsql' ? 'ILIKE' : 'LIKE';
    }

    private function descriptionSearchSql(string $operator): string
    {
        return match ($this->databaseDriver()) {
            'pgsql' => "COALESCE(description::text, '') {$operator} ?",
            'mysql', 'mariadb' => "COALESCE(CAST(description AS CHAR), '') {$operator} ?",
            default => "COALESCE(CAST(description AS TEXT), '') {$operator} ?",
        };
    }

    private function databaseDriver(): string
    {
        /** @var Connection $connection */
        $connection = Event::query()->getConnection();

        return $connection->getDriverName();
    }

    private function startsAtUserTimeSqlExpression(int $offsetMinutes): string
    {
        $safeOffsetMinutes = $offsetMinutes;

        return match ($this->databaseDriver()) {
            'pgsql' => "to_char(events.starts_at + interval '{$safeOffsetMinutes} minutes', 'HH24:MI')",
            'mysql', 'mariadb' => "DATE_FORMAT(DATE_ADD(events.starts_at, INTERVAL {$safeOffsetMinutes} MINUTE), '%H:%i')",
            default => "strftime('%H:%M', datetime(events.starts_at, '{$safeOffsetMinutes} minutes'))",
        };
    }

    private function userUtcOffsetMinutes(?Request $request = null): int
    {
        return now(UserDateTimeFormatter::resolveTimezone($request))->utcOffset();
    }
}
