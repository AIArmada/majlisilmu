<?php

namespace App\Livewire\Pages\Dashboard;

use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventSubmission;
use App\Models\Registration;
use App\Models\User;
use App\Support\Timezone\UserDateTimeFormatter;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class UserDashboard extends Component
{
    use WithPagination;

    private const AGENDA_PER_PAGE = 6;

    private const PLANNER_BUCKET_PER_PAGE = 3;

    private const SUBMITTED_PER_PAGE = 4;

    private const CHECKINS_PER_PAGE = 6;

    public function mount(): void
    {
        abort_unless(auth()->user() instanceof User, 403);
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function summaryStats(): array
    {
        $user = $this->user();
        $upcomingAgenda = $this->upcomingAgenda();
        $submittedEvents = $this->submittedEvents();

        return [
            'planning_count' => $upcomingAgenda->count(),
            'going_count' => $this->countCalendarEntriesForRole('going', futureOnly: true),
            'registered_count' => $this->countCalendarEntriesForRole('registered', futureOnly: true),
            'interested_count' => $this->countCalendarEntriesForRole('interested', futureOnly: true),
            'saved_count' => $this->countCalendarEntriesForRole('saved', futureOnly: true),
            'submitted_count' => $submittedEvents->count(),
            'checkins_count' => EventCheckin::query()->where('user_id', $user->id)->count(),
            'institutions_count' => $user->institutions()->count(),
        ];
    }

    /**
     * @return Collection<int, Event>
     */
    #[Computed]
    public function savedEvents(): Collection
    {
        return $this->sortEventsForPlanner(
            $this->savedEventsQuery($this->user())
                ->with($this->plannerEventRelations())
                ->get()
        );
    }

    /**
     * @return Collection<int, Event>
     */
    #[Computed]
    public function interestedEvents(): Collection
    {
        return $this->sortEventsForPlanner(
            $this->interestedEventsQuery($this->user())
                ->with($this->plannerEventRelations())
                ->get()
        );
    }

    /**
     * @return Collection<int, Event>
     */
    #[Computed]
    public function goingEvents(): Collection
    {
        return $this->sortEventsForPlanner(
            $this->goingEventsQuery($this->user())
                ->with($this->plannerEventRelations())
                ->get()
        );
    }

    /**
     * @return Collection<int, Registration>
     */
    #[Computed]
    public function registeredEvents(): Collection
    {
        /** @var Collection<int, Registration> $registrations */
        $registrations = Registration::query()
            ->where('user_id', $this->user()->id)
            ->with([
                'event' => fn ($query) => $query->with($this->plannerEventRelations()),
            ])
            ->latest()
            ->get()
            ->filter(fn (Registration $registration): bool => $registration->event instanceof Event)
            ->values();

        return $this->sortRegistrationsForPlanner($registrations);
    }

    /**
     * @return Collection<int, EventSubmission>
     */
    #[Computed]
    public function submittedEvents(): Collection
    {
        /** @var Collection<int, EventSubmission> $submissions */
        $submissions = EventSubmission::query()
            ->where('submitted_by', $this->user()->id)
            ->with([
                'event' => fn ($query) => $query->with($this->plannerEventRelations()),
            ])
            ->latest()
            ->get()
            ->filter(fn (EventSubmission $submission): bool => $submission->event instanceof Event)
            ->values();

        return $submissions;
    }

    /**
     * @return Collection<int, EventCheckin>
     */
    #[Computed]
    public function recentCheckins(): Collection
    {
        /** @var Collection<int, EventCheckin> $checkins */
        $checkins = EventCheckin::query()
            ->where('user_id', $this->user()->id)
            ->with([
                'event' => fn ($query) => $query->with($this->plannerEventRelations()),
            ])
            ->orderByDesc('checked_in_at')
            ->get();

        return $checkins;
    }

    /**
     * @return list<array<string, mixed>>
     */
    #[Computed]
    public function calendarEntries(): array
    {
        /** @var array<string, array<string, mixed>> $entries */
        $entries = [];
        $savedEvents = $this->savedEvents();
        $interestedEvents = $this->interestedEvents();
        $goingEvents = $this->goingEvents();
        $registeredEvents = $this->registeredEvents();
        $submittedEvents = $this->submittedEvents();
        $recentCheckins = $this->recentCheckins();

        foreach ($savedEvents as $event) {
            $this->mergeEventIntoCalendarEntries($entries, $event, 'saved');
        }

        foreach ($interestedEvents as $event) {
            $this->mergeEventIntoCalendarEntries($entries, $event, 'interested');
        }

        foreach ($goingEvents as $event) {
            $this->mergeEventIntoCalendarEntries($entries, $event, 'going');
        }

        foreach ($registeredEvents as $registration) {
            if (! $registration->event instanceof Event || ! in_array($registration->status, ['registered', 'attended'], true)) {
                continue;
            }

            $this->mergeEventIntoCalendarEntries($entries, $registration->event, 'registered');
        }

        foreach ($submittedEvents as $submission) {
            if (! $submission->event instanceof Event) {
                continue;
            }

            $this->mergeEventIntoCalendarEntries($entries, $submission->event, 'submitted');
        }

        foreach ($recentCheckins as $checkin) {
            $this->mergeCheckinIntoCalendarEntries($entries, $checkin);
        }

        /** @var list<array<string, mixed>> $entryList */
        $entryList = array_values($entries);

        /** @var list<array<string, mixed>> $sorted */
        $sorted = collect($entryList)
            ->sortBy([
                ['date_key', 'asc'],
                ['starts_at_ts', 'asc'],
                ['title', 'asc'],
            ])
            ->values()
            ->all();

        return $sorted;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    #[Computed]
    public function calendarEntriesByDate(): array
    {
        /** @var list<array<string, mixed>> $entries */
        $entries = $this->calendarEntries();

        /** @var array<string, list<array<string, mixed>>> $grouped */
        $grouped = collect($entries)
            ->groupBy('date_key')
            ->map(fn (Collection $entries): array => $entries->values()->all())
            ->all();

        return $grouped;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    #[Computed]
    public function calendarFilters(): array
    {
        $definitions = $this->roleDefinitions();
        $counts = array_fill_keys(array_keys($definitions), 0);
        /** @var list<array<string, mixed>> $entries */
        $entries = $this->calendarEntries();

        foreach ($entries as $entry) {
            $roles = $entry['roles'] ?? [];

            if (! is_array($roles)) {
                continue;
            }

            foreach ($roles as $role) {
                if (! is_string($role) || ! array_key_exists($role, $counts)) {
                    continue;
                }

                $counts[$role]++;
            }
        }

        foreach ($definitions as $role => $definition) {
            $definitions[$role]['count'] = $counts[$role];
        }

        return $definitions;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function upcomingAgenda(): Collection
    {
        $timezone = UserDateTimeFormatter::resolveTimezone();
        $nowTimestamp = now($timezone)->getTimestamp();
        /** @var list<array<string, mixed>> $entries */
        $entries = $this->calendarEntries();

        /** @var Collection<int, array<string, mixed>> $agenda */
        $agenda = collect($entries)
            ->filter(function (array $entry) use ($nowTimestamp): bool {
                return ! ($entry['is_checkin'] ?? false)
                    && is_int($entry['starts_at_ts'] ?? null)
                    && $entry['starts_at_ts'] >= $nowTimestamp;
            })
            ->sortBy('starts_at_ts')
            ->values();

        return $agenda;
    }

    /**
     * @return array<string, mixed>|null
     */
    #[Computed]
    public function nextAgendaItem(): ?array
    {
        $upcomingAgenda = $this->upcomingAgenda();

        /** @var array<string, mixed>|null $item */
        $item = $upcomingAgenda->first();

        return $item;
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    #[Computed]
    public function paginatedAgenda(): LengthAwarePaginator
    {
        $nextAgendaItem = $this->nextAgendaItem();
        $agendaPreview = $nextAgendaItem
            ? $this->upcomingAgenda()->slice(1)->values()
            : $this->upcomingAgenda();

        return $this->paginateCollection($agendaPreview, self::AGENDA_PER_PAGE, 'agenda_page');
    }

    /**
     * @return LengthAwarePaginator<int, Event>
     */
    #[Computed]
    public function paginatedGoingEvents(): LengthAwarePaginator
    {
        return $this->paginateCollection($this->goingEvents(), self::PLANNER_BUCKET_PER_PAGE, 'going_page');
    }

    /**
     * @return LengthAwarePaginator<int, Registration>
     */
    #[Computed]
    public function paginatedRegisteredEvents(): LengthAwarePaginator
    {
        return $this->paginateCollection($this->registeredEvents(), self::PLANNER_BUCKET_PER_PAGE, 'registered_page');
    }

    /**
     * @return LengthAwarePaginator<int, Event>
     */
    #[Computed]
    public function paginatedInterestedEvents(): LengthAwarePaginator
    {
        return $this->paginateCollection($this->interestedEvents(), self::PLANNER_BUCKET_PER_PAGE, 'interested_page');
    }

    /**
     * @return LengthAwarePaginator<int, Event>
     */
    #[Computed]
    public function paginatedSavedEvents(): LengthAwarePaginator
    {
        return $this->paginateCollection($this->savedEvents(), self::PLANNER_BUCKET_PER_PAGE, 'saved_page');
    }

    /**
     * @return LengthAwarePaginator<int, EventSubmission>
     */
    #[Computed]
    public function paginatedSubmittedEvents(): LengthAwarePaginator
    {
        return $this->paginateCollection($this->submittedEvents(), self::SUBMITTED_PER_PAGE, 'submitted_page');
    }

    /**
     * @return LengthAwarePaginator<int, EventCheckin>
     */
    #[Computed]
    public function paginatedRecentCheckins(): LengthAwarePaginator
    {
        return $this->paginateCollection($this->recentCheckins(), self::CHECKINS_PER_PAGE, 'checkins_page');
    }

    public function render(): View
    {
        return view('livewire.pages.dashboard.user-dashboard');
    }

    /**
     * @return BelongsToMany<Event, User>
     */
    protected function savedEventsQuery(User $user): BelongsToMany
    {
        return $user->savedEvents()
            ->active()
            ->orderBy('starts_at');
    }

    /**
     * @return BelongsToMany<Event, User>
     */
    protected function interestedEventsQuery(User $user): BelongsToMany
    {
        return $user->interestedEvents()
            ->active()
            ->orderBy('starts_at');
    }

    /**
     * @return BelongsToMany<Event, User>
     */
    protected function goingEventsQuery(User $user): BelongsToMany
    {
        return $user->goingEvents()
            ->active()
            ->orderBy('starts_at');
    }

    /**
     * @return array<int, string>
     */
    protected function plannerEventRelations(): array
    {
        return [
            'media',
            'institution',
            'institution.media',
            'venue',
        ];
    }

    protected function user(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }

    /**
     * @param  Collection<int, Event>  $events
     * @return Collection<int, Event>
     */
    protected function sortEventsForPlanner(Collection $events): Collection
    {
        return $events
            ->sort(fn (Event $left, Event $right): int => $this->comparePlannerDates($left->starts_at, $right->starts_at))
            ->values();
    }

    /**
     * @param  Collection<int, Registration>  $registrations
     * @return Collection<int, Registration>
     */
    protected function sortRegistrationsForPlanner(Collection $registrations): Collection
    {
        return $registrations
            ->sort(function (Registration $left, Registration $right): int {
                $leftDate = $left->event?->starts_at;
                $rightDate = $right->event?->starts_at;

                return $this->comparePlannerDates($leftDate, $rightDate);
            })
            ->values();
    }

    protected function comparePlannerDates(?CarbonInterface $left, ?CarbonInterface $right): int
    {
        [$leftPriority, $leftTimestamp] = $this->plannerDateSortTuple($left);
        [$rightPriority, $rightTimestamp] = $this->plannerDateSortTuple($right);

        if ($leftPriority !== $rightPriority) {
            return $leftPriority <=> $rightPriority;
        }

        return $leftTimestamp <=> $rightTimestamp;
    }

    /**
     * @return array{0: int, 1: int}
     */
    protected function plannerDateSortTuple(?CarbonInterface $date): array
    {
        if (! $date instanceof CarbonInterface) {
            return [2, PHP_INT_MAX];
        }

        $timezone = UserDateTimeFormatter::resolveTimezone();
        $localized = $date->copy()->timezone($timezone);

        if ($localized->isPast()) {
            return [1, -1 * $localized->getTimestamp()];
        }

        return [0, $localized->getTimestamp()];
    }

    /**
     * @param  array<string, array<string, mixed>>  $entries
     */
    protected function mergeEventIntoCalendarEntries(array &$entries, Event $event, string $role): void
    {
        $startsAt = $event->starts_at;
        $dateKey = $this->dateKey($startsAt);

        if ($dateKey === null) {
            return;
        }

        $key = 'event:'.$event->id.':'.$dateKey;

        if (! array_key_exists($key, $entries)) {
            $entries[$key] = $this->makeCalendarEntry(
                key: $key,
                event: $event,
                dateKey: $dateKey,
                startsAt: $startsAt,
                timeLabel: $this->eventTimeLabel($event),
                secondaryLabel: $this->eventLocationLabel($event),
                isCheckin: false,
            );
        }

        $this->appendRoleToCalendarEntry($entries[$key], $role);
    }

    /**
     * @param  array<string, array<string, mixed>>  $entries
     */
    protected function mergeCheckinIntoCalendarEntries(array &$entries, EventCheckin $checkin): void
    {
        $event = $checkin->event;
        $baseDate = $event instanceof Event
            ? $event->starts_at
            : $checkin->checked_in_at;
        $dateKey = $this->dateKey($baseDate);

        if ($dateKey === null) {
            return;
        }

        $key = $event instanceof Event
            ? 'event:'.$event->id.':'.$dateKey
            : 'checkin:'.$checkin->id.':'.$dateKey;

        if (! array_key_exists($key, $entries)) {
            $entries[$key] = $this->makeCalendarEntry(
                key: $key,
                event: $event,
                dateKey: $dateKey,
                startsAt: $baseDate,
                timeLabel: $this->checkinTimeLabel($checkin),
                secondaryLabel: $event instanceof Event ? $this->eventLocationLabel($event) : __('Attendance history'),
                isCheckin: true,
            );
        }

        $entries[$key]['is_checkin'] = true;
        $this->appendRoleToCalendarEntry($entries[$key], 'checkin');
    }

    /**
     * @param  array<string, mixed>  $entry
     */
    protected function appendRoleToCalendarEntry(array &$entry, string $role): void
    {
        $definitions = $this->roleDefinitions();

        if (! array_key_exists($role, $definitions)) {
            return;
        }

        /** @var list<string> $roles */
        $roles = $entry['roles'] ?? [];

        if (! in_array($role, $roles, true)) {
            $roles[] = $role;
        }

        usort($roles, fn (string $left, string $right): int => $this->rolePriority($left) <=> $this->rolePriority($right));

        $primaryRole = $roles[0] ?? 'saved';
        $status = is_string($entry['status'] ?? null) ? $entry['status'] : 'approved';

        $entry['roles'] = $roles;
        $entry['primary_role'] = $primaryRole;
        $entry['role_badges'] = array_map(
            fn (string $roleKey): array => [
                'key' => $roleKey,
                'label' => $definitions[$roleKey]['label'],
                'class' => $definitions[$roleKey]['badge_class'],
            ],
            $roles
        );
        $entry['panel_class'] = $this->entryPanelClass($status, $primaryRole, (bool) ($entry['is_checkin'] ?? false));
    }

    /**
     * @return array<string, mixed>
     */
    protected function makeCalendarEntry(
        string $key,
        ?Event $event,
        string $dateKey,
        ?CarbonInterface $startsAt,
        string $timeLabel,
        string $secondaryLabel,
        bool $isCheckin
    ): array {
        $status = $event instanceof Event ? (string) $event->status : 'approved';
        $primaryRole = $isCheckin ? 'checkin' : 'saved';
        $timestamp = $startsAt instanceof CarbonInterface
            ? $startsAt->copy()->timezone(UserDateTimeFormatter::resolveTimezone())->getTimestamp()
            : 0;

        return [
            'key' => $key,
            'id' => $event instanceof Event ? $event->id : $key,
            'title' => $event instanceof Event ? $event->title : __('Checked-in event'),
            'url' => $event instanceof Event ? route('events.show', $event) : null,
            'date_key' => $dateKey,
            'time_label' => $timeLabel,
            'secondary_label' => $secondaryLabel,
            'image_url' => $event instanceof Event ? $event->card_image_url : asset('images/placeholders/event.png'),
            'status' => $status,
            'status_label' => $this->translatedEventWorkflowStatusLabel($status),
            'status_class' => $this->eventStatusClass($status),
            'roles' => [],
            'role_badges' => [],
            'primary_role' => $primaryRole,
            'panel_class' => $this->entryPanelClass($status, $primaryRole, $isCheckin),
            'starts_at_ts' => $timestamp,
            'is_past' => $startsAt instanceof CarbonInterface
                ? $startsAt->copy()->timezone(UserDateTimeFormatter::resolveTimezone())->isPast()
                : false,
            'is_checkin' => $isCheckin,
        ];
    }

    protected function countCalendarEntriesForRole(string $role, bool $futureOnly = false): int
    {
        $timezone = UserDateTimeFormatter::resolveTimezone();
        $nowTimestamp = now($timezone)->getTimestamp();
        /** @var list<array<string, mixed>> $entries */
        $entries = $this->calendarEntries();

        return collect($entries)
            ->filter(function (array $entry) use ($role, $futureOnly, $nowTimestamp): bool {
                $roles = $entry['roles'] ?? [];

                if (! is_array($roles) || ! in_array($role, $roles, true)) {
                    return false;
                }

                if (! $futureOnly) {
                    return true;
                }

                return is_int($entry['starts_at_ts'] ?? null) && $entry['starts_at_ts'] >= $nowTimestamp;
            })
            ->count();
    }

    protected function dateKey(?CarbonInterface $date): ?string
    {
        $key = UserDateTimeFormatter::format($date, 'Y-m-d');

        return $key !== '' ? $key : null;
    }

    protected function eventTimeLabel(Event $event): string
    {
        if (! $event->starts_at instanceof CarbonInterface) {
            return __('Time to be confirmed');
        }

        return UserDateTimeFormatter::translatedFormat($event->starts_at, 'd M, h:i A');
    }

    protected function checkinTimeLabel(EventCheckin $checkin): string
    {
        if ($checkin->checked_in_at instanceof CarbonInterface) {
            return __('Checked in :time', ['time' => UserDateTimeFormatter::format($checkin->checked_in_at, 'h:i A')]);
        }

        $event = $checkin->event;

        if ($event instanceof Event && $event->starts_at instanceof CarbonInterface) {
            return UserDateTimeFormatter::translatedFormat($event->starts_at, 'd M, h:i A');
        }

        return __('Attendance recorded');
    }

    protected function eventLocationLabel(Event $event): string
    {
        $venue = $event->venue;
        $institution = $event->institution;

        return ($venue ? $venue->name : null)
            ?? ($institution ? $institution->name : null)
            ?? __('Online / TBD');
    }

    /**
     * @return array<string, array<string, string>>
     */
    protected function roleDefinitions(): array
    {
        return [
            'going' => [
                'label' => __('Going'),
                'badge_class' => 'bg-emerald-100 text-emerald-700',
                'active_button_class' => 'border-emerald-500 bg-emerald-600 text-white shadow-sm shadow-emerald-500/20',
                'inactive_button_class' => 'border-emerald-200 bg-white text-emerald-700 hover:border-emerald-300',
            ],
            'registered' => [
                'label' => __('Registered'),
                'badge_class' => 'bg-sky-100 text-sky-700',
                'active_button_class' => 'border-sky-500 bg-sky-600 text-white shadow-sm shadow-sky-500/20',
                'inactive_button_class' => 'border-sky-200 bg-white text-sky-700 hover:border-sky-300',
            ],
            'interested' => [
                'label' => __('Interested'),
                'badge_class' => 'bg-rose-100 text-rose-700',
                'active_button_class' => 'border-rose-500 bg-rose-600 text-white shadow-sm shadow-rose-500/20',
                'inactive_button_class' => 'border-rose-200 bg-white text-rose-700 hover:border-rose-300',
            ],
            'saved' => [
                'label' => __('Saved'),
                'badge_class' => 'bg-amber-100 text-amber-700',
                'active_button_class' => 'border-amber-500 bg-amber-500 text-white shadow-sm shadow-amber-500/20',
                'inactive_button_class' => 'border-amber-200 bg-white text-amber-700 hover:border-amber-300',
            ],
            'submitted' => [
                'label' => __('Submitted'),
                'badge_class' => 'bg-violet-100 text-violet-700',
                'active_button_class' => 'border-violet-500 bg-violet-600 text-white shadow-sm shadow-violet-500/20',
                'inactive_button_class' => 'border-violet-200 bg-white text-violet-700 hover:border-violet-300',
            ],
            'checkin' => [
                'label' => __('Check-ins'),
                'badge_class' => 'bg-slate-200 text-slate-700',
                'active_button_class' => 'border-slate-500 bg-slate-700 text-white shadow-sm shadow-slate-500/20',
                'inactive_button_class' => 'border-slate-200 bg-white text-slate-700 hover:border-slate-300',
            ],
        ];
    }

    protected function rolePriority(string $role): int
    {
        return match ($role) {
            'going' => 0,
            'registered' => 1,
            'interested' => 2,
            'saved' => 3,
            'submitted' => 4,
            'checkin' => 5,
            default => 99,
        };
    }

    protected function eventStatusClass(string $status): string
    {
        return match ($status) {
            'approved' => 'bg-emerald-100 text-emerald-700',
            'pending', 'needs_changes' => 'bg-amber-100 text-amber-700',
            'cancelled', 'rejected' => 'bg-rose-100 text-rose-700',
            'draft' => 'bg-slate-200 text-slate-700',
            default => 'bg-slate-200 text-slate-700',
        };
    }

    protected function entryPanelClass(string $status, string $primaryRole, bool $isCheckin): string
    {
        if ($isCheckin) {
            return 'border-slate-200 bg-slate-50 text-slate-900';
        }

        if (in_array($status, ['cancelled', 'rejected'], true)) {
            return 'border-rose-200 bg-rose-50 text-rose-900';
        }

        if (in_array($status, ['pending', 'needs_changes'], true)) {
            return 'border-amber-200 bg-amber-50 text-amber-900';
        }

        return match ($primaryRole) {
            'going' => 'border-emerald-200 bg-emerald-50/80 text-emerald-950',
            'registered' => 'border-sky-200 bg-sky-50/80 text-sky-950',
            'interested' => 'border-rose-200 bg-rose-50/80 text-rose-950',
            'saved' => 'border-amber-200 bg-amber-50/80 text-amber-950',
            'submitted' => 'border-violet-200 bg-violet-50/80 text-violet-950',
            default => 'border-slate-200 bg-slate-50 text-slate-900',
        };
    }

    protected function translatedStatusLabel(string $status): string
    {
        $translated = __($status);

        if ($translated !== $status) {
            return $translated;
        }

        return Str::of($status)->replace('_', ' ')->headline()->toString();
    }

    protected function translatedEventWorkflowStatusLabel(string $status): string
    {
        return match ($status) {
            'pending' => __('Menunggu Kelulusan'),
            default => $this->translatedStatusLabel($status),
        };
    }

    /**
     * @template TItem
     *
     * @param  Collection<int, TItem>  $items
     * @return LengthAwarePaginator<int, TItem>
     */
    protected function paginateCollection(Collection $items, int $perPage, string $pageName): LengthAwarePaginator
    {
        $currentPage = $this->getPage($pageName);

        /** @var Collection<int, TItem> $pageItems */
        $pageItems = $items
            ->forPage($currentPage, $perPage)
            ->values();

        $paginator = new Paginator(
            $pageItems,
            $items->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'pageName' => $pageName,
            ]
        );

        return $paginator->appends(collect(request()->query())->except($pageName)->all());
    }
}
