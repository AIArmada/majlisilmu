<?php

namespace App\Livewire\Pages\Dashboard;

use AIArmada\FilamentAuthz\Facades\Authz;
use AIArmada\FilamentAuthz\Models\AuthzScope;
use AIArmada\FilamentAuthz\Models\Role;
use App\Enums\EventVisibility;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Event;
use App\Models\Institution;
use App\Models\User;
use App\Support\Authz\MemberRoleScopes;
use App\Support\Authz\ScopedMemberRoleSeeder;
use App\Support\Submission\PublicSubmissionLockService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Spatie\Permission\PermissionRegistrar;

#[Layout('layouts.app')]
class InstitutionDashboard extends Component
{
    use InteractsWithToasts;
    use WithPagination;

    #[Url(as: 'institution')]
    public ?string $institutionId = null;

    #[Url(as: 'event_search', except: '')]
    public string $eventSearch = '';

    #[Url(as: 'event_status', except: 'all')]
    public string $eventStatus = 'all';

    #[Url(as: 'event_visibility', except: 'all')]
    public string $eventVisibility = 'all';

    #[Url(as: 'event_sort', except: 'starts_desc')]
    public string $eventSort = 'starts_desc';

    #[Url(as: 'event_per_page', except: 8)]
    public int $eventPerPage = 8;

    public string $newMemberEmail = '';

    public string $newMemberRoleId = '';

    public ?string $editingMemberId = null;

    public string $editingMemberRoleId = '';

    public function mount(): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        if (! $this->availableInstitutionsQuery($user)->exists()) {
            abort(403);
        }

        $this->institutionId = $this->normalizeInstitutionId($this->institutionId);

        if ($this->institutionId === null) {
            $this->institutionId = $this->availableInstitutionsQuery($user)
                ->orderBy('name')
                ->value('institutions.id');
        }

        if ($this->institutionId !== null && ! $this->availableInstitutionsQuery($user)->whereKey($this->institutionId)->exists()) {
            abort(403);
        }

