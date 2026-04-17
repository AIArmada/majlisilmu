<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Data\Api\Frontend\Search\CountryData;
use App\Data\Api\Frontend\Search\EventListData;
use App\Data\Api\Frontend\Search\InstitutionDetailData;
use App\Data\Api\Frontend\Search\InstitutionDonationChannelData;
use App\Data\Api\Frontend\Search\InstitutionListData;
use App\Data\Api\Frontend\Search\ReferenceDetailData;
use App\Data\Api\Frontend\Search\SeriesDetailData;
use App\Data\Api\Frontend\Search\SpeakerDetailData;
use App\Data\Api\Frontend\Search\SpeakerDetailMediaData;
use App\Data\Api\Frontend\Search\SpeakerGalleryItemData;
use App\Data\Api\Frontend\Search\SpeakerInstitutionData;
use App\Data\Api\Frontend\Search\SpeakerListData;
use App\Data\Api\Frontend\Search\VenueDetailData;
use App\Enums\ContactCategory;
use App\Enums\EventKeyPersonRole;
use App\Enums\EventStructure;
use App\Enums\EventVisibility;
use App\Enums\InspirationCategory;
use App\Enums\InstitutionType;
use App\Enums\SocialMediaPlatform;
use App\Models\Address;
use App\Models\Contact;
use App\Models\DonationChannel;
use App\Models\Event;
use App\Models\EventKeyPerson;
use App\Models\Inspiration;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Series;
use App\Models\SocialMedia;
use App\Models\Speaker;
use App\Models\User;
use App\Models\Venue;
use App\Services\EventSearchService;
use App\Support\Api\ApiPagination;
use App\Support\ApiDocumentation\Schemas\InstitutionDetailResponse;
use App\Support\ApiDocumentation\Schemas\InstitutionDirectoryResponse;
use App\Support\ApiDocumentation\Schemas\SpeakerDetailResponse;
use App\Support\ApiDocumentation\Schemas\SpeakerDirectoryResponse;
use App\Support\Cache\PublicDirectoryCacheVersion;
use App\Support\Location\AddressHierarchyFormatter;
use App\Support\Location\PublicCountryRegistry;
use App\Support\Search\InstitutionSearchService;
use App\Support\Search\SpeakerSearchService;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Dedoc\Scramble\Attributes\QueryParameter;
use Dedoc\Scramble\Attributes\Response;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SearchController extends FrontendController
{
    public function __construct(
        private readonly EventSearchService $eventSearchService,
        private readonly InstitutionSearchService $institutionSearchService,
        private readonly SpeakerSearchService $speakerSearchService,
        private readonly PublicDirectoryCacheVersion $publicDirectoryCacheVersion,
    ) {}

    #[Group('Search', 'Public aggregate search endpoints across events, speakers, and institutions.')]
    #[Endpoint(
        title: 'Search events, speakers, and institutions',
        description: 'Returns a compact public search payload for events, speakers, and institutions using the same visibility rules as the client surface.',
    )]
    public function search(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $search = $this->normalizedString($request->query('search'));
        $lat = $this->normalizedLatitude($request->query('lat'));
        $lng = $this->normalizedLongitude($request->query('lng'));
        $radius = $this->normalizedRadiusKm($request);
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

        if ($hasLocation) {
            $this->applyInstitutionNearbyScope($institutionQuery, $lat, $lng, $radius);
        }

        return response()->json([
            'data' => [
                'events' => [
                    'items' => collect($eventPaginator->items())->map(fn (Event $event): array => $this->eventListData($event))->all(),
                    'total' => $eventPaginator->total(),
                ],
                'speakers' => [
                    'items' => $speakerQuery->orderBy('name')->limit(4)->get()->map(fn (Speaker $speaker): array => $this->speakerListData($speaker, $user))->all(),
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
                'authenticated' => $user instanceof User,
            ],
        ]);
    }

    #[Group('Institution', 'Public institution directory and detail endpoints.')]
    #[Endpoint(
        title: 'List public institutions',
        description: 'Returns the public institution directory with search, geography, nearby radius, type, and follow-state filters.',
    )]
    #[QueryParameter('search', 'Optional free-text search across public institution names, nicknames, and descriptions.', required: false, type: 'string', infer: false, example: 'Masjid Biru')]
    #[QueryParameter('lat', 'Current device latitude. Provide with `lng` to filter institutions within `radius_km`.', required: false, type: 'number', infer: false, example: 3.139)]
    #[QueryParameter('lng', 'Current device longitude. Provide with `lat` to filter institutions within `radius_km`.', required: false, type: 'number', infer: false, example: 101.6869)]
    #[QueryParameter('radius_km', 'Nearby search radius in kilometers. Values are clamped from 1 to 100 and default to 15 when `lat` and `lng` are present.', required: false, type: 'integer', infer: false, default: 15, example: 15)]
    #[QueryParameter('type', 'Optional institution type filter.', required: false, type: 'string', infer: false, example: 'masjid')]
    #[QueryParameter('country_id', 'Optional country filter.', required: false, type: 'integer', infer: false, example: 132)]
    #[QueryParameter('state_id', 'Optional state filter.', required: false, type: 'integer', infer: false, example: 14)]
    #[QueryParameter('district_id', 'Optional district filter.', required: false, type: 'integer', infer: false, example: 103)]
    #[QueryParameter('subdistrict_id', 'Optional subdistrict filter.', required: false, type: 'integer', infer: false, example: 1201)]
    #[QueryParameter('following', 'When authenticated, restrict results to institutions followed by the current user.', required: false, type: 'boolean', infer: false, example: false)]
    #[QueryParameter('page', 'Pagination page number.', required: false, type: 'integer', infer: false, default: 1, example: 1)]
    #[QueryParameter('per_page', 'Pagination page size. Values are clamped to the server-supported maximum.', required: false, type: 'integer', infer: false, default: 12, example: 12)]
    #[Response(
        status: 200,
        description: 'Institution directory response.',
        type: InstitutionDirectoryResponse::class,
    )]
    public function institutions(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $search = $this->normalizedString($request->query('search'));
        $institutionType = $this->normalizedInstitutionType($request->query('type'));
        $countryId = $this->requestedCountryId($request);
        $stateId = $this->normalizedInt($request->query('state_id'));
        $districtId = $this->normalizedInt($request->query('district_id'));
        $subdistrictId = $this->normalizedInt($request->query('subdistrict_id'));
        $lat = $this->normalizedLatitude($request->query('lat'));
        $lng = $this->normalizedLongitude($request->query('lng'));
        $radius = $this->normalizedRadiusKm($request);
        $hasNearbyLocation = $lat !== null && $lng !== null;
        $perPage = ApiPagination::normalizePerPage($request->integer('per_page', 12), default: 12, max: 50);
        $followingOnly = $request->boolean('following');

        $baseQuery = $this->baseInstitutionQuery(
            type: $institutionType,
            countryId: $countryId,
            stateId: $stateId,
            districtId: $districtId,
            subdistrictId: $subdistrictId,
            user: $user,
        );

        if ($hasNearbyLocation) {
            $this->applyInstitutionNearbyScope($baseQuery, $lat, $lng, $radius);
        }

        $followedInstitutionQuery = clone $baseQuery;

        $this->applyInstitutionFollowingScope($followedInstitutionQuery, $user);

        $followingTotal = $this->institutionDirectoryTotalWithBase($request, $search, $followedInstitutionQuery);

        if ($followingOnly) {
            $this->applyInstitutionFollowingScope($baseQuery, $user);
        }

        $institutions = $search === null
            ? $baseQuery
                ->when(! $hasNearbyLocation, fn (Builder $query) => $query->publicDirectoryOrder())
                ->paginate($perPage)
            : $this->institutionDirectorySearchPaginator($request, $search, $perPage, $baseQuery);

        return response()->json([
            'data' => collect($institutions->items())->map(fn (Institution $institution): array => $this->institutionListData($institution, $user))->all(),
            'meta' => [
                'pagination' => [
                    'page' => $institutions->currentPage(),
                    'per_page' => $institutions->perPage(),
                    'total' => $institutions->total(),
                ],
                'following' => [
                    'total' => $followingTotal,
                ],
                'location' => [
                    'active' => $hasNearbyLocation,
                    'lat' => $lat,
                    'lng' => $lng,
                    'radius_km' => $hasNearbyLocation ? $radius : null,
                ],
                'types' => $this->institutionTypeFiltersData(),
                'cache' => $this->institutionDirectoryCacheData(),
            ],
        ]);
    }

    #[Group('Speaker', 'Public speaker directory and detail endpoints.')]
    #[Endpoint(
        title: 'List public speakers',
        description: 'Returns the public speaker directory with search, location, gender, and follow-state filters.',
    )]
    #[Response(
        status: 200,
        description: 'Speaker directory response.',
        type: SpeakerDirectoryResponse::class,
    )]
    public function speakers(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $search = $this->normalizedString($request->query('search'));
        $directorySeed = $this->normalizedString($request->query('directory_seed'));
        $perPage = ApiPagination::normalizePerPage($request->integer('per_page', 12), default: 12, max: 50);
        $countryId = $this->requestedCountryId($request);
        $stateId = $this->normalizedInt($request->query('state_id'));
        $districtId = $this->normalizedInt($request->query('district_id'));
        $subdistrictId = $this->normalizedInt($request->query('subdistrict_id'));
        $gender = in_array($request->query('gender'), ['male', 'female'], true)
            ? $request->query('gender')
            : null;
        $sort = $request->query('sort') === 'upcoming' ? 'upcoming' : null;
        $followingOnly = $request->boolean('following');

        $baseQuery = $this->baseSpeakerQuery($user);

        $this->applySpeakerLocationScope($baseQuery, $countryId, $stateId, $districtId, $subdistrictId);

        if ($gender !== null) {
            $baseQuery->where('speakers.gender', $gender);
        }

        if ($sort === 'upcoming') {
            $baseQuery->orderBy('events_count', 'desc');
        }

        $followedSpeakerQuery = clone $baseQuery;

        $this->applySpeakerFollowingScope($followedSpeakerQuery, $user);

        $followingTotal = $this->speakerDirectoryTotalWithBase($request, $search, $followedSpeakerQuery, $sort);

        if ($followingOnly) {
            $this->applySpeakerFollowingScope($baseQuery, $user);
        }

        $speakers = $search === null
            ? $baseQuery->when($sort === null, fn ($q) => $q->publicDirectoryOrder($directorySeed))->paginate($perPage)
            : $this->speakerDirectorySearchPaginatorWithBase($request, $search, $perPage, $baseQuery, $sort);
        $speakerDirectoryCache = $this->speakerDirectoryCacheData();

        return response()->json([
            'data' => collect($speakers->items())->map(fn (Speaker $speaker): array => $this->speakerListData($speaker, $user))->all(),
            'meta' => [
                'pagination' => [
                    'page' => $speakers->currentPage(),
                    'per_page' => $speakers->perPage(),
                    'total' => $speakers->total(),
                ],
                'following' => [
                    'total' => $followingTotal,
                ],
                'cache' => $speakerDirectoryCache,
            ],
        ]);
    }

    #[Group('Inspiration', 'Public inspiration discovery endpoints for random featured inspiration content.')]
    #[Endpoint(
        title: 'Get a random inspiration',
        description: 'Returns one random active inspiration record, localized when a `locale` query value is provided.',
    )]
    public function randomInspiration(Request $request): JsonResponse
    {
        $locale = $this->normalizedString($request->query('locale'));
        $record = Inspiration::query()
            ->with('media')
            ->active()
            ->forLocale($locale)
            ->inRandomOrder()
            ->first();

        return response()->json([
            'data' => $record ? $this->inspirationData($record) : null,
            'meta' => [
                'locale' => $locale ?? app()->getLocale(),
            ],
        ]);
    }

    #[Group('Institution', 'Public institution directory and detail endpoints.')]
    #[Endpoint(
        title: 'Get a public institution',
        description: 'Returns the public institution detail payload by slug or UUID, including upcoming and past events.',
    )]
    #[Response(
        status: 200,
        description: 'Institution detail response.',
        type: InstitutionDetailResponse::class,
    )]
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
                'institution' => $this->institutionDetailData($record, $user),
                'upcoming_events' => $record->events()
                    ->active()
                    ->where('starts_at', '>=', now())
                    ->with(['institution.media', 'venue.address.state', 'venue.address.district', 'venue.address.subdistrict', 'speakers.media', 'keyPeople.speaker', 'media', 'references'])
                    ->orderBy('starts_at')
                    ->take(max(1, min($request->integer('upcoming_per_page', 6), 50)))
                    ->get()
                    ->map(fn (Event $event): array => $this->eventListData($event))
                    ->all(),
                'upcoming_total' => $record->events()->active()->where('starts_at', '>=', now())->count(),
                'past_events' => $record->events()
                    ->active()
                    ->where('starts_at', '<', now())
                    ->with(['institution.media', 'venue.address.state', 'venue.address.district', 'venue.address.subdistrict', 'speakers.media', 'keyPeople.speaker', 'media', 'references'])
                    ->orderByDesc('starts_at')
                    ->take(max(1, min($request->integer('past_per_page', 6), 50)))
                    ->get()
                    ->map(fn (Event $event): array => $this->eventListData($event))
                    ->all(),
                'past_total' => $record->events()->active()->where('starts_at', '<', now())->count(),
            ],
        ]);
    }

    #[Group('Speaker', 'Public speaker directory and detail endpoints.')]
    #[Endpoint(
        title: 'Get a public speaker',
        description: 'Returns the public speaker detail payload by slug or UUID, including speaker events and other key-person participations.',
    )]
    #[Response(
        status: 200,
        description: 'Speaker detail response.',
        type: SpeakerDetailResponse::class,
    )]
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

        $otherRoleUpcomingParticipations = $record->nonSpeakerEventKeyPeople()
            ->whereHas('event', function (Builder $query): void {
                $query
                    ->where('events.is_active', true)
                    ->whereIn('events.status', Event::PUBLIC_STATUSES)
                    ->where('events.visibility', EventVisibility::Public)
                    ->where('events.event_structure', '!=', EventStructure::ParentProgram->value)
                    ->where('starts_at', '>=', now());
            })
            ->with([
                'event.institution',
                'event.institution.media',
                'event.institution.address.state',
                'event.institution.address.district',
                'event.institution.address.subdistrict',
                'event.venue.address.state',
                'event.venue.address.district',
                'event.venue.address.subdistrict',
                'event.media',
                'event.references',
            ])
            ->get()
            ->sortBy(function (EventKeyPerson $keyPerson): int {
                $startsAt = $keyPerson->event?->starts_at;

                return $startsAt instanceof \DateTimeInterface ? $startsAt->getTimestamp() : PHP_INT_MAX;
            })
            ->take(max(1, min($request->integer('other_role_upcoming_per_page', 6), 50)))
            ->values();

        $otherRolePastParticipations = $record->nonSpeakerEventKeyPeople()
            ->whereHas('event', function (Builder $query): void {
                $query
                    ->where('events.is_active', true)
                    ->whereIn('events.status', Event::PUBLIC_STATUSES)
                    ->where('events.visibility', EventVisibility::Public)
                    ->where('events.event_structure', '!=', EventStructure::ParentProgram->value)
                    ->where('starts_at', '<', now());
            })
            ->with([
                'event.institution',
                'event.institution.media',
                'event.institution.address.state',
                'event.institution.address.district',
                'event.institution.address.subdistrict',
                'event.venue.address.state',
                'event.venue.address.district',
                'event.venue.address.subdistrict',
                'event.media',
                'event.references',
            ])
            ->get()
            ->sortByDesc(function (EventKeyPerson $keyPerson): int {
                $startsAt = $keyPerson->event?->starts_at;

                return $startsAt instanceof \DateTimeInterface ? $startsAt->getTimestamp() : 0;
            })
            ->take(max(1, min($request->integer('other_role_past_per_page', 6), 50)))
            ->values();

        return response()->json([
            'data' => [
                'speaker' => $this->speakerDetailData($record, $user),
                'upcoming_events' => $record->speakerEvents()
                    ->active()
                    ->where('starts_at', '>=', now())
                    ->with(['institution', 'institution.media', 'institution.address.state', 'institution.address.district', 'institution.address.subdistrict', 'venue.address.state', 'venue.address.district', 'venue.address.subdistrict', 'media', 'references'])
                    ->orderBy('starts_at')
                    ->take(max(1, min($request->integer('upcoming_per_page', 10), 50)))
                    ->get()
                    ->map(fn (Event $event): array => $this->eventListData($event))
                    ->all(),
                'upcoming_total' => $record->speakerEvents()->active()->where('starts_at', '>=', now())->count(),
                'past_events' => $record->speakerEvents()
                    ->active()
                    ->where('starts_at', '<', now())
                    ->with(['institution', 'institution.media', 'institution.address.state', 'institution.address.district', 'institution.address.subdistrict', 'venue.address.state', 'venue.address.district', 'venue.address.subdistrict', 'media', 'references'])
                    ->orderByDesc('starts_at')
                    ->take(max(1, min($request->integer('past_per_page', 10), 50)))
                    ->get()
                    ->map(fn (Event $event): array => $this->eventListData($event))
                    ->all(),
                'past_total' => $record->speakerEvents()->active()->where('starts_at', '<', now())->count(),
                'other_role_upcoming_participations' => $otherRoleUpcomingParticipations
                    ->map(fn (EventKeyPerson $keyPerson): array => $this->eventParticipationData($keyPerson))
                    ->all(),
                'other_role_upcoming_total' => $record->nonSpeakerEventKeyPeople()
                    ->whereHas('event', function (Builder $query): void {
                        $query
                            ->where('events.is_active', true)
                            ->whereIn('events.status', Event::PUBLIC_STATUSES)
                            ->where('events.visibility', EventVisibility::Public)
                            ->where('events.event_structure', '!=', EventStructure::ParentProgram->value)
                            ->where('starts_at', '>=', now());
                    })
                    ->count(),
                'other_role_past_participations' => $otherRolePastParticipations
                    ->map(fn (EventKeyPerson $keyPerson): array => $this->eventParticipationData($keyPerson))
                    ->all(),
                'other_role_past_total' => $record->nonSpeakerEventKeyPeople()
                    ->whereHas('event', function (Builder $query): void {
                        $query
                            ->where('events.is_active', true)
                            ->whereIn('events.status', Event::PUBLIC_STATUSES)
                            ->where('events.visibility', EventVisibility::Public)
                            ->where('events.event_structure', '!=', EventStructure::ParentProgram->value)
                            ->where('starts_at', '<', now());
                    })
                    ->count(),
            ],
        ]);
    }

    #[Group('Venue', 'Public venue detail endpoints for active verified venues and their related events.')]
    #[Endpoint(
        title: 'Get a public venue',
        description: 'Returns the public venue detail payload by slug or UUID, including upcoming and past events hosted there.',
    )]
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
                'venue' => $this->venueDetailData($record),
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
                        'references',
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
                        'references',
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

    #[Group('Reference', 'Public reference detail endpoints for active references and their related events.')]
    #[Endpoint(
        title: 'Get a public reference',
        description: 'Returns the public reference detail payload by slug or UUID, including upcoming and past events linked to that reference.',
    )]
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
                'reference' => $this->referenceDetailData($record, $user),
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

    #[Group('Series', 'Public series detail endpoints for visible series and their related events.')]
    #[Endpoint(
        title: 'Get a public series',
        description: 'Returns the public series detail payload by slug or UUID, including upcoming and past events in the series.',
    )]
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
                'series' => $this->seriesDetailData($record, $user),
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
        return $this->speakerSearchService->applyIndexedSearch($this->baseSpeakerQuery(), $search);
    }

    /**
     * @return Builder<Institution>
     */
    private function institutionSearchQuery(string $search): Builder
    {
        return $this->institutionSearchService->applySearch(
            Institution::query()
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
                ->with(['address.country', 'address.state', 'address.district', 'address.subdistrict', 'media']),
            $search,
        );
    }

    /**
     * @return Builder<Institution>
     */
    private function baseInstitutionQuery(
        ?InstitutionType $type = null,
        ?int $countryId = null,
        ?int $stateId = null,
        ?int $districtId = null,
        ?int $subdistrictId = null,
        ?User $user = null,
    ): Builder {
        $query = Institution::query();

        if ($user instanceof User) {
            $query->select('institutions.*')
                ->selectRaw(
                    'exists(select 1 from followings where followings.user_id = ? and followings.followable_id = institutions.id and followings.followable_type = ?) as is_following',
                    [$user->id, (new Institution)->getMorphClass()],
                );
        }

        $query
            ->active()
            ->where('status', 'verified')
            ->withCount(['events' => function (Builder $query): void {
                $query
                    ->where('events.is_active', true)
                    ->whereIn('events.status', Event::PUBLIC_STATUSES)
                    ->where('events.visibility', EventVisibility::Public)
                    ->where('events.event_structure', '!=', EventStructure::ParentProgram->value);
            }])
            ->with(['address.country', 'address.state', 'address.district', 'address.subdistrict', 'media']);

        if ($type instanceof InstitutionType) {
            $query->where('institutions.type', $type->value);
        }

        $this->applyInstitutionLocationScope($query, $countryId, $stateId, $districtId, $subdistrictId);

        return $query;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function institutionTypeFiltersData(): array
    {
        return array_map(
            static fn (InstitutionType $type): array => [
                'value' => $type->value,
                'label' => $type->getLabel(),
            ],
            InstitutionType::cases(),
        );
    }

    /**
     * @param  Builder<Institution>  $query
     */
    private function applyInstitutionLocationScope(Builder $query, ?int $countryId, ?int $stateId, ?int $districtId, ?int $subdistrictId): void
    {
        if ($countryId === null && $stateId === null && $districtId === null && $subdistrictId === null) {
            return;
        }

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
     */
    private function applyInstitutionNearbyScope(Builder $query, float $lat, float $lng, int $radiusKm): void
    {
        $addressMorphType = (new Institution)->getMorphClass();
        $distanceSql = '(6371 * acos(cos(radians(?)) * cos(radians(institution_addresses.lat)) * cos(radians(institution_addresses.lng) - radians(?)) + sin(radians(?)) * sin(radians(institution_addresses.lat))))';

        if ($query->getQuery()->columns === null) {
            $query->select('institutions.*');
        }

        $query
            ->join('addresses as institution_addresses', function ($join) use ($addressMorphType): void {
                $join->on('institution_addresses.addressable_id', '=', 'institutions.id')
                    ->where('institution_addresses.addressable_type', $addressMorphType);
            })
            ->whereRaw('institution_addresses.lat is not null')
            ->whereRaw('institution_addresses.lng is not null')
            ->selectRaw("{$distanceSql} as distance_km", [$lat, $lng, $lat])
            ->whereRaw("{$distanceSql} <= ?", [$lat, $lng, $lat, $radiusKm])
            ->orderBy('distance_km')
            ->orderBy('institutions.name')
            ->orderBy('institutions.id');
    }

    /**
     * @param  Builder<Institution>  $base
     * @return LengthAwarePaginator<int, Institution>
     */
    private function institutionDirectorySearchPaginator(Request $request, string $search, int $perPage, Builder $base): LengthAwarePaginator
    {
        $matchingIds = $this->institutionSearchService->publicSearchIds($search);

        if ($matchingIds !== []) {
            $directMatches = (clone $base)
                ->whereIn('institutions.id', $matchingIds)
                ->publicDirectoryOrder()
                ->paginate($perPage);

            if ($directMatches->total() > 0 || mb_strlen($search) < 3) {
                return $directMatches;
            }
        } elseif (mb_strlen($search) < 3) {
            return $this->emptyInstitutionPaginator($request, $perPage);
        }

        $orderedIds = $this->filterInstitutionSearchIds($base, $this->institutionSearchService->publicFuzzySearchIds($search));

        if ($orderedIds === []) {
            return $this->emptyInstitutionPaginator($request, $perPage);
        }

        $currentPage = max(1, $request->integer('page', 1));
        $paginatedIds = array_slice($orderedIds, ($currentPage - 1) * $perPage, $perPage);

        if ($paginatedIds === []) {
            return new LengthAwarePaginator(
                collect(),
                count($orderedIds),
                $perPage,
                $currentPage,
                $this->institutionPaginatorOptions($request),
            );
        }

        $institutions = (clone $base)
            ->whereIn('institutions.id', $paginatedIds)
            ->get()
            ->sortBy(static function (Institution $institution) use ($paginatedIds): int {
                $position = array_search($institution->id, $paginatedIds, true);

                return is_int($position) ? $position : PHP_INT_MAX;
            })
            ->values();

        return new LengthAwarePaginator(
            $institutions,
            count($orderedIds),
            $perPage,
            $currentPage,
            $this->institutionPaginatorOptions($request),
        );
    }

    /**
     * @param  Builder<Institution>  $base
     * @param  list<string>  $orderedIds
     * @return list<string>
     */
    private function filterInstitutionSearchIds(Builder $base, array $orderedIds): array
    {
        if ($orderedIds === []) {
            return [];
        }

        $scopedIds = (clone $base)
            ->whereIn('institutions.id', $orderedIds)
            ->pluck('institutions.id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        return collect($scopedIds)
            ->sortBy(static function (string $id) use ($orderedIds): int {
                $position = array_search($id, $orderedIds, true);

                return is_int($position) ? $position : PHP_INT_MAX;
            })
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Institution>  $base
     */
    private function institutionDirectoryTotalWithBase(Request $request, ?string $search, Builder $base): int
    {
        if ($search === null) {
            return (clone $base)->count();
        }

        return $this->institutionDirectorySearchPaginator($request, $search, 1, $base)->total();
    }

    /**
     * @param  Builder<Institution>  $query
     */
    private function applyInstitutionFollowingScope(Builder $query, ?User $user): void
    {
        if (! $user instanceof User) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereExists(function ($followingQuery) use ($user): void {
            $followingQuery
                ->selectRaw('1')
                ->from('followings')
                ->where('followings.user_id', $user->id)
                ->where('followings.followable_type', (new Institution)->getMorphClass())
                ->whereColumn('followings.followable_id', 'institutions.id');
        });
    }

    /**
     * @return LengthAwarePaginator<int, Institution>
     */
    private function emptyInstitutionPaginator(Request $request, int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            collect(),
            0,
            $perPage,
            max(1, $request->integer('page', 1)),
            $this->institutionPaginatorOptions($request),
        );
    }

    /**
     * @return array{path: string, query: array<string, mixed>}
     */
    private function institutionPaginatorOptions(Request $request): array
    {
        return [
            'path' => $request->url(),
            'query' => $request->query(),
        ];
    }

    /**
     * @return array{version: string}
     */
    private function institutionDirectoryCacheData(): array
    {
        return $this->publicDirectoryCacheVersion->institution();
    }

    /**
     * @return array{version: string}
     */
    private function speakerDirectoryCacheData(): array
    {
        return $this->publicDirectoryCacheVersion->speaker();
    }

    /**
     * @return Builder<Speaker>
     */
    private function baseSpeakerQuery(?User $user = null): Builder
    {
        $query = Speaker::query();

        if ($user instanceof User) {
            $query->select('speakers.*')
                ->selectRaw(
                    'exists(select 1 from followings where followings.user_id = ? and followings.followable_id = speakers.id and followings.followable_type = ?) as is_following',
                    [$user->id, (new Speaker)->getMorphClass()],
                );
        }

        return $query->active()
            ->where('status', 'verified')
            ->withCount(['events' => function (Builder $query): void {
                $query
                    ->where('events.is_active', true)
                    ->whereIn('events.status', Event::PUBLIC_STATUSES)
                    ->where('events.visibility', EventVisibility::Public)
                    ->where('events.event_structure', '!=', EventStructure::ParentProgram->value)
                    ->where('events.starts_at', '>=', now());
            }])
            ->with(['media', 'address.country']);
    }

    /**
     * @param  Builder<Speaker>  $base
     * @return LengthAwarePaginator<int, Speaker>
     */
    private function speakerDirectorySearchPaginatorWithBase(Request $request, string $search, int $perPage, Builder $base, ?string $sort): LengthAwarePaginator
    {
        $matchingIds = $this->speakerSearchService->publicSearchIds($search);

        if ($matchingIds !== []) {
            $query = (clone $base)->whereIn('speakers.id', $matchingIds);

            if ($sort !== 'upcoming') {
                $query->publicDirectoryOrder();
            }

            return $query->paginate($perPage);
        }

        if (mb_strlen($search) < 3) {
            return $this->emptySpeakerPaginator($request, $perPage);
        }

        $orderedIds = $this->filterSpeakerSearchIds($base, $this->speakerSearchService->publicFuzzySearchIds($search));

        if ($orderedIds === []) {
            return $this->emptySpeakerPaginator($request, $perPage);
        }

        $currentPage = max(1, $request->integer('page', 1));
        $paginatedIds = array_slice($orderedIds, ($currentPage - 1) * $perPage, $perPage);

        if ($paginatedIds === []) {
            return new LengthAwarePaginator(
                collect(),
                count($orderedIds),
                $perPage,
                $currentPage,
                $this->speakerPaginatorOptions($request),
            );
        }

        $speakers = (clone $base)
            ->whereIn('speakers.id', $paginatedIds)
            ->get()
            ->sortBy(static function (Speaker $speaker) use ($paginatedIds): int {
                $position = array_search($speaker->id, $paginatedIds, true);

                return is_int($position) ? $position : PHP_INT_MAX;
            })
            ->values();

        return new LengthAwarePaginator(
            $speakers,
            count($orderedIds),
            $perPage,
            $currentPage,
            $this->speakerPaginatorOptions($request),
        );
    }

    /**
     * @return LengthAwarePaginator<int, Speaker>
     */
    private function emptySpeakerPaginator(Request $request, int $perPage): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            collect(),
            0,
            $perPage,
            max(1, $request->integer('page', 1)),
            $this->speakerPaginatorOptions($request),
        );
    }

    /**
     * @param  Builder<Speaker>  $query
     */
    private function applySpeakerLocationScope(Builder $query, ?int $countryId, ?int $stateId, ?int $districtId, ?int $subdistrictId): void
    {
        if ($countryId === null && $stateId === null && $districtId === null && $subdistrictId === null) {
            return;
        }

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
     * @param  Builder<Speaker>  $base
     * @param  list<string>  $orderedIds
     * @return list<string>
     */
    private function filterSpeakerSearchIds(Builder $base, array $orderedIds): array
    {
        if ($orderedIds === []) {
            return [];
        }

        $scopedIds = (clone $base)
            ->whereIn('speakers.id', $orderedIds)
            ->pluck('speakers.id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        return collect($scopedIds)
            ->sortBy(static function (string $id) use ($orderedIds): int {
                $position = array_search($id, $orderedIds, true);

                return is_int($position) ? $position : PHP_INT_MAX;
            })
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Speaker>  $base
     */
    private function speakerDirectoryTotalWithBase(Request $request, ?string $search, Builder $base, ?string $sort): int
    {
        if ($search === null) {
            return (clone $base)->count();
        }

        return $this->speakerDirectorySearchPaginatorWithBase($request, $search, 1, $base, $sort)->total();
    }

    /**
     * @param  Builder<Speaker>  $query
     */
    private function applySpeakerFollowingScope(Builder $query, ?User $user): void
    {
        if (! $user instanceof User) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereExists(function ($followingQuery) use ($user): void {
            $followingQuery
                ->selectRaw('1')
                ->from('followings')
                ->where('followings.user_id', $user->id)
                ->where('followings.followable_type', (new Speaker)->getMorphClass())
                ->whereColumn('followings.followable_id', 'speakers.id');
        });
    }

    /**
     * @return array{path: string, query: array<string, mixed>}
     */
    private function speakerPaginatorOptions(Request $request): array
    {
        return [
            'path' => $request->url(),
            'query' => $request->query(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function venueDetailData(Venue $venue): array
    {
        return VenueDetailData::fromModel(
            venue: $venue,
            contacts: $this->contactData($venue->contacts),
            socialMedia: $this->socialMediaData($venue->socialMedia),
        )->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function referenceDetailData(Reference $reference, ?User $user): array
    {
        return ReferenceDetailData::fromModel(
            reference: $reference,
            user: $user,
            socialMedia: $this->socialMediaData($reference->socialMedia),
        )->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function seriesDetailData(Series $series, ?User $user): array
    {
        return SeriesDetailData::fromModel($series, $user)->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function institutionDetailData(Institution $institution, ?User $user): array
    {
        $addressModel = $institution->addressModel;
        $institutionMedia = $this->institutionCardMediaData($institution);
        $addressLines = $this->addressDisplayLines($addressModel);

        return InstitutionDetailData::fromModel(
            institution: $institution,
            user: $user,
            addressLines: $addressLines,
            address: $this->addressFilterData($addressModel),
            country: $this->countryData($addressModel),
            addressLine: $this->addressLocation($addressModel),
            media: $institutionMedia,
            speakerCount: $this->institutionSpeakerCount($institution),
            contacts: $this->contactData($institution->contacts),
            socialMedia: $this->socialMediaData($institution->socialMedia),
            donationChannels: $institution->donationChannels
                ->where('status', 'verified')
                ->sortByDesc('is_default')
                ->map(fn (DonationChannel $channel): array => $this->institutionDonationChannelData($channel))
                ->values()
                ->all(),
        )->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function speakerDetailData(Speaker $speaker, ?User $user): array
    {
        $bio = $this->speakerBioData($speaker);
        $coverUrl = $speaker->getFirstMediaUrl('cover', 'banner') ?: $speaker->getFirstMediaUrl('cover');

        return SpeakerDetailData::fromModel(
            speaker: $speaker,
            user: $user,
            bio: $bio,
            address: $this->addressFilterData($speaker->addressModel),
            country: $this->countryData($speaker->addressModel),
            location: $this->addressLocation($speaker->addressModel),
            media: SpeakerDetailMediaData::fromModel($speaker, $coverUrl)->toArray(),
            gallery: $this->speakerGalleryData($speaker),
            institutions: $speaker->institutions
                ->map(fn (Institution $institution): array => $this->speakerInstitutionData($institution))
                ->all(),
            contacts: $this->contactData($speaker->contacts),
            socialMedia: $this->socialMediaData($speaker->socialMedia),
        )->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function eventListData(Event $event): array
    {
        return EventListData::fromModel($event)->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function eventParticipationData(EventKeyPerson $keyPerson): array
    {
        return [
            'id' => $keyPerson->id,
            'role' => $this->enumValue($keyPerson->role),
            'role_label' => $this->keyPersonRoleLabel($keyPerson->role),
            'display_name' => $keyPerson->display_name,
            'event' => $keyPerson->event ? $this->eventListData($keyPerson->event) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function institutionListData(Institution $institution, ?User $user = null): array
    {
        return InstitutionListData::fromModel($institution, $user)->toArray();
    }

    /** @return array{public_image_url: string, logo_url: string, cover_url: ?string} */
    private function institutionCardMediaData(Institution $institution): array
    {
        $publicImageUrl = $institution->public_image_url;
        $logoUrl = $institution->public_logo_url;
        $coverUrl = $institution->public_cover_url;
        $logoFallbackUrl = $institution->getFallbackMediaUrl('logo', 'thumb');
        $resolvedLogoUrl = $logoUrl !== ''
            ? $logoUrl
            : ($logoFallbackUrl !== '' ? $logoFallbackUrl : $publicImageUrl);

        return [
            'public_image_url' => $publicImageUrl,
            'logo_url' => $resolvedLogoUrl,
            'cover_url' => $coverUrl !== '' ? $coverUrl : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function speakerListData(Speaker $speaker, ?User $user = null): array
    {
        return SpeakerListData::fromModel($speaker, $user)->toArray();
    }

    /**
     * @return array<string, mixed>
     */
    private function inspirationData(Inspiration $inspiration): array
    {
        $category = $inspiration->category;

        if (! $category instanceof InspirationCategory) {
            $category = InspirationCategory::from((string) $category);
        }

        $contentHtml = $inspiration->renderContentHtml();
        $contentText = trim(strip_tags($contentHtml));
        $thumbUrl = $inspiration->getFirstMediaUrl('main', 'thumb') ?: null;
        $fullUrl = $inspiration->getFirstMediaUrl('main') ?: null;

        return [
            'id' => $inspiration->id,
            'locale' => $inspiration->locale,
            'title' => $inspiration->title,
            'content' => $contentText,
            'content_html' => $contentHtml,
            'preview_text' => $inspiration->contentPreviewText(160),
            'source' => $inspiration->source,
            'category' => [
                'value' => $category->value,
                'label' => $category->label(),
                'icon' => $category->icon(),
                'color' => $category->color(),
                'is_comic' => $category === InspirationCategory::IslamicComic,
            ],
            'media' => [
                'thumb_url' => $thumbUrl,
                'full_url' => $fullUrl,
                'has_media' => $thumbUrl !== null,
            ],
        ];
    }

    /** @return array{id: string, name: string, display_name: string, slug: string, position: ?string, is_primary: bool, public_image_url: string, logo_url: string, cover_url: ?string} */
    private function speakerInstitutionData(Institution $institution): array
    {
        return SpeakerInstitutionData::fromModel($institution, $this->institutionCardMediaData($institution))->toArray();
    }

    /**
     * @return list<array{id: string, name: string, url: string, thumb_url: string}>
     */
    private function speakerGalleryData(Speaker $speaker): array
    {
        return $speaker->getMedia('gallery')
            ->map(fn (Media $media): array => SpeakerGalleryItemData::fromModel($media)->toArray())
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function institutionDonationChannelData(DonationChannel $channel): array
    {
        return InstitutionDonationChannelData::fromModel($channel)->toArray();
    }

    /**
     * @return array{html: string, text: string, excerpt: ?string, should_collapse: bool}
     */
    private function speakerBioData(Speaker $speaker): array
    {
        $bio = $speaker->bio;

        if (is_array($bio)) {
            $renderer = RichContentRenderer::make($bio);
            $html = $renderer->toHtml();
            $text = trim($renderer->toText());
        } else {
            $html = (string) $bio;
            $text = trim(strip_tags((string) $bio));
        }

        return [
            'html' => $html,
            'text' => $text,
            'excerpt' => $text !== '' ? Str::limit($text, 180) : null,
            'should_collapse' => Str::length($text) > 680,
        ];
    }

    private function keyPersonRoleLabel(mixed $role): string
    {
        if ($role instanceof EventKeyPersonRole) {
            return $role->getLabel();
        }

        if ($role instanceof \BackedEnum && is_string($role->value)) {
            return EventKeyPersonRole::tryFrom($role->value)?->getLabel() ?? Str::headline($role->value);
        }

        if (is_string($role) && $role !== '') {
            return EventKeyPersonRole::tryFrom($role)?->getLabel() ?? Str::headline($role);
        }

        return '';
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

    private function normalizedLatitude(mixed $value): ?float
    {
        $latitude = $this->normalizedFloat($value);

        return $latitude !== null && $latitude >= -90.0 && $latitude <= 90.0
            ? $latitude
            : null;
    }

    private function normalizedLongitude(mixed $value): ?float
    {
        $longitude = $this->normalizedFloat($value);

        return $longitude !== null && $longitude >= -180.0 && $longitude <= 180.0
            ? $longitude
            : null;
    }

    private function normalizedRadiusKm(Request $request): int
    {
        return max(1, min($request->integer('radius_km', 15), 100));
    }

    private function normalizedInt(mixed $value): ?int
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        if ($normalized === '' || ! ctype_digit($normalized)) {
            return null;
        }

        return (int) $normalized;
    }

    private function normalizedInstitutionType(mixed $value): ?InstitutionType
    {
        $normalized = $this->normalizedString($value);

        if ($normalized === null) {
            return null;
        }

        return InstitutionType::tryFrom($normalized);
    }

    private function requestedCountryId(Request $request): ?int
    {
        return app(PublicCountryRegistry::class)->resolveCountryId(
            $request->query('country_id'),
        );
    }

    /**
     * @return array{country_id: ?int, state_id: ?int, district_id: ?int, subdistrict_id: ?int}|null
     */
    private function addressFilterData(?Address $address): ?array
    {
        if (! $address instanceof Address) {
            return null;
        }

        return [
            'country_id' => is_numeric($address->country_id) ? (int) $address->country_id : null,
            'state_id' => is_numeric($address->state_id) ? (int) $address->state_id : null,
            'district_id' => is_numeric($address->district_id) ? (int) $address->district_id : null,
            'subdistrict_id' => is_numeric($address->subdistrict_id) ? (int) $address->subdistrict_id : null,
        ];
    }

    /**
     * @return array{id: int, name: string, iso2: string, key: ?string}|null
     */
    private function countryData(?Address $address): ?array
    {
        return CountryData::fromAddress($address)?->toArray();
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

    /**
     * @return array{street: ?string, locality: ?string, regional: ?string}
     */
    private function addressDisplayLines(?Address $address): array
    {
        return AddressHierarchyFormatter::displayLines($address);
    }

    private function addressLocation(?Address $address): ?string
    {
        $location = AddressHierarchyFormatter::format($address);

        return $location !== '' ? $location : null;
    }

    private function institutionSpeakerCount(Institution $institution): int
    {
        return (int) DB::table('event_key_people')
            ->where('role', EventKeyPersonRole::Speaker->value)
            ->whereNotNull('speaker_id')
            ->whereIn('event_id', function ($sub) use ($institution): void {
                $sub->select('id')
                    ->from('events')
                    ->where('institution_id', $institution->id)
                    ->where('is_active', true)
                    ->where('starts_at', '>=', now());
            })
            ->distinct('speaker_id')
            ->count();
    }
}
