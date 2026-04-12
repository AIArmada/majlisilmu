<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Enums\ContactCategory;
use App\Enums\EventFormat;
use App\Enums\EventKeyPersonRole;
use App\Enums\EventStructure;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\InspirationCategory;
use App\Enums\SocialMediaPlatform;
use App\Models\Address;
use App\Models\Contact;
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
use App\Support\Location\AddressHierarchyFormatter;
use App\Support\Search\InstitutionSearchService;
use App\Support\Search\SpeakerSearchService;
use App\Support\Timezone\UserDateTimeFormatter;
use Dedoc\Scramble\Attributes\Group;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SearchController extends FrontendController
{
    public function __construct(
        private readonly EventSearchService $eventSearchService,
        private readonly InstitutionSearchService $institutionSearchService,
        private readonly SpeakerSearchService $speakerSearchService,
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

    #[Group('Institution')]
    public function institutions(Request $request): JsonResponse
    {
        $search = $this->normalizedString($request->query('search'));
        $countryId = $this->normalizedInt($request->query('country_id'));
        $stateId = $this->normalizedInt($request->query('state_id'));
        $districtId = $this->normalizedInt($request->query('district_id'));
        $subdistrictId = $this->normalizedInt($request->query('subdistrict_id'));
        $perPage = $request->integer('per_page', 12);

        $institutions = $search === null
            ? $this->baseInstitutionQuery($countryId, $stateId, $districtId, $subdistrictId)->publicDirectoryOrder()->paginate($perPage)
            : $this->institutionDirectorySearchPaginator($request, $search, $perPage, $countryId, $stateId, $districtId, $subdistrictId);

        return response()->json([
            'data' => collect($institutions->items())->map(fn (Institution $institution): array => $this->institutionListData($institution))->all(),
            'meta' => [
                'pagination' => [
                    'page' => $institutions->currentPage(),
                    'per_page' => $institutions->perPage(),
                    'total' => $institutions->total(),
                ],
                'cache' => $this->institutionDirectoryCacheData(),
            ],
        ]);
    }

    #[Group('Speaker')]
    public function speakers(Request $request): JsonResponse
    {
        $user = $this->currentUser($request);
        $search = $this->normalizedString($request->query('search'));
        $directorySeed = $this->normalizedString($request->query('directory_seed'));
        $perPage = $request->integer('per_page', 12);
        $gender = in_array($request->query('gender'), ['male', 'female'], true)
            ? $request->query('gender')
            : null;
        $sort = $request->query('sort') === 'upcoming' ? 'upcoming' : null;

        $baseQuery = $this->baseSpeakerQuery($user);

        if ($gender !== null) {
            $baseQuery->where('speakers.gender', $gender);
        }

        if ($sort === 'upcoming') {
            $baseQuery->orderBy('events_count', 'desc');
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
                'cache' => $speakerDirectoryCache,
            ],
        ]);
    }

    #[Group('Inspiration')]
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

        $institutionMedia = $this->institutionCardMediaData($record);

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
                        'public_image_url' => $institutionMedia['public_image_url'],
                        'logo_url' => $institutionMedia['logo_url'],
                        'cover_url' => $institutionMedia['cover_url'],
                    ],
                    'contacts' => $this->contactData($record->contacts),
                    'social_media' => $this->socialMediaData($record->socialMedia),
                ],
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
                ->with(['address.state', 'address.district', 'address.subdistrict', 'media']),
            $search,
        );
    }

    /**
     * @return Builder<Institution>
     */
    private function baseInstitutionQuery(?int $countryId = null, ?int $stateId = null, ?int $districtId = null, ?int $subdistrictId = null): Builder
    {
        $query = Institution::query()
            ->active()
            ->where('status', 'verified')
            ->withCount(['events' => function (Builder $query): void {
                $query
                    ->where('events.is_active', true)
                    ->whereIn('events.status', Event::PUBLIC_STATUSES)
                    ->where('events.visibility', EventVisibility::Public)
                    ->where('events.event_structure', '!=', EventStructure::ParentProgram->value);
            }])
            ->with(['address.state', 'address.district', 'address.subdistrict', 'media']);

        $this->applyInstitutionLocationScope($query, $countryId, $stateId, $districtId, $subdistrictId);

        return $query;
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
     * @return LengthAwarePaginator<int, Institution>
     */
    private function institutionDirectorySearchPaginator(Request $request, string $search, int $perPage, ?int $countryId, ?int $stateId, ?int $districtId, ?int $subdistrictId): LengthAwarePaginator
    {
        $matchingIds = $this->institutionSearchService->publicSearchIds($search);

        if ($matchingIds !== []) {
            $directMatches = $this->baseInstitutionQuery($countryId, $stateId, $districtId, $subdistrictId)
                ->whereIn('institutions.id', $matchingIds)
                ->publicDirectoryOrder()
                ->paginate($perPage);

            if ($directMatches->total() > 0 || mb_strlen($search) < 3) {
                return $directMatches;
            }
        } elseif (mb_strlen($search) < 3) {
            return $this->emptyInstitutionPaginator($request, $perPage);
        }

        $orderedIds = $this->filterInstitutionSearchIds(
            $this->institutionSearchService->publicFuzzySearchIds($search),
            $countryId,
            $stateId,
            $districtId,
            $subdistrictId,
        );

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

        $institutions = $this->baseInstitutionQuery($countryId, $stateId, $districtId, $subdistrictId)
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
     * @param  list<string>  $orderedIds
     * @return list<string>
     */
    private function filterInstitutionSearchIds(array $orderedIds, ?int $countryId, ?int $stateId, ?int $districtId, ?int $subdistrictId): array
    {
        if ($orderedIds === []) {
            return [];
        }

        $scopedIds = $this->baseInstitutionQuery($countryId, $stateId, $districtId, $subdistrictId)
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
        $institutionFingerprints = $this->baseInstitutionQuery()
            ->orderBy('id')
            ->get()
            ->map(fn (Institution $institution): array => $this->institutionListData($institution))
            ->all();

        return [
            'version' => sha1(json_encode($institutionFingerprints) ?: ''),
        ];
    }

    /**
     * @return array{version: string}
     */
    private function speakerDirectoryCacheData(): array
    {
        $speakerQuery = Speaker::query()
            ->active()
            ->where('status', 'verified');

        $speakerFingerprints = (clone $speakerQuery)
            ->orderBy('id')
            ->get(['id', 'slug', 'name', 'gender', 'job_title', 'status', 'is_active', 'updated_at'])
            ->map(fn (Speaker $speaker): array => $speaker->getAttributes())
            ->all();
        $speakerMediaFingerprints = Media::query()
            ->where('model_type', (new Speaker)->getMorphClass())
            ->whereIn('collection_name', ['avatar', 'cover'])
            ->whereIn('model_id', (clone $speakerQuery)->select('id'))
            ->orderBy('id')
            ->get(['id', 'model_id', 'collection_name', 'file_name', 'updated_at'])
            ->map(fn (Media $media): array => $media->getAttributes())
            ->all();
        $eventFingerprints = Event::query()
            ->where('events.is_active', true)
            ->whereIn('events.status', Event::PUBLIC_STATUSES)
            ->where('events.visibility', EventVisibility::Public)
            ->where('events.event_structure', '!=', EventStructure::ParentProgram->value)
            ->where('events.starts_at', '>=', now())
            ->orderBy('id')
            ->get(['id', 'starts_at', 'status', 'visibility', 'event_structure', 'updated_at'])
            ->map(fn (Event $event): array => $event->getAttributes())
            ->all();

        return [
            'version' => sha1(json_encode([
                'speakers' => $speakerFingerprints,
                'media' => $speakerMediaFingerprints,
                'events' => $eventFingerprints,
            ]) ?: ''),
        ];
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
            ->with('media');
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

        $orderedIds = $this->speakerSearchService->publicFuzzySearchIds($search);

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
    private function speakerDetailData(Speaker $speaker, ?User $user): array
    {
        $bio = $this->speakerBioData($speaker);
        $coverUrl = $speaker->getFirstMediaUrl('cover', 'banner') ?: $speaker->getFirstMediaUrl('cover');

        return [
            'id' => $speaker->id,
            'slug' => $speaker->slug,
            'name' => $speaker->name,
            'formatted_name' => $speaker->formatted_name,
            'job_title' => $speaker->job_title,
            'is_freelance' => (bool) $speaker->is_freelance,
            'bio' => $speaker->bio,
            'bio_html' => $bio['html'],
            'bio_text' => $bio['text'],
            'bio_excerpt' => $bio['excerpt'],
            'should_collapse_bio' => $bio['should_collapse'],
            'qualifications' => is_array($speaker->qualifications) ? array_values($speaker->qualifications) : [],
            'location' => $this->addressLocation($speaker->addressModel),
            'status' => $speaker->status,
            'is_active' => (bool) $speaker->is_active,
            'is_following' => $user?->isFollowing($speaker) ?? false,
            'media' => [
                'avatar_url' => $speaker->public_avatar_url,
                'cover_url' => $coverUrl,
                'share_image_url' => $speaker->hasMedia('avatar')
                    ? $speaker->public_avatar_url
                    : ($coverUrl !== '' ? $coverUrl : $speaker->default_avatar_url),
            ],
            'gallery' => $this->speakerGalleryData($speaker),
            'institutions' => $speaker->institutions
                ->map(fn (Institution $institution): array => $this->speakerInstitutionData($institution))
                ->all(),
            'contacts' => $this->contactData($speaker->contacts),
            'social_media' => $this->socialMediaData($speaker->socialMedia),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventListData(Event $event): array
    {
        $eventTypeValues = $this->eventTypeValues($event);
        $eventFormat = $event->event_format;
        $eventFormatValue = $this->enumValue($eventFormat);
        $status = $event->status;
        $statusValue = (string) $status;

        return [
            'id' => $event->id,
            'slug' => $event->slug,
            'title' => $event->title,
            'starts_at' => $this->optionalDateTimeString($event->starts_at),
            'ends_at' => $this->optionalDateTimeString($event->ends_at),
            'timing_display' => $event->timing_display,
            'end_time_display' => $event->ends_at instanceof \DateTimeInterface
                ? UserDateTimeFormatter::format($event->ends_at, 'h:i A')
                : null,
            'visibility' => $this->enumValue($event->visibility),
            'status' => $statusValue,
            'status_label' => $status instanceof HasLabel ? $status->getLabel() : Str::headline($statusValue),
            'event_type' => $eventTypeValues,
            'event_type_label' => $this->eventTypeLabel($eventTypeValues),
            'event_format' => $eventFormatValue,
            'event_format_label' => $this->eventFormatLabel($eventFormatValue),
            'reference_study_subtitle' => $event->reference_study_subtitle,
            'location' => $this->eventLocation($event),
            'is_remote' => in_array($eventFormatValue, [EventFormat::Online->value, EventFormat::Hybrid->value], true),
            'is_pending' => $statusValue === 'pending',
            'is_cancelled' => $statusValue === 'cancelled',
            'has_poster' => $event->hasMedia('poster'),
            'card_image_url' => $event->card_image_url,
            'institution' => $event->institution ? [
                'id' => $event->institution->id,
                'name' => $event->institution->name,
                'slug' => $event->institution->slug,
                'display_name' => $event->institution->display_name,
                'public_image_url' => $event->institution->public_image_url,
                'logo_url' => $event->institution->getFirstMediaUrl('logo', 'thumb') ?: $event->institution->getFirstMediaUrl('logo'),
            ] : null,
            'venue' => $event->venue ? [
                'id' => $event->venue->id,
                'name' => $event->venue->name,
                'slug' => $event->venue->slug,
            ] : null,
        ];
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
    private function institutionListData(Institution $institution): array
    {
        $eventsCount = (int) ($institution->events_count ?? 0);
        $media = $this->institutionCardMediaData($institution);
        $location = $this->addressLocation($institution->address);

        return [
            'id' => $institution->id,
            'slug' => $institution->slug,
            'name' => $institution->name,
            'nickname' => $institution->nickname,
            'display_name' => $institution->display_name,
            'events_count' => $eventsCount,
            'event_count' => $eventsCount,
            'public_image_url' => $media['public_image_url'],
            'image_url' => $media['image_url'],
            'logo_url' => $media['logo_url'],
            'cover_url' => $media['cover_url'],
            'location' => $location,
            'location_text' => $location,
        ];
    }

    /**
     * @return array{public_image_url: string, image_url: string, logo_url: string, cover_url: ?string}
     */
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
            'image_url' => $publicImageUrl,
            'logo_url' => $resolvedLogoUrl,
            'cover_url' => $coverUrl !== '' ? $coverUrl : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function speakerListData(Speaker $speaker, ?User $user = null): array
    {
        $attributes = $speaker->getAttributes();
        $isFollowing = array_key_exists('is_following', $attributes)
            ? (bool) $attributes['is_following']
            : ($user?->isFollowing($speaker) ?? false);

        return [
            'id' => $speaker->id,
            'slug' => $speaker->slug,
            'name' => $speaker->name,
            'formatted_name' => $speaker->formatted_name,
            'events_count' => (int) ($speaker->events_count ?? 0),
            'avatar_url' => $speaker->public_avatar_url,
            'is_following' => $isFollowing,
        ];
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

    /**
     * @return array{id: string, name: string, display_name: string, slug: string, position: ?string, is_primary: bool, public_image_url: string, logo_url: string, cover_url: ?string, chip_image_url: string}
     */
    private function speakerInstitutionData(Institution $institution): array
    {
        $media = $this->institutionCardMediaData($institution);
        $logoUrl = $media['logo_url'];
        $coverUrl = $media['cover_url'];
        $publicImageUrl = $media['public_image_url'];
        $position = data_get($institution, 'pivot.position');
        $isPrimary = data_get($institution, 'pivot.is_primary');

        return [
            'id' => $institution->id,
            'name' => $institution->name,
            'display_name' => $institution->display_name,
            'slug' => $institution->slug,
            'position' => is_string($position) && $position !== '' ? $position : null,
            'is_primary' => (bool) $isPrimary,
            'public_image_url' => $publicImageUrl,
            'logo_url' => $logoUrl,
            'cover_url' => $coverUrl,
            'chip_image_url' => $publicImageUrl,
        ];
    }

    /**
     * @return list<array{id: string, name: string, url: string, thumb_url: string}>
     */
    private function speakerGalleryData(Speaker $speaker): array
    {
        return $speaker->getMedia('gallery')
            ->map(fn (Media $media): array => [
                'id' => (string) $media->getKey(),
                'name' => $media->name,
                'url' => $media->getUrl(),
                'thumb_url' => $media->getAvailableUrl(['gallery_thumb']) ?: $media->getUrl(),
            ])
            ->all();
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

    /**
     * @return list<string>
     */
    private function eventTypeValues(Event $event): array
    {
        $eventType = $event->event_type;

        if ($eventType instanceof Collection) {
            return $eventType
                ->map(fn (EventType $value): string => $value->value)
                ->filter(fn (string $value): bool => $value !== '')
                ->values()
                ->all();
        }

        if (is_array($eventType)) {
            return array_values(array_filter(array_map(strval(...), $eventType), static fn (string $value): bool => $value !== ''));
        }

        $value = $this->enumValue($eventType);

        return $value !== '' ? [$value] : [];
    }

    /**
     * @param  list<string>  $eventTypeValues
     */
    private function eventTypeLabel(array $eventTypeValues): string
    {
        $value = $eventTypeValues[0] ?? null;

        if (! is_string($value) || $value === '') {
            return __('Umum');
        }

        return EventType::tryFrom($value)?->getLabel() ?? __('Umum');
    }

    private function eventFormatLabel(string $eventFormatValue): string
    {
        if ($eventFormatValue === '') {
            return EventFormat::Physical->getLabel();
        }

        return EventFormat::tryFrom($eventFormatValue)?->getLabel() ?? Str::headline($eventFormatValue);
    }

    private function eventLocation(Event $event): ?string
    {
        $venue = $event->venue;
        $institution = $event->institution;
        $primaryLocationName = $venue?->name ?: $institution?->name;
        $address = $venue?->addressModel;

        if (! $address instanceof Address) {
            $address = $institution?->addressModel;
        }

        $parts = array_values(array_filter([
            $primaryLocationName,
            ...AddressHierarchyFormatter::parts($address),
        ], static fn (mixed $value): bool => is_string($value) && $value !== ''));

        if ($parts === []) {
            return null;
        }

        return implode(', ', $parts);
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
        $location = AddressHierarchyFormatter::format($address);

        return $location !== '' ? $location : null;
    }
}