        $this->eventPerPage = $this->normalizeEventPerPage($this->eventPerPage);
        $this->eventStatus = $this->normalizeEventStatus($this->eventStatus);
        $this->eventVisibility = $this->normalizeEventVisibility($this->eventVisibility);
        $this->eventSort = $this->normalizeEventSort($this->eventSort);
    }

    public function updatedInstitutionId(?string $institutionId): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $this->institutionId = $this->normalizeInstitutionId($institutionId);

        if ($this->institutionId === null) {
            $this->resetPage('institution_events_page');
            $this->resetPage('institution_members_page');
            $this->resetMemberEditor();

            return;
        }

        if (! $this->availableInstitutionsQuery($user)->whereKey($this->institutionId)->exists()) {
            abort(403);
        }

        $this->resetPage('institution_events_page');
        $this->resetPage('institution_members_page');
        $this->resetMemberEditor();
    }

    public function updatedEventSearch(): void
    {
        $this->resetPage('institution_events_page');
    }

    public function updatedEventStatus(string $value): void
    {
        $this->eventStatus = $this->normalizeEventStatus($value);
        $this->resetPage('institution_events_page');
    }

    public function updatedEventVisibility(string $value): void
    {
        $this->eventVisibility = $this->normalizeEventVisibility($value);
        $this->resetPage('institution_events_page');
    }

    public function updatedEventSort(string $value): void
    {
        $this->eventSort = $this->normalizeEventSort($value);
        $this->resetPage('institution_events_page');
    }

    public function updatedEventPerPage(int|string $value): void
    {
        $this->eventPerPage = $this->normalizeEventPerPage($value);
        $this->resetPage('institution_events_page');
    }

    public function addMember(): void
    {
        $institution = $this->selectedInstitutionOrAbort();

        $this->ensureCanManageMembers($institution);

        $validated = $this->validate($this->memberCreationRules());

        $email = mb_strtolower(trim((string) $validated['newMemberEmail']));
        $member = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();

        if (! $member instanceof User) {
            throw ValidationException::withMessages([
                'newMemberEmail' => __('We could not find a user with that email address.'),
            ]);
        }

        $institution->members()->syncWithoutDetaching([$member->id]);
        $this->syncMemberRoles($member, $validated['newMemberRoleId']);

        app(PublicSubmissionLockService::class)->ensureInstitutionUnlockedIfIneligible($institution->fresh());

        $this->newMemberEmail = '';
        $this->newMemberRoleId = '';
        $this->resetPage('institution_members_page');

        $this->successToast(__('Member added successfully.'));
    }

    public function startEditingMemberRoles(string $memberId): void
    {
        $institution = $this->selectedInstitutionOrAbort();

        $this->ensureCanManageMembers($institution);

        $member = $this->findInstitutionMember($memberId);

        $this->editingMemberId = $member->id;
        $this->editingMemberRoleId = $this->getMemberRoleIds($member)[0] ?? '';
    }

    public function cancelEditingMemberRoles(): void
    {
        $this->resetMemberEditor();
    }

    public function saveMemberRoles(): void
    {
        $institution = $this->selectedInstitutionOrAbort();

        $this->ensureCanManageMembers($institution);

        if (! is_string($this->editingMemberId) || $this->editingMemberId === '') {
            abort(404);
        }

        $member = $this->findInstitutionMember($this->editingMemberId);

        $validated = $this->validate($this->memberRoleRules());

        $this->syncMemberRoles($member, $validated['editingMemberRoleId']);
        app(PublicSubmissionLockService::class)->syncForUser($member);

        $this->resetMemberEditor();

        $this->successToast(__('Member roles updated.'));
    }

    public function removeMember(string $memberId): void
    {
        $institution = $this->selectedInstitutionOrAbort();

        $this->ensureCanManageMembers($institution);

        $member = $this->findInstitutionMember($memberId);

        if ($this->memberIsOwner($member)) {
            $this->errorToast(__('Institution owners cannot be removed from this dashboard.'));

            return;
        }

        $institution->members()->detach($member->id);
        $this->syncMemberRoles($member, null);
        app(PublicSubmissionLockService::class)->ensureInstitutionUnlockedIfIneligible($institution->fresh());

        if ($this->editingMemberId === $member->id) {
            $this->resetMemberEditor();
        }

        $this->resetPage('institution_members_page');

        $this->successToast(__('Member removed successfully.'));
    }

    /**
     * @return Collection<int, Institution>
     */
    #[Computed]
    public function institutions(): Collection
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return collect();
        }

        return $this->availableInstitutionsQuery($user)
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
            ->get();
    }

    #[Computed]
    public function selectedInstitution(): ?Institution
    {
        if ($this->institutionId === null || $this->institutionId === '') {
            return null;
        }

        /** @var Institution|null $institution */
        $institution = $this->institutions()
            ->firstWhere('id', $this->institutionId);

        return $institution;
    }

    /**
     * @return array{events_count:int,public_events_count:int,internal_events_count:int}
     */
    #[Computed]
    public function institutionStats(): array
    {
        $institution = $this->selectedInstitution();

        if (! $institution instanceof Institution) {
            return [
                'events_count' => 0,
                'public_events_count' => 0,
                'internal_events_count' => 0,
            ];
        }

        $totalEvents = (int) ($institution->events_count ?? 0);
        $publicEvents = (int) ($institution->public_events_count ?? 0);

        return [
            'events_count' => $totalEvents,
            'public_events_count' => $publicEvents,
            'internal_events_count' => max($totalEvents - $publicEvents, 0),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Event>
     */
    #[Computed]
    public function institutionEvents(): LengthAwarePaginator
    {
        $institution = $this->selectedInstitution();

        if (! $institution instanceof Institution) {
            return Event::query()
                ->whereRaw('1 = 0')
                ->paginate(perPage: $this->eventPerPage, pageName: 'institution_events_page');
        }

        $query = Event::query()
            ->where('institution_id', $institution->id)
            ->with(['venue:id,name'])
            ->withCount('registrations');

        if ($this->eventSearch !== '') {
            $search = '%'.mb_strtolower(trim($this->eventSearch)).'%';

            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->whereRaw('LOWER(title) LIKE ?', [$search])
                    ->orWhereHas('venue', fn (Builder $venueQuery) => $venueQuery->whereRaw('LOWER(name) LIKE ?', [$search]));
            });
        }

        if ($this->eventStatus !== 'all') {
            $query->where('status', $this->eventStatus);
        }

        if ($this->eventVisibility !== 'all') {
            $query->where('visibility', $this->eventVisibility);
        }

        $this->applyEventSort($query);

        return $query->paginate(perPage: $this->eventPerPage, pageName: 'institution_events_page');
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    #[Computed]
    public function institutionMembers(): LengthAwarePaginator
    {
        $institution = $this->selectedInstitution();

        if (! $institution instanceof Institution) {
            return User::query()
                ->whereRaw('1 = 0')
                ->paginate(perPage: 8, pageName: 'institution_members_page');
        }

        return User::query()
            ->whereIn('id', $institution->members()->select('users.id'))
            ->orderBy('name')
            ->paginate(perPage: 8, pageName: 'institution_members_page');
    }

    /**
     * @return array<string, list<string>>
     */
    #[Computed]
    public function institutionMemberRoleMap(): array
    {
        $roleMap = [];

        foreach ($this->institutionMembers()->items() as $member) {
            if (! $member instanceof User) {
                continue;
            }

            $roleMap[$member->id] = $this->getMemberRoleNames($member);
        }

        return $roleMap;
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function eventStatusOptions(): array
    {
        return [
            'all' => __('All statuses'),
            'pending' => __('Pending'),
            'draft' => __('Draft'),
            'approved' => __('Approved'),
            'needs_changes' => __('Needs Changes'),
            'rejected' => __('Rejected'),
            'cancelled' => __('Cancelled'),
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function eventVisibilityOptions(): array
    {
        return [
            'all' => __('All visibility'),
            EventVisibility::Public->value => __('Public'),
            EventVisibility::Unlisted->value => __('Unlisted'),
            EventVisibility::Private->value => __('Hidden'),
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function eventSortOptions(): array
    {
        return [
            'starts_desc' => __('Newest first'),
            'starts_asc' => __('Oldest first'),
            'title_asc' => __('Title A-Z'),
            'title_desc' => __('Title Z-A'),
            'registrations_desc' => __('Most registrations'),
            'pending_first' => __('Pending first'),
        ];
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function institutionRoleOptions(): array
    {
        app(ScopedMemberRoleSeeder::class)->ensureForInstitution();

        $teamsKey = app(PermissionRegistrar::class)->teamsKey;
        $scope = $this->getRoleScope();

        return Authz::withScope($scope, fn (): array => Role::query()
            ->where($teamsKey, getPermissionsTeamId())
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all());
    }

    #[Computed]
    public function canManageMembers(): bool
    {
        $institution = $this->selectedInstitution();
        $user = auth()->user();

        return $institution instanceof Institution
            && $user instanceof User
            && $this->userHasInstitutionManagementRole($user);
    }

    /**
     * @return BelongsToMany<Institution, User>
     */
    protected function availableInstitutionsQuery(User $user): BelongsToMany
    {
        return $user->institutions();
    }

    protected function normalizeInstitutionId(?string $institutionId): ?string
    {
        if ($institutionId === null || trim($institutionId) === '') {
            return null;
        }

        return $institutionId;
    }

    protected function normalizeEventStatus(string $value): string
    {
        return array_key_exists($value, $this->eventStatusOptions())
            ? $value
            : 'all';
    }

    protected function normalizeEventVisibility(string $value): string
    {
        return array_key_exists($value, $this->eventVisibilityOptions())
            ? $value
            : 'all';
    }

    protected function normalizeEventSort(string $value): string
    {
        return array_key_exists($value, $this->eventSortOptions())
            ? $value
            : 'starts_desc';
    }

    protected function normalizeEventPerPage(int|string $value): int
    {
        $perPage = (int) $value;

        return in_array($perPage, [8, 15, 25], true) ? $perPage : 8;
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    protected function memberCreationRules(): array
    {
        return [
            'newMemberEmail' => ['required', 'email'],
            'newMemberRoleId' => ['required', 'string'],
        ];
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    protected function memberRoleRules(): array
    {
        return [
            'editingMemberRoleId' => ['required', 'string'],
        ];
    }

    protected function selectedInstitutionOrAbort(): Institution
    {
        $institution = $this->selectedInstitution();

        abort_unless($institution instanceof Institution, 404);

        return $institution;
    }

    protected function findInstitutionMember(string $memberId): User
    {
        $member = $this->selectedInstitutionOrAbort()
            ->members()
            ->whereKey($memberId)
            ->first();

        abort_unless($member instanceof User, 404);

        return $member;
    }

    protected function resetMemberEditor(): void
    {
        $this->editingMemberId = null;
        $this->editingMemberRoleId = '';
    }

    protected function ensureCanManageMembers(Institution $institution): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User && $this->userHasInstitutionManagementRole($user), 403);
    }

    /**
     * @param  Builder<Event>  $query
     */
    protected function applyEventSort(Builder $query): void
    {
        match ($this->eventSort) {
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
    }

    /**
     * @return list<string>
     */
    protected function getMemberRoleIds(User $user): array
    {
        return Authz::withScope($this->getRoleScope(), fn (): array => $user->roles()->pluck('id')->all(), $user);
    }

    /**
     * @return list<string>
     */
    protected function getMemberRoleNames(User $user): array
    {
        return Authz::withScope($this->getRoleScope(), fn (): array => $user->getRoleNames()->values()->all(), $user);
    }

    protected function userHasInstitutionManagementRole(User $user): bool
    {
        return Authz::withScope(
            $this->getRoleScope(),
            fn (): bool => $user->hasAnyRole(['owner', 'admin']),
            $user,
        );
    }

    protected function memberIsOwner(User $user): bool
    {
        return Authz::withScope(
            $this->getRoleScope(),
            fn (): bool => $user->hasRole('owner'),
            $user,
        );
    }

    protected function syncMemberRoles(User $user, ?string $roleId): void
    {
        $validRoleIds = $roleId !== null && $roleId !== '' && array_key_exists($roleId, $this->institutionRoleOptions())
            ? [$roleId]
            : [];

        Authz::withScope($this->getRoleScope(), function () use ($user, $validRoleIds): void {
            $user->syncRoles($validRoleIds);
        }, $user);
    }

    protected function getRoleScope(): AuthzScope
    {
        return app(MemberRoleScopes::class)->institution();
    }

    public function render(): View
    {
        return view('livewire.pages.dashboard.institution-dashboard');
    }
}
