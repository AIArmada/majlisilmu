<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Enums\ContactCategory;
use App\Enums\EventStructure;
use App\Enums\EventVisibility;
use App\Enums\SocialMediaPlatform;
use App\Models\Address;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Series;
use App\Models\SocialMedia;
use App\Models\Speaker;
use App\Models\User;
use App\Models\Venue;
use App\Services\EventSearchService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchController extends FrontendController
{
    public function __construct(
        private readonly EventSearchService $eventSearchService,
    ) {}

    public function search(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $search = $this->normalizedString($request->query('search'));
        $lat = $this->normalizedFloat($request->query('lat'));
        $lng = $this->normalizedFloat($request->query('lng'));
        $radius = max(1, min($request->integer('radius_km', 15), 100));
        $hasLocation = $lat !== null && $lng !== null;

        $eventPaginator = $hasLocation
            ? $this->eventSearchService->searchNearbyWithQuery(
                query: $search,
                lat: $lat ?? 0.0,
                lng: $lng ?? 0.0,
                radiusKm: $radius,
                perPage: 6,
            )
            : $this->eventSearchService->search(
                query: $search,
                perPage: 6,
                sort: $search !== null ? 'relevance' : 'time',
            );

        $speakerQuery = $search !== null ? $this->speakerSearchQuery($search) : Speaker::query()->whereRaw('1 = 0');
        $institutionQuery = $search !== null ? $this->institutionSearchQuery($search) : Institution::query()->whereRaw('1 = 0');

        return response()->json([
            'data' => [
                'events' => [
                    'items' => collect($eventPaginator->items())->map(fn (Event $event): array => $this->eventListData($event))->all(),
                    'total' => $eventPaginator->total(),
                ],
                'speakers' => [
                    'items' => $speakerQuery->orderBy('name')->limit(4)->get()->map(fn (Speaker $speaker): array => $this->speakerListData($speaker))->all(),
                    'total' => (clone $speakerQuery)->count(),
                ],
                'institutions' => [
                    'items' => $institutionQuery->orderBy('name')->limit(4)->get()->map(fn (Institution $institution): array => $this->institutionListData($institution))->all(),
                    'total' => (clone $institutionQuery)->count(),
                ],
            ],
            'meta' => [
                'search' => $search,
                'lat' => $lat,
                'lng' => $lng,
                'radius_km' => $radius,
                'authenticated' => $user !== null,
            ],
        ]);
    }

    #[Group('Institution')]
    public function institutions(Request $request): JsonResponse
    {
        $search = $this->normalizedString($request->query('search'));
        $countryId = $request->integer('country_id');
        $stateId = $request->integer('state_id');
        $districtId = $request->integer('district_id');
        $subdistrictId = $request->integer('subdistrict_id');

        $query = Institution::query()
            ->where('status', 'verified')
            ->where('is_active', true)
            ->withCount(['events' => function (Builder $query): void {
                $query
                    ->where('events.is_active', true)
                    ->whereIn('events.status', Event::PUBLIC_STATUSES)
                    ->where('events.visibility', EventVisibility::Public)
                    ->where('events.event_structure', '!=', EventStructure::ParentProgram->value);
            }])
            ->with(['address.state', 'address.district', 'address.subdistrict', 'media']);

        $this->applyInstitutionLocationScope($query, $countryId, $stateId, $districtId, $subdistrictId);

        if ($search !== null) {
            $query = $this->applyInstitutionSearch($query, $search);
        }

        $institutions = $query
            ->orderBy('name')
            ->paginate($request->integer('per_page', 12));

        return response()->json([
            'data' => collect($institutions->items())->map(fn (Institution $institution): array => $this->institutionListData($institution))->all(),
            'meta' => [
                'pagination' => [
                    'page' => $institutions->currentPage(),
                    'per_page' => $institutions->perPage(),
                    'total' => $institutions->total(),
                ],
            ],
        ]);
    }

    #[Group('Speaker')]
    public function speakers(Request $request): JsonResponse
    {
        $search = $this->normalizedString($request->query('search'));

        $query = Speaker::query()
            ->where('status', 'verified')
            ->where('is_active', true)
            ->withCount(['events' => function (Builder $query): void {
                $query
                    ->where('events.is_active', true)
                    ->whereIn('events.status', Event::PUBLIC_STATUSES)
                    ->where('events.visibility', EventVisibility::Public)
                    ->where('events.event_structure', '!=', EventStructure::ParentProgram->value)
                    ->where('events.starts_at', '>=', now());
            }])
            ->with('media');

        if ($search !== null) {
            $this->applySpeakerSearchConstraint($query, $search);
        }

        $speakers = $query
            ->orderBy('name')
            ->paginate($request->integer('per_page', 12));

        return response()->json([
            'data' => collect($speakers->items())->map(fn (Speaker $speaker): array => $this->speakerListData($speaker))->all(),
            'meta' => [
                'pagination' => [
                    'page' => $speakers->currentPage(),
                    'per_page' => $speakers->perPage(),
                    'total' => $speakers->total(),
                ],
            ],
        ]);
    }

    #[Group('Institution')]
    public function showInstitution(Request $request, string $institutionKey): JsonResponse
    {
        $user = $this->currentUser($request);
        $record = Institution::query()
            ->with([
                'media',
                'address.state',
                'address.city',
                'address.district',
                'address.subdistrict',
                'address.country',
                'contacts',
                'socialMedia',
                'donationChannels.media',
                'speakers' => fn ($query) => $query->where('status', 'verified')->orderByPivot('is_primary', 'desc')->limit(12),
                'speakers.media',
                'spaces' => fn ($query) => $query->where('is_active', true),
                'languages',
            ])
            ->where(function (Builder $query) use ($institutionKey): void {
                $query->where('slug', $institutionKey);

                if (Str::isUuid($institutionKey)) {
                    $query->orWhere('id', $institutionKey);
                }
            })
            ->firstOrFail();

        abort_unless($user instanceof User ? $user->can('view', $record) : $record->status === 'verified', 404);

        return response()->json([
            'data' => [
                'institution' => [
                    'id' => $record->id,
                    'slug' => $record->slug,
                    'name' => $record->name,
                    'nickname' => $record->nickname,
                    'display_name' => $record->display_name,
                    'description' => $record->description,
                    'status' => $record->status,
                    'is_following' => $user?->isFollowing($record) ?? false,
                    'media' => [
                        'logo_url' => $record->getFirstMediaUrl('logo', 'thumb') ?: $record->getFirstMediaUrl('logo'),
                        'cover_url' => $record->getFirstMediaUrl('cover', 'banner') ?: $record->getFirstMediaUrl('cover'),
                    ],
                    'contacts' => $this->contactData($record->contacts),
                    'social_media' => $this->socialMediaData($record->socialMedia),
                ],
                'upcoming_events' => $record->events()
                    ->active()
                    ->where('starts_at', '>=', now())
                    ->with(['venue.address.state', 'venue.address.district', 'venue.address.subdistrict', 'speakers.media', 'keyPeople.speaker', 'media'])
                    ->orderBy('starts_at')
                    ->take(max(1, min($request->integer('upcoming_per_page', 6), 50)))
                    ->get()
                    ->map(fn (Event $event): array => $this->eventListData($event))
                    ->all(),
                'upcoming_total' => $record->events()->active()->where('starts_at', '>=', now())->count(),
                'past_events' => $record->events()
                    ->active()
                    ->where('starts_at', '<', now())
                    ->with(['venue.address.state', 'venue.address.district', 'venue.address.subdistrict', 'speakers.media', 'keyPeople.speaker', 'media'])
                    ->orderByDesc('starts_at')
                    ->take(max(1, min($request->integer('past_per_page', 6), 50)))
                    ->get()
                    ->map(fn (Event $event): array => $this->eventListData($event))
                    ->all(),
                'past_total' => $record->events()->active()->where('starts_at', '<', now())->count(),
            ],
        ]);
    }

    #[Group('Speaker')]
    public function showSpeaker(Request $request, string $speakerKey): JsonResponse
    {
        $user = $this->currentUser($request);
        $record = Speaker::query()
            ->with([
                'media',
                'contacts',
                'socialMedia',
                'address.state',
                'address.city',
                'address.district',
                'address.subdistrict',
                'address.country',
                'institutions' => fn ($query) => $query->orderByPivot('is_primary', 'desc')->limit(3),
                'institutions.media',
            ])
            ->where(function (Builder $query) use ($speakerKey): void {
                $query->where('slug', $speakerKey);

                if (Str::isUuid($speakerKey)) {
                    $query->orWhere('id', $speakerKey);
                }
            })
            ->firstOrFail();

        abort_unless($user instanceof User ? $user->can('view', $record) : ($record->is_active && $record->status === 'verified'), 404);

        return response()->json([
            'data' => [
                'speaker' => [
                    'id' => $record->id,
                    'slug' => $record->slug,
                    'name' => $record->name,
                    'formatted_name' => $record->formatted_name,
                    'bio' => $record->bio,
                    'status' => $record->status,
                    'is_active' => (bool) $record->is_active,
                    'is_following' => $user?->isFollowing($record) ?? false,
                    'media' => [
                        'avatar_url' => $record->getFirstMediaUrl('avatar', 'profile') ?: $record->getFirstMediaUrl('avatar'),
                        'cover_url' => $record->getFirstMediaUrl('cover', 'banner') ?: $record->getFirstMediaUrl('cover'),
                    ],
                    'contacts' => $this->contactData($record->contacts),
                    'social_media' => $this->socialMediaData($record->socialMedia),
                ],
                'upcoming_events' => $record->speakerEvents()
                    ->active()
                    ->where('starts_at', '>=', now())
                    ->with(['institution', 'institution.address.state', 'institution.address.district', 'institution.address.subdistrict', 'venue.address.state', 'venue.address.district', 'venue.address.subdistrict', 'media'])
                    ->orderBy('starts_at')
                    ->take(max(1, min($request->integer('upcoming_per_page', 10), 50)))
                    ->get()
                    ->map(fn (Event $event): array => $this->eventListData($event))
                    ->all(),
                'upcoming_total' => $record->speakerEvents()->active()->where('starts_at', '>=', now())->count(),
                'past_events' => $record->speakerEvents()
                    ->active()
                    ->where('starts_at', '<', now())
                    ->with(['institution', 'institution.address.state', 'institution.address.district', 'institution.address.subdistrict', 'venue.address.state', 'venue.address.district', 'venue.address.subdistrict', 'media'])
                    ->orderByDesc('starts_at')
                    ->take(max(1, min($request->integer('past_per_page', 10), 50)))
                    ->get()
                    ->map(fn (Event $event): array => $this->eventListData($event))
                    ->all(),
                'past_total' => $record->speakerEvents()->active()->where('starts_at', '<', now())->count(),
            ],
        ]);
    }

    #[Group('Venue')]
    public function showVenue(Request $request, string $venueKey): JsonResponse
    {
        $user = $this->currentUser($request);
        $canBypassVisibility = $user?->hasAnyRole(['super_admin', 'moderator']) ?? false;

        $record = Venue::query()
            ->with([
                'media',
                'address.state',
                'address.city',
                'address.district',
                'address.subdistrict',
                'address.country',
                'contacts',
                'socialMedia',
            ])
            ->where(function (Builder $query) use ($venueKey): void {
                $query->where('slug', $venueKey);

                if (Str::isUuid($venueKey)) {
                    $query->orWhere('id', $venueKey);
                }
            })
            ->firstOrFail();

        if (! $record->is_active || ($record->status !== 'verified' && ! $canBypassVisibility)) {
            abort(404);
        }

        return response()->json([
            'data' => [
                'venue' => [
                    'id' => $record->id,
                    'slug' => $record->slug,
                    'name' => $record->name,
                    'description' => $record->description,
                    'status' => $record->status,
                    'is_active' => (bool) $record->is_active,
                    'media' => [
                        'cover_url' => $record->getFirstMediaUrl('cover', 'banner') ?: $record->getFirstMediaUrl('cover'),
                    ],
                    'contacts' => $this->contactData($record->contacts),
                    'social_media' => $this->socialMediaData($record->socialMedia),
                ],
                'upcoming_events' => $record->events()
                    ->active()
                    ->where('starts_at', '>=', now())
                    ->with([
                        'institution.media',
                        'institution.address.state',
                        'institution.address.district',
                        'institution.address.subdistrict',
                        'speakers.media',
                        'keyPeople.speaker.media',
                        'media',
                    ])
                    ->orderBy('starts_at')
                    ->take(max(1, min($request->integer('upcoming_per_page', 8), 50)))
                    ->get()
                    ->map(fn (Event $event): array => $this->eventListData($event))
                    ->all(),
                'upcoming_total' => $record->events()->active()->where('starts_at', '>=', now())->count(),
                'past_events' => $record->events()
                    ->active()
                    ->where('starts_at', '<', now())
                    ->with([
                        'institution.media',
                        'institution.address.state',
                        'institution.address.district',
                        'institution.address.subdistrict',
                        'speakers.media',
                        'keyPeople.speaker.media',
                        'media',
                    ])
                    ->orderByDesc('starts_at')
                    ->take(max(1, min($request->integer('past_per_page', 8), 50)))
                    ->get()
                    ->map(fn (Event $event): array => $this->eventListData($event))
                    ->all(),
                'past_total' => $record->events()->active()->where('starts_at', '<', now())->count(),
            ],
        ]);
    }

    #[Group('Reference')]
    public function showReference(Request $request, string $referenceKey): JsonResponse
    {
        $user = $this->currentUser($request);

        $record = Reference::query()
            ->with(['media', 'socialMedia'])
            ->where(function (Builder $query) use ($referenceKey): void {
                $query->where('slug', $referenceKey);

                if (Str::isUuid($referenceKey)) {
                    $query->orWhere('id', $referenceKey);
                }
            })
            ->firstOrFail();

        abort_unless($record->is_active, 404);

        return response()->json([
            'data' => [
                'reference' => [
                    'id' => $record->id,
                    'slug' => $record->slug,
                    'title' => $record->title,
                    'author' => $record->author,
                    'type' => $record->type,
                    'publisher' => $record->publisher,
                    'publication_year' => $record->publication_year,
                    'description' => $record->description,
                    'is_active' => (bool) $record->is_active,
                    'is_following' => $user?->isFollowing($record) ?? false,
                    'media' => [
                        'front_cover_url' => $record->getFirstMediaUrl('front_cover', 'thumb') ?: $record->getFirstMediaUrl('front_cover'),
                        'back_cover_url' => $record->getFirstMediaUrl('back_cover', 'thumb') ?: $record->getFirstMediaUrl('back_cover'),
                    ],
                    'social_media' => $this->socialMediaData($record->socialMedia),
                ],
                'upcoming_events' => $record->events()
                    ->active()
                    ->where('starts_at', '>=', now())
                    ->with([
                        'institution',
                        'institution.address.state',
                        'institution.address.district',
                        'institution.address.subdistrict',
                        'venue.address.state',
                        'venue.address.district',
                        'venue.address.subdistrict',
                        'media',
                    ])
                    ->orderBy('starts_at', 'asc')
                    ->take(max(1, min($request->integer('upcoming_per_page', 10), 50)))
                    ->get()
                    ->map(fn (Event $event): array => $this->eventListData($event))
                    ->all(),
                'upcoming_total' => $record->events()->active()->where('starts_at', '>=', now())->count(),
                'past_events' => $record->events()
                    ->active()
                    ->where('starts_at', '<', now())
                    ->with([
                        'institution',
                        'institution.address.state',
                        'institution.address.district',
                        'institution.address.subdistrict',
                        'venue.address.state',
                        'venue.address.district',
                        'venue.address.subdistrict',
                        'media',
                    ])
                    ->orderByDesc('starts_at')
                    ->take(max(1, min($request->integer('past_per_page', 10), 50)))
                    ->get()
                    ->map(fn (Event $event): array => $this->eventListData($event))
                    ->all(),
                'past_total' => $record->events()->active()->where('starts_at', '<', now())->count(),
            ],
        ]);
    }

    #[Group('Series')]
    public function showSeries(Request $request, string $series): JsonResponse
    {
        $user = $this->currentUser($request);
        $canBypassVisibility = $user?->hasAnyRole(['super_admin', 'moderator']) ?? false;

        $record = Series::query()
            ->with(['media'])
            ->where(function (Builder $query) use ($series): void {
                $query->where('slug', $series);

                if (Str::isUuid($series)) {
                    $query->orWhere('id', $series);
                }
            })
            ->firstOrFail();

        if ($record->visibility !== 'public' && ! $canBypassVisibility) {
            abort(404);
        }

        return response()->json([
            'data' => [
                'series' => [
                    'id' => $record->id,
                    'slug' => $record->slug,
                    'title' => $record->title,
                    'description' => $record->description,
                    'visibility' => $record->visibility,
                    'is_following' => $user?->isFollowing($record) ?? false,
                    'media' => [
                        'cover_url' => $record->getFirstMediaUrl('cover', 'thumb') ?: $record->getFirstMediaUrl('cover'),
                    ],
                ],
                'upcoming_events' => $record->events()
                    ->active()
                    ->where('starts_at', '>=', now())
                    ->with([
                        'institution',
                        'institution.address.state',
                        'institution.address.district',
                        'institution.address.subdistrict',
                        'venue.address.state',
                        'venue.address.district',
                        'venue.address.subdistrict',
                        'media',
                    ])
                    ->orderBy('starts_at', 'asc')
                    ->take(max(1, min($request->integer('upcoming_per_page', 10), 50)))
                    ->get()
                    ->map(fn (Event $event): array => $this->eventListData($event))
                    ->all(),
                'upcoming_total' => $record->events()->active()->where('starts_at', '>=', now())->count(),
                'past_events' => $record->events()
                    ->active()
                    ->where('starts_at', '<', now())
                    ->with([
                        'institution',
                        'institution.address.state',
                        'institution.address.district',
                        'institution.address.subdistrict',
                        'venue.address.state',
                        'venue.address.district',
                        'venue.address.subdistrict',
                        'media',
                    ])
                    ->orderByDesc('starts_at')
                    ->take(max(1, min($request->integer('past_per_page', 10), 50)))
                    ->get()
                    ->map(fn (Event $event): array => $this->eventListData($event))
                    ->all(),
                'past_total' => $record->events()->active()->where('starts_at', '<', now())->count(),
            ],
        ]);
    }

    /**
     * @return Builder<Speaker>
     */
    private function speakerSearchQuery(string $search): Builder
    {
        $operator = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';
        $collapsedSearch = preg_replace('/\s+/u', ' ', trim($search)) ?? '';
        $collapsedWildcardSearch = '%'.str_replace(' ', '%', $collapsedSearch).'%';
        $searchTokens = array_values(array_filter(explode(' ', $collapsedSearch), static fn (string $token): bool => $token !== ''));

        return Speaker::query()
            ->active()
            ->where('status', 'verified')
            ->withCount(['events' => function (Builder $query): void {
                $query
                    ->where('events.is_active', true)
                    ->whereIn('events.status', Event::PUBLIC_STATUSES)
                    ->where('events.visibility', EventVisibility::Public)
                    ->where('events.event_structure', '!=', EventStructure::ParentProgram->value)
                    ->where('events.starts_at', '>=', now());
            }])
            ->with('media')
            ->where(function (Builder $query) use ($collapsedSearch, $collapsedWildcardSearch, $searchTokens, $operator): void {
                $query
                    ->where('name', $operator, "%{$collapsedSearch}%")
                    ->orWhere('name', $operator, $collapsedWildcardSearch);

                foreach ($searchTokens as $token) {
                    if (mb_strlen($token) < 2) {
                        continue;
                    }

                    $query->orWhere('name', $operator, "%{$token}%");
                }
            });
    }

    /**
     * @return Builder<Institution>
     */
    private function institutionSearchQuery(string $search): Builder
    {
        return Institution::query()
            ->active()
            ->where('status', 'verified')
            ->withCount(['events' => function (Builder $query): void {
                $query
                    ->where('events.is_active', true)
                    ->whereIn('events.status', Event::PUBLIC_STATUSES)
                    ->where('events.visibility', EventVisibility::Public)
                    ->where('events.event_structure', '!=', EventStructure::ParentProgram->value)
                    ->where('events.starts_at', '>=', now());
            }])
            ->with(['address.state', 'address.district', 'address.subdistrict', 'media'])
            ->searchNameOrNickname($search);
    }

    /**
     * @param  Builder<Institution>  $query
     */
    private function applyInstitutionLocationScope(Builder $query, ?int $countryId, ?int $stateId, ?int $districtId, ?int $subdistrictId): void
    {
        $query->whereHas('address', function (Builder $addressQuery) use ($countryId, $stateId, $districtId, $subdistrictId): void {
            if ($countryId !== null) {
                $addressQuery->where('country_id', $countryId);
            }

            if ($stateId !== null) {
                $addressQuery->where('state_id', $stateId);
            }

            if ($districtId !== null) {
                $addressQuery->where('district_id', $districtId);
            }

            if ($subdistrictId !== null) {
                $addressQuery->where('subdistrict_id', $subdistrictId);
            }
        });
    }

    /**
     * @param  Builder<Institution>  $query
     * @return Builder<Institution>
     */
    private function applyInstitutionSearch(Builder $query, string $search): Builder
    {
        $operator = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';
        $collapsedSearch = preg_replace('/\s+/u', ' ', trim($search)) ?? '';
        $collapsedWildcardSearch = '%'.str_replace(' ', '%', $collapsedSearch).'%';
        $searchTokens = array_values(array_filter(explode(' ', $collapsedSearch), static fn (string $token): bool => $token !== ''));

        return $query->where(function (Builder $innerQuery) use ($collapsedSearch, $operator, $collapsedWildcardSearch, $searchTokens): void {
            $innerQuery->searchNameOrNickname($collapsedSearch)
                ->orWhere('description', $operator, "%{$collapsedSearch}%")
                ->orWhere('description', $operator, $collapsedWildcardSearch);

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
                            ->orWhere('description', $operator, "%{$token}%");
                    });
                }
            });
        });
    }

    /**
     * @param  Builder<Speaker>  $query
     */
    private function applySpeakerSearchConstraint(Builder $query, string $search): void
    {
        $operator = config('database.default') === 'pgsql' ? 'ILIKE' : 'LIKE';
        $collapsedSearch = preg_replace('/\s+/u', ' ', trim($search)) ?? '';
        $collapsedWildcardSearch = '%'.str_replace(' ', '%', $collapsedSearch).'%';
        $searchTokens = array_values(array_filter(explode(' ', $collapsedSearch), static fn (string $token): bool => $token !== ''));

        $query->where(function (Builder $innerQuery) use ($collapsedSearch, $collapsedWildcardSearch, $searchTokens, $operator): void {
            $innerQuery
                ->where('name', $operator, "%{$collapsedSearch}%")
                ->orWhere('name', $operator, $collapsedWildcardSearch);

            foreach ($searchTokens as $token) {
                if (mb_strlen($token) < 2) {
                    continue;
                }

                $innerQuery->orWhere('name', $operator, "%{$token}%");
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function eventListData(Event $event): array
    {
        return [
            'id' => $event->id,
            'slug' => $event->slug,
            'title' => $event->title,
            'starts_at' => $this->optionalDateTimeString($event->starts_at),
            'ends_at' => $this->optionalDateTimeString($event->ends_at),
            'visibility' => $this->enumValue($event->visibility),
            'status' => (string) $event->status,
            'card_image_url' => $event->card_image_url,
            'institution' => $event->institution ? [
                'id' => $event->institution->id,
                'name' => $event->institution->name,
                'slug' => $event->institution->slug,
            ] : null,
            'venue' => $event->venue ? [
                'id' => $event->venue->id,
                'name' => $event->venue->name,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function institutionListData(Institution $institution): array
    {
        return [
            'id' => $institution->id,
            'slug' => $institution->slug,
            'name' => $institution->name,
            'nickname' => $institution->nickname,
            'display_name' => $institution->display_name,
            'events_count' => (int) ($institution->events_count ?? 0),
            'logo_url' => $institution->getFirstMediaUrl('logo', 'thumb') ?: $institution->getFirstMediaUrl('logo'),
            'location' => $this->addressLocation($institution->address),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function speakerListData(Speaker $speaker): array
    {
        return [
            'id' => $speaker->id,
            'slug' => $speaker->slug,
            'name' => $speaker->name,
            'formatted_name' => $speaker->formatted_name,
            'events_count' => (int) ($speaker->events_count ?? 0),
            'avatar_url' => $speaker->public_avatar_url,
        ];
    }

    private function normalizedString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $normalized = trim($value);

        return $normalized === '' ? null : $normalized;
    }

    private function normalizedFloat(mixed $value): ?float
    {
        if (! is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @param  iterable<mixed>  $contacts
     * @return list<array<string, mixed>>
     */
    private function contactData(iterable $contacts): array
    {
        $items = [];

        foreach ($contacts as $contact) {
            $category = $contact instanceof Contact ? $contact->category : data_get($contact, 'category');
            $categoryValue = $this->enumValue($category);
            $categoryEnum = ContactCategory::tryFrom($categoryValue);
            $isPublic = (bool) data_get($contact, 'is_public', false);

            if (! $isPublic) {
                continue;
            }

            $items[] = [
                'category' => $categoryValue,
                'label' => $categoryEnum?->getLabel() ?? Str::headline($categoryValue),
                'value' => (string) data_get($contact, 'value', ''),
                'type' => $this->enumValue(data_get($contact, 'type')),
                'is_public' => $isPublic,
            ];
        }

        return $items;
    }

    /**
     * @param  iterable<mixed>  $socialMediaItems
     * @return list<array<string, mixed>>
     */
    private function socialMediaData(iterable $socialMediaItems): array
    {
        $items = [];

        foreach ($socialMediaItems as $socialMedia) {
            $platformValue = $this->enumValue($socialMedia instanceof SocialMedia ? $socialMedia->platform : data_get($socialMedia, 'platform'));
            $platformEnum = SocialMediaPlatform::tryFrom($platformValue);
            $resolvedUrl = (string) data_get($socialMedia, 'resolved_url', data_get($socialMedia, 'url', ''));

            if ($platformValue === '' || $resolvedUrl === '') {
                continue;
            }

            $items[] = [
                'platform' => $platformValue,
                'platform_label' => $platformEnum?->getLabel() ?? Str::headline($platformValue),
                'url' => (string) data_get($socialMedia, 'url', ''),
                'resolved_url' => $resolvedUrl,
                'username' => (string) data_get($socialMedia, 'username', ''),
                'display_username' => (string) data_get($socialMedia, 'display_username', ''),
                'icon_url' => (string) data_get($socialMedia, 'icon_url', ''),
            ];
        }

        return $items;
    }

    private function addressLocation(?Address $address): ?string
    {
        if (! $address instanceof Address) {
            return null;
        }

        $parts = array_values(array_filter([
            $address->district?->name,
            $address->subdistrict?->name,
            $address->state?->name,
        ], static fn (mixed $value): bool => is_string($value) && $value !== ''));

        return $parts !== [] ? implode(', ', array_unique($parts)) : null;
    }
}
