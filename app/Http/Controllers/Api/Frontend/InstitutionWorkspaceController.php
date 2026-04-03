<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Actions\Membership\AddMemberToSubject;
use App\Actions\Membership\ChangeSubjectMemberRole;
use App\Actions\Membership\RemoveMemberFromSubject;
use App\Enums\EventVisibility;
use App\Enums\MemberSubjectType;
use App\Models\Event;
use App\Models\Institution;
use App\Models\User;
use App\Support\Authz\MemberRoleCatalog;
use App\Support\Authz\ScopedMemberRoleSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class InstitutionWorkspaceController extends FrontendController
{
    public function __construct(
        private readonly MemberRoleCatalog $memberRoleCatalog,
        private readonly ScopedMemberRoleSeeder $scopedMemberRoleSeeder,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);

        abort_unless($this->availableInstitutionsQuery($user)->exists(), 403);

        $institutionId = $this->normalizeInstitutionId($request->query('institution_id'));

        if ($institutionId === null) {
            $institutionId = $this->availableInstitutionsQuery($user)
                ->orderBy('name')
                ->value('institutions.id');
        }

        if ($institutionId !== null && ! $this->availableInstitutionsQuery($user)->whereKey($institutionId)->exists()) {
            abort(403);
        }

        $institution = $institutionId !== null
            ? $this->availableInstitutionsQuery($user)
                ->withCount([
                    'events',
                    'events as public_events_count' => function (Builder $query): void {
                        $query
                            ->where('events.is_active', true)
                            ->whereIn('events.status', Event::PUBLIC_STATUSES)
                            ->where('events.visibility', EventVisibility::Public);
                    },
                ])
                ->whereKey($institutionId)
                ->first()
            : null;

        $eventSearch = trim((string) $request->query('event_search', ''));
        $eventStatus = $this->normalizeEventStatus((string) $request->query('event_status', 'all'));
        $eventVisibility = $this->normalizeEventVisibility((string) $request->query('event_visibility', 'all'));
        $eventSort = $this->normalizeEventSort((string) $request->query('event_sort', 'starts_desc'));
        $eventPerPage = $this->normalizeEventPerPage($request->integer('event_per_page', 8));

        $events = $this->institutionEvents($institution, $eventSearch, $eventStatus, $eventVisibility, $eventSort, $eventPerPage);
        $members = $this->institutionMembers($institution);

        return response()->json([
            'data' => [
                'institutions' => $this->availableInstitutionsQuery($user)
                    ->withCount([
                        'events',
                        'events as public_events_count' => function (Builder $query): void {
                            $query
                                ->where('events.is_active', true)
                                ->whereIn('events.status', Event::PUBLIC_STATUSES)
                                ->where('events.visibility', EventVisibility::Public);
                        },
                    ])
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Institution $availableInstitution): array => [
                        'id' => $availableInstitution->getKey(),
                        'name' => $availableInstitution->name,
                        'display_name' => $availableInstitution->display_name,
                        'events_count' => $this->countValue($availableInstitution, 'events_count'),
                        'public_events_count' => $this->countValue($availableInstitution, 'public_events_count'),
                    ])->all(),
                'selected_institution' => $institution instanceof Institution
                    ? [
                        'id' => $institution->getKey(),
                        'name' => $institution->name,
                        'display_name' => $institution->display_name,
                        'events_count' => $this->countValue($institution, 'events_count'),
                        'public_events_count' => $this->countValue($institution, 'public_events_count'),
                        'internal_events_count' => max(
                            $this->countValue($institution, 'events_count') - $this->countValue($institution, 'public_events_count'),
                            0,
                        ),
                    ]
                    : null,
                'events' => $events->getCollection()->map(fn (Event $event): array => [
                    'id' => $event->getKey(),
                    'title' => $event->title,
                    'slug' => $event->slug,
                    'status' => (string) $event->status,
                    'visibility' => $this->enumValue($event->visibility),
                    'starts_at' => $this->optionalDateTimeString($event->starts_at),
                    'registrations_count' => (int) ($event->registrations_count ?? 0),
                    'venue' => $event->venue?->only(['id', 'name']),
                ])->all(),
                'event_filters' => [
                    'search' => $eventSearch,
                    'status' => $eventStatus,
                    'visibility' => $eventVisibility,
                    'sort' => $eventSort,
                    'per_page' => $eventPerPage,
                ],
                'members' => $members->getCollection()->map(fn (User $member): array => [
                    'id' => $member->getKey(),
                    'name' => $member->name,
                    'email' => $member->email,
                    'roles' => $this->memberRoleCatalog->roleNamesFor($member, MemberSubjectType::Institution),
                    'role_ids' => $this->memberRoleCatalog->roleIdsFor($member, MemberSubjectType::Institution),
                    'is_owner' => $this->memberIsOwner($member),
                    'has_protected_role' => $this->memberHasProtectedRole($member),
                ])->all(),
                'can_manage_members' => $institution instanceof Institution && $this->userHasInstitutionManagementRole($user),
                'institution_role_options' => $this->institutionRoleOptions(),
            ],
            'meta' => [
                'events_pagination' => $this->paginatorData($events),
                'members_pagination' => $this->paginatorData($members),
                'request_id' => $this->requestId($request),
            ],
        ]);
    }

    public function addMember(
        string $institutionId,
        Request $request,
        AddMemberToSubject $addMemberToSubject,
    ): JsonResponse {
        $user = $this->requireUser($request);
        $institution = $this->selectedInstitutionOrAbort($user, $institutionId);
        $this->ensureCanManageMembers($user, $institution);

        $validated = $request->validate([
            'email' => ['required', 'email'],
            'role_id' => ['required', 'string'],
        ]);

        $member = User::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower(trim((string) $validated['email']))])
            ->first();

        if (! $member instanceof User) {
            throw ValidationException::withMessages([
                'email' => __('We could not find a user with that email address.'),
            ]);
        }

        $addMemberToSubject->handle($institution, $member, (string) $validated['role_id']);

        return response()->json([
            'data' => [
                'member' => [
                    'id' => $member->getKey(),
                    'name' => $member->name,
                    'email' => $member->email,
                    'roles' => $this->memberRoleCatalog->roleNamesFor($member, MemberSubjectType::Institution),
                ],
            ],
            'meta' => [
                'request_id' => $this->requestId($request),
            ],
        ], 201);
    }

    public function updateMemberRole(
        string $institutionId,
        string $memberId,
        Request $request,
        ChangeSubjectMemberRole $changeSubjectMemberRole,
    ): JsonResponse {
        $user = $this->requireUser($request);
        $institution = $this->selectedInstitutionOrAbort($user, $institutionId);
        $this->ensureCanManageMembers($user, $institution);

        $member = $institution->members()->whereKey($memberId)->first();
        abort_unless($member instanceof User, 404);

        if ($this->memberHasProtectedRole($member)) {
            throw ValidationException::withMessages([
                'member' => __('Owner roles can only be changed from the global roles screen.'),
            ]);
        }

        $validated = $request->validate([
            'role_id' => ['required', 'string'],
        ]);

        $changeSubjectMemberRole->handle($institution, $member, (string) $validated['role_id']);

        return response()->json([
            'data' => [
                'member' => [
                    'id' => $member->getKey(),
                    'name' => $member->name,
                    'email' => $member->email,
                    'roles' => $this->memberRoleCatalog->roleNamesFor($member->fresh() ?? $member, MemberSubjectType::Institution),
                ],
            ],
            'meta' => [
                'request_id' => $this->requestId($request),
            ],
        ]);
    }

    public function removeMember(
        string $institutionId,
        string $memberId,
        Request $request,
        RemoveMemberFromSubject $removeMemberFromSubject,
    ): JsonResponse {
        $user = $this->requireUser($request);
        $institution = $this->selectedInstitutionOrAbort($user, $institutionId);
        $this->ensureCanManageMembers($user, $institution);

        $member = $institution->members()->whereKey($memberId)->first();
        abort_unless($member instanceof User, 404);

        if ($this->memberIsOwner($member)) {
            throw ValidationException::withMessages([
                'member' => __('Institution owners cannot be removed from this dashboard.'),
            ]);
        }

        try {
            $removeMemberFromSubject->handle($institution, $member);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages([
                'member' => __('Owner roles can only be changed from the global roles screen.'),
            ]);
        }

        return response()->json([
            'data' => [
                'removed_member_id' => $memberId,
            ],
            'meta' => [
                'request_id' => $this->requestId($request),
            ],
        ]);
    }

    /**
     * @return BelongsToMany<Institution, User>
     */
    private function availableInstitutionsQuery(User $user): BelongsToMany
    {
        return $user->institutions();
    }

    private function normalizeInstitutionId(mixed $institutionId): ?string
    {
        if (! is_string($institutionId) || trim($institutionId) === '') {
            return null;
        }

        return $institutionId;
    }

    private function normalizeEventStatus(string $value): string
    {
        return in_array($value, ['all', 'pending', 'draft', 'approved', 'needs_changes', 'rejected', 'cancelled'], true)
            ? $value
            : 'all';
    }

    private function normalizeEventVisibility(string $value): string
    {
        return in_array($value, ['all', EventVisibility::Public->value, EventVisibility::Unlisted->value, EventVisibility::Private->value], true)
            ? $value
            : 'all';
    }

    private function normalizeEventSort(string $value): string
    {
        return in_array($value, ['starts_desc', 'starts_asc', 'title_asc', 'title_desc', 'registrations_desc', 'pending_first'], true)
            ? $value
            : 'starts_desc';
    }

    private function normalizeEventPerPage(int|string $value): int
    {
        $perPage = (int) $value;

        return in_array($perPage, [8, 15, 25], true) ? $perPage : 8;
    }

    private function selectedInstitutionOrAbort(User $user, string $institutionId): Institution
    {
        $institution = $this->availableInstitutionsQuery($user)->whereKey($institutionId)->first();

        abort_unless($institution instanceof Institution, 404);

        return $institution;
    }

    private function ensureCanManageMembers(User $user, Institution $institution): void
    {
        abort_unless($this->userHasInstitutionManagementRole($user), 403);
    }

    private function userHasInstitutionManagementRole(User $user): bool
    {
        return $this->memberRoleCatalog->userHasAnyRole($user, MemberSubjectType::Institution, ['owner', 'admin']);
    }

    private function memberIsOwner(User $user): bool
    {
        return $this->memberRoleCatalog->userHasRole($user, MemberSubjectType::Institution, 'owner');
    }

    private function memberHasProtectedRole(User $user): bool
    {
        return $this->memberRoleCatalog->userHasProtectedRole($user, MemberSubjectType::Institution);
    }

    /**
     * @return LengthAwarePaginator<int, Event>
     */
    private function institutionEvents(
        ?Institution $institution,
        string $eventSearch,
        string $eventStatus,
        string $eventVisibility,
        string $eventSort,
        int $eventPerPage,
    ): LengthAwarePaginator {
        if (! $institution instanceof Institution) {
            /** @var LengthAwarePaginator<int, Event> $paginator */
            $paginator = Event::query()->whereRaw('1 = 0')->paginate(perPage: $eventPerPage);

            return $paginator;
        }

        $query = Event::query()
            ->where('institution_id', $institution->getKey())
            ->with(['venue:id,name'])
            ->withCount('registrations');

        if ($eventSearch !== '') {
            $search = '%'.mb_strtolower(trim($eventSearch)).'%';

            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->whereRaw('LOWER(title) LIKE ?', [$search])
                    ->orWhereHas('venue', fn (Builder $venueQuery) => $venueQuery->whereRaw('LOWER(name) LIKE ?', [$search]));
            });
        }

        if ($eventStatus !== 'all') {
            $query->where('status', $eventStatus);
        }

        if ($eventVisibility !== 'all') {
            $query->where('visibility', $eventVisibility);
        }

        match ($eventSort) {
            'starts_asc' => $query->orderBy('starts_at')->orderBy('title'),
            'title_asc' => $query->orderBy('title')->orderBy('starts_at', 'desc'),
            'title_desc' => $query->orderByDesc('title')->orderBy('starts_at', 'desc'),
            'registrations_desc' => $query->orderByDesc('registrations_count')->orderBy('starts_at', 'desc'),
            'pending_first' => $query
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderBy('starts_at')
                ->orderBy('title'),
            default => $query->orderByDesc('starts_at')->orderBy('title'),
        };

        /** @var LengthAwarePaginator<int, Event> $paginator */
        $paginator = $query->paginate($eventPerPage);

        return $paginator;
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    private function institutionMembers(?Institution $institution): LengthAwarePaginator
    {
        if (! $institution instanceof Institution) {
            /** @var LengthAwarePaginator<int, User> $paginator */
            $paginator = User::query()->whereRaw('1 = 0')->paginate(8);

            return $paginator;
        }

        /** @var LengthAwarePaginator<int, User> $paginator */
        $paginator = User::query()
            ->whereIn('id', $institution->members()->select('users.id'))
            ->orderBy('name')
            ->paginate(8);

        return $paginator;
    }

    /**
     * @return array<string, string>
     */
    private function institutionRoleOptions(): array
    {
        $this->scopedMemberRoleSeeder->ensureForInstitution();

        return $this->memberRoleCatalog->roleOptionsFor(MemberSubjectType::Institution);
    }

    /**
     * @param  LengthAwarePaginator<int, mixed>  $paginator
     * @return array<string, int>
     */
    private function paginatorData(LengthAwarePaginator $paginator): array
    {
        return [
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    private function countValue(Institution $institution, string $key): int
    {
        return (int) data_get($institution, $key, 0);
    }
}
