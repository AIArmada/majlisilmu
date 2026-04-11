<?php

namespace App\Http\Controllers\Api;

use App\Enums\EventKeyPersonRole;
use App\Enums\EventPrayerTime;
use App\Enums\EventVisibility;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\Signals\ProductSignalsService;
use App\Support\Timezone\UserDateTimeFormatter;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
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
            AllowedFilter::callback('prayer_time', function (Builder $query, mixed $value): void {
                $prayerTime = is_string($value) ? trim($value) : '';

                if ($prayerTime === '') {
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

        $events = QueryBuilder::for(Event::query()->with(['keyPeople.speaker', 'institution.media', 'media']))
            ->allowedFilters(...$allowedFilters)
            ->allowedIncludes(...$allowedIncludes)
            ->allowedSorts(...$allowedSorts)
            ->defaultSort('-starts_at')
            ->where('is_active', true)
            ->whereIn('status', self::PUBLIC_STATUSES)
            ->where('visibility', 'public')
            ->paginate((int) $request->input('per_page', 20))
            ->appends($request->query());

        /** @var array<string, mixed> $payload */
        $payload = $events->toArray();
        $payload['data'] = $events->getCollection()
            ->map(fn (Event $event): array => $this->serializeEventPayload($event))
            ->all();

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

        $event = QueryBuilder::for(Event::query()->with(['keyPeople.speaker', 'institution.media', 'media']))
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
        $posterUrl = $this->preferredMediaUrl($event->getFirstMedia('poster'), ['preview', 'card', 'thumb']);

        return [
            ...$event->toArray(),
            'card_image_url' => $event->card_image_url,
            'poster_url' => $posterUrl,
            'has_poster' => $event->hasMedia('poster'),
        ];
    }

    /**
     * @param  list<string>  $preferredConversions
     */
    private function preferredMediaUrl(?Media $media, array $preferredConversions = []): ?string
    {
        if (! $media instanceof Media) {
            return null;
        }

        $availableUrl = $preferredConversions === []
            ? $media->getUrl()
            : $media->getAvailableUrl($preferredConversions);

        if ($availableUrl !== '') {
            return $availableUrl;
        }

        $originalUrl = $media->getUrl();

        return $originalUrl !== '' ? $originalUrl : null;
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
}
