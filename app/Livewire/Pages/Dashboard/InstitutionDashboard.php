<?php

namespace App\Livewire\Pages\Dashboard;

use App\Actions\Membership\AddMemberToSubject;
use App\Actions\Membership\ChangeSubjectMemberRole;
use App\Actions\Membership\RemoveMemberFromSubject;
use App\Enums\EventVisibility;
use App\Enums\MemberSubjectType;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Event;
use App\Models\Institution;
use App\Models\User;
use App\Support\Authz\MemberRoleCatalog;
use App\Support\Authz\ScopedMemberRoleSeeder;
use App\Support\Timezone\UserDateTimeFormatter;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class InstitutionDashboard extends Component implements HasForms, HasTable
{
    use InteractsWithActions;
    use InteractsWithForms;
    use InteractsWithTable {
        bootedInteractsWithTable as protected filamentBootedInteractsWithTable;
    }
    use InteractsWithToasts;

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

        $this->eventStatus = $this->normalizeEventStatus($this->eventStatus);
        $this->eventVisibility = $this->normalizeEventVisibility($this->eventVisibility);
        $this->eventSort = $this->normalizeEventSort($this->eventSort);
        $this->eventPerPage = $this->normalizeEventPerPage($this->eventPerPage);
    }

    public function bootedInteractsWithTable(): void
    {
        $this->filamentBootedInteractsWithTable();
        $this->syncTableStateFromLegacyQuery();
    }

    public function getTablePaginationPageName(): string
    {
        return 'institution_events_page';
    }

    public function updatedInstitutionId(?string $institutionId): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $this->institutionId = $this->normalizeInstitutionId($institutionId);

        if ($this->institutionId === null) {
            $this->flushCachedTableRecords();
            $this->resetPage();
            $this->resetPage('institution_members_page');
            $this->resetMemberEditor();

            return;
        }

        if (! $this->availableInstitutionsQuery($user)->whereKey($this->institutionId)->exists()) {
            abort(403);
        }

        $this->flushCachedTableRecords();
        $this->resetPage();
        $this->resetPage('institution_members_page');
        $this->resetMemberEditor();
    }

    public function updatedEventSort(string $value): void
    {
        $this->eventSort = $this->normalizeEventSort($value);
        $this->tableSort = null;

        $this->flushCachedTableRecords();
        $this->resetPage();
    }

    public function updated(string $name, mixed $value): void
    {
        if ($name === 'tableSearch') {
            $this->eventSearch = $this->tableSearch ?? '';

            return;
        }

        if (str($name)->startsWith('tableFilters')) {
            $this->syncLegacyFilterStateFromTable();

            return;
        }

        if ($name === 'tableSort') {
            $this->eventSort = $this->legacyEventSortFromTableState();

            return;
        }

        if ($name === 'tableRecordsPerPage') {
            $this->eventPerPage = $this->normalizeEventPerPage($this->tableRecordsPerPage ?? 8);
        }
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

        app(AddMemberToSubject::class)->handle($institution, $member, $validated['newMemberRoleId']);

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

        if ($this->memberHasProtectedRole($member)) {
            $this->errorToast(__('Owner roles can only be changed from the global roles screen.'));

            return;
        }

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

        if ($this->memberHasProtectedRole($member)) {
            $this->resetMemberEditor();
            $this->errorToast(__('Owner roles can only be changed from the global roles screen.'));

            return;
        }

        $validated = $this->validate($this->memberRoleRules());

        app(ChangeSubjectMemberRole::class)->handle($institution, $member, $validated['editingMemberRoleId']);

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

        app(RemoveMemberFromSubject::class)->handle($institution, $member);

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

        return app(MemberRoleCatalog::class)->roleOptionsFor(MemberSubjectType::Institution);
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

    public function table(Table $table): Table
    {
        return $table
            ->query(function (): Builder {
                $institution = $this->selectedInstitution();

                if (! $institution instanceof Institution) {
                    return Event::query()->whereRaw('1 = 0');
                }

                return Event::query()
                    ->where('institution_id', $institution->id)
                    ->with([
                        'space:id,name',
                        'speakers:id,name',
                        'references:id,title',
                    ])
                    ->withCount(['registrations as dashboard_registrations_count']);
            })
            ->columns([
                TextColumn::make('title')
                    ->label(__('Title'))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        $search = '%'.mb_strtolower(trim($search)).'%';

                        return $query->where(function (Builder $builder) use ($search): void {
                            $builder
                                ->whereRaw('LOWER(title) LIKE ?', [$search])
                                ->orWhereHas('venue', fn (Builder $venueQuery) => $venueQuery->whereRaw('LOWER(name) LIKE ?', [$search]));
                        });
                    })
                    ->sortable()
                    ->html()
                    ->wrap()
                    ->formatStateUsing(fn (Event $record): HtmlString => new HtmlString(view(
                        'livewire.pages.dashboard.partials.institution-event-title-cell',
                        [
                            'event' => $record,
                            'selectedInstitutionId' => $this->institutionId,
                        ],
                    )->render())),
                TextColumn::make('starts_at')
                    ->label(__('Date'))
                    ->state(fn (Event $record): string => $this->formatEventSchedule($record))
                    ->sortable()
                    ->wrap(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $this->translateStatusLabel($state))
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'pending' => 'warning',
                        'needs_changes' => 'info',
                        'rejected', 'cancelled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('speaker_names')
                    ->label(__('Speakers'))
                    ->state(fn (Event $record): array => $record->speakers
                        ->pluck('name')
                        ->filter(fn (mixed $name): bool => is_string($name) && trim($name) !== '')
                        ->map(fn (string $name): string => trim($name))
                        ->values()
                        ->all())
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->placeholder('-')
                    ->wrap()
                    ->tooltip(fn (Event $record): ?string => $record->speakers
                        ->pluck('name')
                        ->filter(fn (mixed $name): bool => is_string($name) && trim($name) !== '')
                        ->map(fn (string $name): string => trim($name))
                        ->implode(', ') ?: null),
                TextColumn::make('reference_titles')
                    ->label(__('References'))
                    ->state(fn (Event $record): array => $record->references
                        ->pluck('title')
                        ->filter(fn (mixed $title): bool => is_string($title) && trim($title) !== '')
                        ->map(fn (string $title): string => trim($title))
                        ->values()
                        ->all())
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList()
                    ->placeholder('-')
                    ->wrap()
                    ->tooltip(fn (Event $record): ?string => $record->references
                        ->pluck('title')
                        ->filter(fn (mixed $title): bool => is_string($title) && trim($title) !== '')
                        ->map(fn (string $title): string => trim($title))
                        ->implode(', ') ?: null),
                TextColumn::make('space.name')
                    ->label(__('Location'))
                    ->placeholder('-')
                    ->wrap(),
            ])
            ->defaultSort(fn (Builder $query): string|Builder|null => $this->applyLegacyEventSort($query), fn (): string => $this->legacyEventSortDirection())
            ->searchPlaceholder(__('Search by event title or venue'))
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(collect($this->eventStatusOptions())->except('all')->all()),
                SelectFilter::make('visibility')
                    ->label(__('Visibility'))
                    ->options(collect($this->eventVisibilityOptions())->except('all')->all()),
            ])
            ->deferFilters(false)
            ->filtersFormColumns(2)
            ->filtersFormWidth(Width::Medium)
            ->paginated([8, 15, 25])
            ->defaultPaginationPageOption(8)
            ->recordClasses(fn (Event $record): ?string => (string) $record->status === 'pending' ? 'bg-amber-50/80' : null)
            ->stackedOnMobile()
            ->scrollToTopOnPageChange()
            ->emptyStateHeading(__('No events match the current filters.'))
            ->emptyStateDescription(__('Try adjusting the search or filters.'));
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

    protected function translateStatusLabel(string $status): string
    {
        $translated = __($status);

        if ($translated !== $status) {
            return $translated;
        }

        return str($status)->replace('_', ' ')->headline()->toString();
    }

    protected function formatEventSchedule(Event $event): string
    {
        if (! $event->starts_at) {
            return __('TBC');
        }

        $date =
            UserDateTimeFormatter::translatedFormat($event->starts_at, 'd M Y');
        $time = $event->isPrayerRelative()
            ? (string) $event->timing_display
            : UserDateTimeFormatter::translatedFormat($event->starts_at, 'h:i A');

        return $date.', '.$time;
    }

    /**
     * @param  Builder<Event>  $query
     * @return Builder<Event>|string|null
     */
    protected function applyLegacyEventSort(Builder $query): Builder|string|null
    {
        if (filled($this->tableSort)) {
            return null;
        }

        return match ($this->eventSort) {
            'starts_asc', 'starts_desc' => 'starts_at',
            'title_asc', 'title_desc' => 'title',
            'registrations_desc' => 'dashboard_registrations_count',
            'pending_first' => $query
                ->orderByRaw("CASE WHEN status = 'pending' THEN 0 ELSE 1 END")
                ->orderBy('starts_at')
                ->orderBy('title'),
            default => 'starts_at',
        };
    }

    protected function legacyEventSortDirection(): string
    {
        return match ($this->eventSort) {
            'starts_asc', 'title_asc', 'pending_first' => 'asc',
            default => 'desc',
        };
    }

    protected function syncTableStateFromLegacyQuery(): void
    {
        $this->eventStatus = $this->normalizeEventStatus($this->eventStatus);
        $this->eventVisibility = $this->normalizeEventVisibility($this->eventVisibility);
        $this->eventSort = $this->normalizeEventSort($this->eventSort);
        $this->eventPerPage = $this->normalizeEventPerPage($this->eventPerPage);

        $this->tableSearch = $this->eventSearch;
        $this->tableFilters = array_filter([
            'status' => $this->eventStatus === 'all' ? null : ['value' => $this->eventStatus],
            'visibility' => $this->eventVisibility === 'all' ? null : ['value' => $this->eventVisibility],
        ], fn (?array $filter): bool => $filter !== null);
        $this->tableDeferredFilters = $this->tableFilters;
        $this->tableRecordsPerPage = $this->eventPerPage;

        $this->getTableFiltersForm()->fill($this->tableFilters);
    }

    protected function syncLegacyFilterStateFromTable(): void
    {
        $this->eventStatus = $this->normalizeEventStatus((string) (data_get($this->getTableFilterState('status'), 'value') ?? 'all'));
        $this->eventVisibility = $this->normalizeEventVisibility((string) (data_get($this->getTableFilterState('visibility'), 'value') ?? 'all'));
    }

    protected function legacyEventSortFromTableState(): string
    {
        return match ($this->tableSort) {
            'starts_at:asc', 'starts_at' => 'starts_asc',
            'starts_at:desc' => 'starts_desc',
            'title:asc', 'title' => 'title_asc',
            'title:desc' => 'title_desc',
            null, '' => 'starts_desc',
            default => 'starts_desc',
        };
    }

    /**
     * @return list<string>
     */
    protected function getMemberRoleIds(User $user): array
    {
        return app(MemberRoleCatalog::class)->roleIdsFor($user, MemberSubjectType::Institution);
    }

    /**
     * @return list<string>
     */
    protected function getMemberRoleNames(User $user): array
    {
        return app(MemberRoleCatalog::class)->roleNamesFor($user, MemberSubjectType::Institution);
    }

    protected function userHasInstitutionManagementRole(User $user): bool
    {
        return app(MemberRoleCatalog::class)->userHasAnyRole($user, MemberSubjectType::Institution, ['owner', 'admin']);
    }

    protected function memberIsOwner(User $user): bool
    {
        return app(MemberRoleCatalog::class)->userHasRole($user, MemberSubjectType::Institution, 'owner');
    }

    protected function memberHasProtectedRole(User $user): bool
    {
        return app(MemberRoleCatalog::class)->userHasProtectedRole($user, MemberSubjectType::Institution);
    }

    public function render(): View
    {
        return view('livewire.pages.dashboard.institution-dashboard');
    }
}
