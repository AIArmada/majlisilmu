<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCadence;
use App\Enums\NotificationPriority;
use App\Enums\NotificationTrigger;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\Registration;
use App\Models\SavedSearch;
use App\Models\User;
use App\Services\EventSearchService;
use App\Support\Authz\MemberPermissionGate;
use App\Support\Notifications\NotificationDispatchData;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class EventNotificationService
{
    public function __construct(
        protected NotificationEngine $engine,
        protected EventSearchService $eventSearchService,
        protected MemberPermissionGate $memberPermissionGate,
    ) {}

    public function notifySubmissionReceived(Event $event): void
    {
        $recipients = $this->submissionRecipients($event);

        if ($recipients->isEmpty()) {
            return;
        }

        $this->engine->dispatch($recipients, new NotificationDispatchData(
            trigger: NotificationTrigger::SubmissionReceived,
            title: __('notifications.messages.submission_received.title', ['title' => $event->title]),
            body: __('notifications.messages.submission_received.body'),
            actionUrl: route('dashboard.notifications'),
            entityType: Event::class,
            entityId: $event->id,
            priority: NotificationPriority::Medium,
            fingerprint: 'submission-received:'.$event->id,
            meta: [
                'event_title' => $event->title,
                'event_slug' => $event->slug,
            ],
        ));
    }

    public function notifySubmissionApproved(Event $event): void
    {
        $recipients = $this->submissionRecipients($event);

        if ($recipients->isEmpty()) {
            return;
        }

        $this->engine->dispatch($recipients, new NotificationDispatchData(
            trigger: NotificationTrigger::SubmissionApproved,
            title: __('notifications.messages.submission_approved.title', ['title' => $event->title]),
            body: __('notifications.messages.submission_approved.body', [
                'timing' => $this->formatEventTiming($event, $recipients->first()),
            ]),
            actionUrl: route('events.show', $event),
            entityType: Event::class,
            entityId: $event->id,
            priority: NotificationPriority::High,
            fingerprint: 'submission-approved:'.$event->id,
        ));
    }

    public function notifySubmissionRejected(Event $event, ?string $note = null): void
    {
        $recipients = $this->submissionRecipients($event);

        if ($recipients->isEmpty()) {
            return;
        }

        $this->engine->dispatch($recipients, new NotificationDispatchData(
            trigger: NotificationTrigger::SubmissionRejected,
            title: __('notifications.messages.submission_rejected.title', ['title' => $event->title]),
            body: filled($note)
                ? __('notifications.messages.submission_rejected.body_with_note', ['note' => $note])
                : __('notifications.messages.submission_rejected.body'),
            actionUrl: route('dashboard.notifications'),
            entityType: Event::class,
            entityId: $event->id,
            priority: NotificationPriority::High,
            fingerprint: 'submission-rejected:'.$event->id.':'.sha1((string) $note),
            meta: ['note' => $note],
        ));
    }

    public function notifySubmissionNeedsChanges(Event $event, ?string $note = null): void
    {
        $recipients = $this->submissionRecipients($event);

        if ($recipients->isEmpty()) {
            return;
        }

        $this->engine->dispatch($recipients, new NotificationDispatchData(
            trigger: NotificationTrigger::SubmissionNeedsChanges,
            title: __('notifications.messages.submission_needs_changes.title', ['title' => $event->title]),
            body: filled($note)
                ? __('notifications.messages.submission_needs_changes.body_with_note', ['note' => $note])
                : __('notifications.messages.submission_needs_changes.body'),
            actionUrl: route('dashboard.notifications'),
            entityType: Event::class,
            entityId: $event->id,
            priority: NotificationPriority::High,
            fingerprint: 'submission-needs-changes:'.$event->id.':'.sha1((string) $note),
            meta: ['note' => $note],
        ));
    }

    public function notifySubmissionCancelled(Event $event, ?string $note = null): void
    {
        $recipients = $this->submissionRecipients($event);

        if ($recipients->isEmpty()) {
            return;
        }

        $this->engine->dispatch($recipients, new NotificationDispatchData(
            trigger: NotificationTrigger::SubmissionCancelled,
            title: __('notifications.messages.submission_cancelled.title', ['title' => $event->title]),
            body: filled($note)
                ? __('notifications.messages.submission_cancelled.body_with_note', ['note' => $note])
                : __('notifications.messages.submission_cancelled.body'),
            actionUrl: route('dashboard.notifications'),
            entityType: Event::class,
            entityId: $event->id,
            priority: NotificationPriority::Urgent,
            fingerprint: 'submission-cancelled:'.$event->id.':'.sha1((string) $note),
            meta: ['note' => $note],
        ));
    }

    public function notifySubmissionRemoderated(Event $event, ?string $note = null): void
    {
        $recipients = $this->submissionRecipients($event);

        if ($recipients->isEmpty()) {
            return;
        }

        $this->engine->dispatch($recipients, new NotificationDispatchData(
            trigger: NotificationTrigger::SubmissionRemoderated,
            title: __('notifications.messages.submission_remoderated.title', ['title' => $event->title]),
            body: filled($note)
                ? __('notifications.messages.submission_remoderated.body_with_note', ['note' => $note])
                : __('notifications.messages.submission_remoderated.body'),
            actionUrl: route('dashboard.notifications'),
            entityType: Event::class,
            entityId: $event->id,
            priority: NotificationPriority::Medium,
            fingerprint: 'submission-remoderated:'.$event->id.':'.sha1((string) $note),
            meta: ['note' => $note],
        ));
    }

    public function notifyPublication(Event $event): void
    {
        if (! $this->isPublicFutureEvent($event)) {
            return;
        }

        $this->notifyFollowedContentPublication($event);
        $this->notifySavedSearchMatches($event);
        $this->notifyTrackedEventApproved($event);
    }

    public function notifyTrackedEventCancelled(Event $event, ?string $note = null): void
    {
        $trackedUsers = $this->trackedUsers($event);

        if ($trackedUsers->isEmpty()) {
            return;
        }

        $this->engine->dispatch($trackedUsers, new NotificationDispatchData(
            trigger: NotificationTrigger::EventCancelled,
            title: __('notifications.messages.event_cancelled.title', ['title' => $event->title]),
            body: filled($note)
                ? __('notifications.messages.event_cancelled.body_with_note', ['timing' => $this->formatEventTiming($event, $trackedUsers->first()), 'note' => $note])
                : __('notifications.messages.event_cancelled.body', ['timing' => $this->formatEventTiming($event, $trackedUsers->first())]),
            actionUrl: route('events.show', $event),
            entityType: Event::class,
            entityId: $event->id,
            priority: NotificationPriority::Urgent,
            fingerprint: 'event-cancelled:'.$event->id.':'.sha1((string) $event->updated_at?->toIso8601String()),
            meta: ['note' => $note],
            bypassQuietHours: $this->startsWithinHours($event, 24),
        ));
    }

    /**
     * @param  array<int, string>  $changedFields
     */
    public function notifyMaterialEventChange(Event $event, array $changedFields): void
    {
        if (! $this->isPublicFutureEvent($event)) {
            return;
        }

        $trigger = $this->materialChangeTrigger($changedFields);

        if (! $trigger instanceof NotificationTrigger) {
            return;
        }

        $trackedUsers = $this->trackedUsers($event);
        $registeredUsers = $this->registeredUsers($event);
        $trackedExcludingRegistered = $trackedUsers
            ->reject(fn (User $user): bool => $registeredUsers->contains('id', $user->id))
            ->values();

        if ($trackedExcludingRegistered->isNotEmpty()) {
            $this->engine->dispatch($trackedExcludingRegistered, new NotificationDispatchData(
                trigger: $trigger,
                title: __('notifications.messages.event_update.title', ['title' => $event->title]),
                body: $this->materialChangeBody($event, $trigger, $trackedExcludingRegistered->first()),
                actionUrl: route('events.show', $event),
                entityType: Event::class,
                entityId: $event->id,
                priority: $this->materialChangePriority($trigger),
                fingerprint: 'event-update:'.$trigger->value.':'.$event->id.':'.sha1(implode('|', $changedFields).':'.$event->updated_at?->toIso8601String()),
                bypassQuietHours: $this->startsWithinHours($event, 24),
            ));
        }

        if ($registeredUsers->isNotEmpty()) {
            $this->engine->dispatch($registeredUsers, new NotificationDispatchData(
                trigger: NotificationTrigger::RegistrationEventChanged,
                title: __('notifications.messages.registration_event_changed.title', ['title' => $event->title]),
                body: __('notifications.messages.registration_event_changed.body', [
                    'timing' => $this->formatEventTiming($event, $registeredUsers->first()),
                ]),
                actionUrl: route('events.show', $event),
                entityType: Event::class,
                entityId: $event->id,
                priority: NotificationPriority::High,
                fingerprint: 'registration-event-changed:'.$event->id.':'.sha1(implode('|', $changedFields).':'.$event->updated_at?->toIso8601String()),
                bypassQuietHours: $this->startsWithinHours($event, 24),
            ));
        }
    }

    public function notifySessionChange(Event $event): void
    {
        $this->notifyMaterialEventChange($event, ['event_session']);
    }

    public function notifyRegistrationConfirmed(Registration $registration): void
    {
        $user = $registration->user;
        $event = $registration->event;

        if (! $user instanceof User || ! $event instanceof Event) {
            return;
        }

        $this->engine->dispatch([$user], new NotificationDispatchData(
            trigger: NotificationTrigger::RegistrationConfirmed,
            title: __('notifications.messages.registration_confirmed.title', ['title' => $event->title]),
            body: __('notifications.messages.registration_confirmed.body', [
                'timing' => $this->formatEventTiming($event, $user),
            ]),
            actionUrl: route('events.show', $event),
            entityType: Event::class,
            entityId: $event->id,
            priority: NotificationPriority::Medium,
            fingerprint: 'registration-confirmed:'.$registration->id,
        ));
    }

    public function notifyCheckinConfirmed(EventCheckin $checkin): void
    {
        $user = $checkin->user;
        $event = $checkin->event;

        if (! $user instanceof User || ! $event instanceof Event) {
            return;
        }

        $this->engine->dispatch([$user], new NotificationDispatchData(
            trigger: NotificationTrigger::CheckinConfirmed,
            title: __('notifications.messages.checkin_confirmed.title', ['title' => $event->title]),
            body: __('notifications.messages.checkin_confirmed.body'),
            actionUrl: route('events.show', $event),
            entityType: Event::class,
            entityId: $event->id,
            priority: NotificationPriority::Medium,
            fingerprint: 'checkin-confirmed:'.$checkin->id,
        ));
    }

    public function dispatchDueReminderNotifications(?CarbonImmutable $now = null): void
    {
        $now ??= CarbonImmutable::now('UTC');

        $this->dispatchReminderWindow($now, NotificationTrigger::Reminder24Hours, $now->addHours(24), $now->addHours(24)->addMinutes(15));
        $this->dispatchReminderWindow($now, NotificationTrigger::Reminder2Hours, $now->addHours(2), $now->addHours(2)->addMinutes(15));
        $this->dispatchReminderWindow($now, NotificationTrigger::CheckinOpen, $now->addHours(2), $now->addHours(2)->addMinutes(15), checkinOpen: true);
    }

    /**
     * @return Collection<int, User>
     */
    protected function submissionRecipients(Event $event): Collection
    {
        $recipients = collect();

        if ($event->submitter_id) {
            $submitter = User::query()->find($event->submitter_id);

            if ($submitter instanceof User) {
                $recipients->push($submitter);
            }
        }

        if ($event->institution) {
            $institutionAdmins = app(MemberPermissionGate::class)
                ->institutionMembersWithPermission($event->institution, 'event.update');
            $recipients = $recipients->merge($institutionAdmins);
        }

        return $recipients
            ->filter(fn (mixed $user): bool => $user instanceof User)
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    protected function trackedUsers(Event $event): Collection
    {
        $registrations = Registration::query()
            ->where('event_id', $event->id)
            ->where('status', '!=', 'cancelled')
            ->with('user')
            ->get()
            ->pluck('user');

        return collect()
            ->merge($event->savedBy()->get())
            ->merge($event->interestedBy()->get())
            ->merge($event->goingBy()->get())
            ->merge($registrations)
            ->filter(fn (mixed $user): bool => $user instanceof User)
            ->unique('id')
            ->values();
    }

    /**
     * @return Collection<int, User>
     */
    protected function registeredUsers(Event $event): Collection
    {
        return Registration::query()
            ->where('event_id', $event->id)
            ->where('status', '!=', 'cancelled')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter(fn (mixed $user): bool => $user instanceof User)
            ->unique('id')
            ->values();
    }

    protected function notifyTrackedEventApproved(Event $event): void
    {
        $trackedUsers = $this->trackedUsers($event);

        if ($trackedUsers->isEmpty()) {
            return;
        }

        $this->engine->dispatch($trackedUsers, new NotificationDispatchData(
            trigger: NotificationTrigger::EventApproved,
            title: __('notifications.messages.event_approved.title', ['title' => $event->title]),
            body: __('notifications.messages.event_approved.body', [
                'timing' => $this->formatEventTiming($event, $trackedUsers->first()),
            ]),
            actionUrl: route('events.show', $event),
            entityType: Event::class,
            entityId: $event->id,
            priority: NotificationPriority::Medium,
            fingerprint: 'event-approved:'.$event->id.':'.sha1((string) $event->updated_at?->toIso8601String()),
        ));
    }

    protected function notifyFollowedContentPublication(Event $event): void
    {
        $this->dispatchFollowedEntityNotifications(
            trigger: NotificationTrigger::FollowedSpeakerEvent,
            event: $event,
            followables: $event->speakers()->with('followers')->get(),
            labelResolver: static fn (mixed $speaker): ?string => $speaker?->name,
        );

        if ($event->institution !== null) {
            $event->loadMissing('institution.followers');
            $this->dispatchFollowedEntityNotifications(
                trigger: NotificationTrigger::FollowedInstitutionEvent,
                event: $event,
                followables: collect([$event->institution]),
                labelResolver: static fn (mixed $institution): ?string => $institution?->name,
            );
        }

        $this->dispatchFollowedEntityNotifications(
            trigger: NotificationTrigger::FollowedSeriesEvent,
            event: $event,
            followables: $event->series()->with('followers')->get(),
            labelResolver: static fn (mixed $series): ?string => $series?->title,
        );

        $this->dispatchFollowedEntityNotifications(
            trigger: NotificationTrigger::FollowedReferenceEvent,
            event: $event,
            followables: $event->references()->with('followers')->get(),
            labelResolver: static fn (mixed $reference): ?string => $reference?->title,
        );
    }

    protected function notifySavedSearchMatches(Event $event): void
    {
        $matchesByUser = [];

        SavedSearch::query()
            ->where('notify', '!=', NotificationCadence::Off->value)
            ->with('user')
            ->cursor()
            ->each(function (SavedSearch $savedSearch) use ($event, &$matchesByUser): void {
                $user = $savedSearch->user;

                if (! $user instanceof User) {
                    return;
                }

                if (! $this->savedSearchMatchesEvent($savedSearch, $event)) {
                    return;
                }

                $matchesByUser[$user->id] ??= [
                    'user' => $user,
                    'searches' => [],
                ];
                $matchesByUser[$user->id]['searches'][] = $savedSearch;
            });

        foreach ($matchesByUser as $matchData) {
            /** @var User $user */
            $user = $matchData['user'];
            /** @var array<int, SavedSearch> $searches */
            $searches = $matchData['searches'];
            $searchNames = collect($searches)->pluck('name')->values()->all();

            $this->engine->dispatch([$user], new NotificationDispatchData(
                trigger: NotificationTrigger::SavedSearchMatch,
                title: __('notifications.messages.saved_search_match.title', ['title' => $event->title]),
                body: __('notifications.messages.saved_search_match.body', [
                    'searches' => implode(', ', array_slice($searchNames, 0, 3)),
                ]),
                actionUrl: route('events.show', $event),
                entityType: Event::class,
                entityId: $event->id,
                priority: NotificationPriority::Low,
                forcedCadence: $this->fastestSavedSearchCadence($searches),
                fingerprint: 'saved-search-match:'.$event->id.':'.$user->id,
                meta: [
                    'saved_search_ids' => collect($searches)->pluck('id')->all(),
                    'saved_search_names' => $searchNames,
                ],
            ));
        }
    }

    /**
     * @param  Collection<int, mixed>  $followables
     * @param  callable(mixed): ?string  $labelResolver
     */
    protected function dispatchFollowedEntityNotifications(
        NotificationTrigger $trigger,
        Event $event,
        Collection $followables,
        callable $labelResolver,
    ): void {
        $users = [];

        foreach ($followables as $followable) {
            $labels = [];

            if (! method_exists($followable, 'followers')) {
                continue;
            }

            /** @var Collection<int, User> $followers */
            $followers = $followable->followers()->get();

            foreach ($followers as $follower) {
                $users[$follower->id]['user'] = $follower;
                $label = $labelResolver($followable);

                if (is_string($label) && $label !== '') {
                    $users[$follower->id]['labels'][$label] = $label;
                }
            }
        }

        foreach ($users as $match) {
            /** @var User $user */
            $user = $match['user'];
            $labels = array_values($match['labels'] ?? []);

            $this->engine->dispatch([$user], new NotificationDispatchData(
                trigger: $trigger,
                title: __('notifications.messages.followed_content.title', ['title' => $event->title]),
                body: __('notifications.messages.followed_content.body', [
                    'matches' => implode(', ', array_slice($labels, 0, 3)),
                    'timing' => $this->formatEventTiming($event, $user),
                ]),
                actionUrl: route('events.show', $event),
                entityType: Event::class,
                entityId: $event->id,
                priority: NotificationPriority::Low,
                fingerprint: 'followed-content:'.$trigger->value.':'.$event->id.':'.$user->id,
                meta: ['matched_entities' => $labels],
            ));
        }
    }

    protected function savedSearchMatchesEvent(SavedSearch $savedSearch, Event $event): bool
    {
        $filters = is_array($savedSearch->filters) ? $savedSearch->filters : [];

        if ($savedSearch->lat !== null && $savedSearch->lng !== null && $savedSearch->radius_km !== null) {
            $results = $this->eventSearchService->searchNearby(
                lat: $savedSearch->lat,
                lng: $savedSearch->lng,
                radiusKm: $savedSearch->radius_km,
                filters: $filters,
                perPage: 50,
            );

            return collect($results->items())->contains(fn (Event $candidate): bool => $candidate->id === $event->id);
        }

        $results = $this->eventSearchService->search(
            query: $savedSearch->query,
            filters: $filters,
            perPage: 50,
        );

        return collect($results->items())->contains(fn (Event $candidate): bool => $candidate->id === $event->id);
    }

    /**
     * @param  array<int, SavedSearch>  $savedSearches
     */
    protected function fastestSavedSearchCadence(array $savedSearches): NotificationCadence
    {
        $cadences = collect($savedSearches)
            ->map(fn (SavedSearch $savedSearch): NotificationCadence => NotificationCadence::tryFrom((string) $savedSearch->notify) ?? NotificationCadence::Daily)
            ->values();

        if ($cadences->contains(NotificationCadence::Instant)) {
            return NotificationCadence::Instant;
        }

        if ($cadences->contains(NotificationCadence::Daily)) {
            return NotificationCadence::Daily;
        }

        if ($cadences->contains(NotificationCadence::Weekly)) {
            return NotificationCadence::Weekly;
        }

        return NotificationCadence::Off;
    }

    /**
     * @param  array<int, string>  $changedFields
     */
    protected function materialChangeTrigger(array $changedFields): ?NotificationTrigger
    {
        if (in_array('venue_id', $changedFields, true) || in_array('space_id', $changedFields, true)) {
            return NotificationTrigger::EventVenueChanged;
        }

        if (in_array('event_session', $changedFields, true)) {
            return NotificationTrigger::EventSessionChanged;
        }

        if (array_intersect($changedFields, ['starts_at', 'ends_at', 'timezone', 'title'])) {
            return NotificationTrigger::EventScheduleChanged;
        }

        return null;
    }

    protected function materialChangeBody(Event $event, NotificationTrigger $trigger, User $user): string
    {
        return match ($trigger) {
            NotificationTrigger::EventVenueChanged => __('notifications.messages.event_venue_changed.body', [
                'timing' => $this->formatEventTiming($event, $user),
            ]),
            NotificationTrigger::EventSessionChanged => __('notifications.messages.event_session_changed.body', [
                'timing' => $this->formatEventTiming($event, $user),
            ]),
            default => __('notifications.messages.event_schedule_changed.body', [
                'timing' => $this->formatEventTiming($event, $user),
            ]),
        };
    }

    protected function materialChangePriority(NotificationTrigger $trigger): NotificationPriority
    {
        return $trigger === NotificationTrigger::EventVenueChanged
            ? NotificationPriority::High
            : NotificationPriority::High;
    }

    protected function dispatchReminderWindow(
        CarbonImmutable $now,
        NotificationTrigger $trigger,
        CarbonImmutable $windowStart,
        CarbonImmutable $windowEnd,
        bool $checkinOpen = false,
    ): void {
        $events = Event::query()
            ->where('is_active', true)
            ->whereIn('status', Event::ENGAGEABLE_STATUSES)
            ->where('visibility', \App\Enums\EventVisibility::Public)
            ->whereBetween('starts_at', [
                $windowStart->utc()->toDateTimeString(),
                $windowEnd->utc()->toDateTimeString(),
            ])
            ->get();

        foreach ($events as $event) {
            $users = $this->trackedReminderUsers($event, $checkinOpen);

            if ($users->isEmpty()) {
                continue;
            }

            $this->engine->dispatch($users, new NotificationDispatchData(
                trigger: $trigger,
                title: match ($trigger) {
                    NotificationTrigger::Reminder24Hours => __('notifications.messages.reminder_24_hours.title', ['title' => $event->title]),
                    NotificationTrigger::Reminder2Hours => __('notifications.messages.reminder_2_hours.title', ['title' => $event->title]),
                    default => __('notifications.messages.checkin_open.title', ['title' => $event->title]),
                },
                body: match ($trigger) {
                    NotificationTrigger::Reminder24Hours => __('notifications.messages.reminder_24_hours.body', ['timing' => $this->formatEventTiming($event, $users->first())]),
                    NotificationTrigger::Reminder2Hours => __('notifications.messages.reminder_2_hours.body', ['timing' => $this->formatEventTiming($event, $users->first())]),
                    default => __('notifications.messages.checkin_open.body', ['timing' => $this->formatEventTiming($event, $users->first())]),
                },
                actionUrl: route('events.show', $event),
                entityType: Event::class,
                entityId: $event->id,
                priority: $trigger === NotificationTrigger::Reminder24Hours ? NotificationPriority::Medium : NotificationPriority::Urgent,
                fingerprint: 'reminder:'.$trigger->value.':'.$event->id.':'.$event->starts_at?->timestamp,
                bypassQuietHours: $trigger !== NotificationTrigger::Reminder24Hours,
            ));
        }
    }

    /**
     * @return Collection<int, User>
     */
    protected function trackedReminderUsers(Event $event, bool $checkinOpen = false): Collection
    {
        $users = collect()
            ->merge($event->goingBy()->get())
            ->merge($this->registeredUsers($event))
            ->filter(fn (mixed $user): bool => $user instanceof User)
            ->unique('id')
            ->values();

        if (! $checkinOpen) {
            return $users;
        }

        return $users;
    }

    protected function isPublicFutureEvent(Event $event): bool
    {
        return $event->is_active
            && (string) $event->status === 'approved'
            && $event->visibility === \App\Enums\EventVisibility::Public
            && $event->starts_at !== null
            && $event->starts_at->isFuture();
    }

    protected function formatEventTiming(Event $event, ?User $user): string
    {
        $startsAt = $event->starts_at;

        if ($startsAt === null) {
            return __('notifications.messages.timing.to_be_confirmed');
        }

        $timezone = $user?->timezone ?: ($event->timezone ?: config('app.timezone'));
        $locale = app()->getLocale();
        $localizedStart = CarbonImmutable::instance($startsAt)->setTimezone($timezone)->locale($locale);

        return $localizedStart->translatedFormat('D, j M Y').' • '.$localizedStart->format('h:i A');
    }

    protected function startsWithinHours(Event $event, int $hours): bool
    {
        return $event->starts_at !== null
            && $event->starts_at->isBetween(now(), now()->addHours($hours));
    }
}
