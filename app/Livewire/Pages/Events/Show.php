<?php

namespace App\Livewire\Pages\Events;

use App\Enums\EventVisibility;
use App\Enums\RegistrationMode;
use App\Enums\SessionStatus;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventSession;
use App\Models\Registration;
use App\Models\User;
use App\Services\CalendarService;
use App\Services\DawahShare\DawahShareService;
use App\States\EventStatus\Approved;
use App\States\EventStatus\Cancelled;
use App\States\EventStatus\EventStatus;
use App\States\EventStatus\Pending;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

#[Layout('layouts.app')]
#[Title('Event Details')]
class Show extends Component
{
    public Event $event;

    public bool $isSaved = false;

    public bool $isInterested = false;

    public bool $isGoing = false;

    public bool $isCheckedIn = false;

    public int $interestsCount = 0;

    public int $goingCount = 0;

    public function mount(Event $event): void
    {
        $isViewable = $event->is_active && $this->isPubliclyVisibleStatus($event);
        $isOwner = $this->isEventOwner($event);

        // Owners can always view their own events (drafts, pending, approved, etc)
        if ($isOwner) {
            // Allow access
        }
        // Public events: anyone can view if active and approved/pending
        elseif ($isViewable && $event->visibility === EventVisibility::Public) {
            // Allow access
        }
        // Unlisted events: anyone with link can view if active and approved/pending
        elseif ($isViewable && $event->visibility === EventVisibility::Unlisted) {
            // Allow access
        }
        // All other cases: 404
        else {
            abort(404);
        }

        $event->load([
            'media',
            'organizer',
            'institution.media',
            'institution.address.state',
            'institution.address.city',
            'institution.address.district',
            'institution.address.subdistrict',
            'institution.contacts',
            'venue.media',
            'venue.address.state',
            'venue.address.city',
            'venue.address.district',
            'venue.address.subdistrict',
            'speakers.media',
            'tags',
            'donationChannel.media',
            'settings',
            'sessions',
            'series',
            'references.media',
            'languages',
        ]);

        $event->loadMorph('organizer', [
            \App\Models\Institution::class => ['media', 'contacts'],
            \App\Models\Speaker::class => ['media'],
            \App\Models\Venue::class => ['media'],
        ]);

        $this->event = $event;
        $this->syncEngagementStates();
    }

    /**
     * @return array<string, string>
     */
    #[Computed]
    public function calendarLinks(): array
    {
        return app(CalendarService::class)->getAllCalendarLinks($this->event);
    }

    /**
     * @return array<int, array{url: string, thumb: string, alt: string}>
     */
    #[Computed]
    public function galleryImages(): array
    {
        $images = [];
        $imageCounter = 1;

        foreach ($this->event->getMedia('gallery') as $galleryMedia) {
            $images[] = $this->buildGalleryImagePayload(
                $galleryMedia,
                __('Photo :number', ['number' => $imageCounter++])
            );
        }

        return $images;
    }

    /**
     * @return Collection<int, EventSession>
     */
    #[Computed]
    public function upcomingSessions(): Collection
    {
        $now = now($this->event->timezone ?: 'Asia/Kuala_Lumpur');

        return $this->event->sessions
            ->filter(function (EventSession $session) use ($now): bool {
                $startsAt = $session->starts_at;

                if (is_string($startsAt) && $startsAt !== '') {
                    try {
                        $startsAt = Carbon::parse($startsAt, $this->event->timezone ?: 'Asia/Kuala_Lumpur');
                    } catch (Throwable) {
                        return false;
                    }
                }

                return $session->status === SessionStatus::Scheduled
                    && $startsAt instanceof CarbonInterface
                    && $startsAt->greaterThanOrEqualTo($now);
            })
            ->sortBy('starts_at')
            ->values();
    }

    public function registrationMode(): RegistrationMode
    {
        $mode = $this->event->settings?->registration_mode;

        if ($mode instanceof RegistrationMode) {
            return $mode;
        }

        return RegistrationMode::Event;
    }

    /**
     * Determine the event's temporal status for display purposes.
     */
    #[Computed]
    public function eventTimeStatus(): string
    {
        $now = now($this->event->timezone ?: 'Asia/Kuala_Lumpur');
        $startsAt = $this->event->starts_at;
        $endsAt = $this->event->ends_at;

        if (! $startsAt instanceof CarbonInterface) {
            return 'upcoming';
        }

        if ($endsAt instanceof CarbonInterface && $now->greaterThan($endsAt)) {
            return 'past';
        }

        if ($startsAt->isPast() && (! $endsAt instanceof CarbonInterface || $now->lessThanOrEqualTo($endsAt))) {
            return 'happening_now';
        }

        if ($startsAt->isFuture() && $startsAt->diffInHours($now) <= 24) {
            return 'starting_soon';
        }

        return 'upcoming';
    }

    /**
     * Render the event description as safe HTML.
     */
    #[Computed]
    public function descriptionHtml(): string
    {
        $description = $this->event->description;

        if (is_array($description)) {
            $html = $description['html'] ?? null;

            if (is_string($html) && $html !== '') {
                return $html;
            }
        }

        $text = $this->event->description_text;

        return $text !== '' ? nl2br(e($text)) : '';
    }

    /**
     * @return array{whatsapp: string, telegram: string, line: string, facebook: string, x: string, instagram: string, tiktok: string, email: string}
     */
    #[Computed]
    public function shareLinks(): array
    {
        /** @var array<string, string> $platformLinks */
        $platformLinks = app(DawahShareService::class)->redirectLinks(
            route('events.show', $this->event),
            trim($this->event->title.' - '.config('app.name')),
            $this->event->title,
        );

        return $platformLinks;
    }

    public function toggleSave(): void
    {
        $this->toggleEngagement('savedEvents', 'isSaved', 'saves_count');
    }

    public function toggleInterest(): void
    {
        $this->toggleEngagement('interestedEvents', 'isInterested', 'interests_count', 'interestsCount');
    }

    public function toggleGoing(): void
    {
        $this->toggleEngagement('goingEvents', 'isGoing', 'going_count', 'goingCount');
    }

    public function checkIn(): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            $this->redirectRoute('login');

            return;
        }

        $state = $this->resolveCheckInState($user);

        if (! $state['available']) {
            if (filled($state['reason'])) {
                FilamentNotification::make()
                    ->title((string) $state['reason'])
                    ->warning()
                    ->send();
            }

            return;
        }

        $alreadyCheckedIn = EventCheckin::query()
            ->where('event_id', $this->event->id)
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyCheckedIn) {
            $this->isCheckedIn = true;

            FilamentNotification::make()
                ->title(__('Anda sudah check-in untuk majlis ini.'))
                ->success()
                ->send();

            return;
        }

        $checkin = EventCheckin::query()->create([
            'event_id' => $this->event->id,
            'event_session_id' => $state['event_session_id'],
            'registration_id' => $state['registration_id'],
            'user_id' => $user->id,
            'method' => $state['method'],
            'checked_in_at' => now(),
        ]);

        app(DawahShareService::class)->recordOutcome(
            type: \App\Enums\DawahShareOutcomeType::EventCheckin,
            outcomeKey: 'event_checkin:checkin:'.$checkin->id,
            subject: $this->event,
            actor: $user,
            request: request(),
            metadata: [
                'checkin_id' => $checkin->id,
                'event_session_id' => $checkin->event_session_id,
                'registration_id' => $checkin->registration_id,
                'method' => $checkin->method,
            ],
        );

        app(\App\Services\Notifications\EventNotificationService::class)
            ->notifyCheckinConfirmed($checkin);

        $this->isCheckedIn = true;
        unset($this->checkInState);

        FilamentNotification::make()
            ->title(__('Check-in berjaya direkodkan.'))
            ->success()
            ->send();
    }

    /**
     * @return array{available: bool, reason: string|null}
     */
    #[Computed]
    public function checkInState(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [
                'available' => false,
                'reason' => __('Log masuk untuk check-in.'),
            ];
        }

        $state = $this->resolveCheckInState($user);

        return [
            'available' => $state['available'],
            'reason' => $state['reason'],
        ];
    }

    protected function toggleEngagement(string $relation, string $stateProperty, string $countColumn, ?string $countProperty = null): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            $this->redirectRoute('login');

            return;
        }

        if (! $this->isEngagementStatus($this->event) || $this->event->visibility !== EventVisibility::Public) {
            abort(403);
        }

        if ($this->{$stateProperty}) {
            $user->{$relation}()->detach($this->event->id);
            $this->event->decrement($countColumn);

            if ($countProperty) {
                $this->{$countProperty} = max(0, $this->{$countProperty} - 1);
            }

            $this->{$stateProperty} = false;
        } else {
            $user->{$relation}()->syncWithoutDetaching([$this->event->id]);
            $this->event->increment($countColumn);

            if ($countProperty) {
                $this->{$countProperty}++;
            }

            $this->{$stateProperty} = true;
            $this->recordEngagementOutcome($relation, $user);
        }
    }

    protected function recordEngagementOutcome(string $relation, User $user): void
    {
        $type = match ($relation) {
            'savedEvents' => \App\Enums\DawahShareOutcomeType::EventSave,
            'interestedEvents' => \App\Enums\DawahShareOutcomeType::EventInterest,
            'goingEvents' => \App\Enums\DawahShareOutcomeType::EventGoing,
            default => null,
        };

        if (! $type instanceof \App\Enums\DawahShareOutcomeType) {
            return;
        }

        app(DawahShareService::class)->recordOutcome(
            type: $type,
            outcomeKey: $type->value.':user:'.$user->id.':event:'.$this->event->id,
            subject: $this->event,
            actor: $user,
            request: request(),
            metadata: [
                'event_id' => $this->event->id,
            ],
        );
    }

    protected function syncEngagementStates(): void
    {
        $this->interestsCount = max(0, (int) ($this->event->interests_count ?? 0));
        $this->goingCount = max(0, (int) ($this->event->going_count ?? 0));

        $user = auth()->user();

        if (! $user instanceof User) {
            $this->isSaved = false;
            $this->isInterested = false;
            $this->isGoing = false;
            $this->isCheckedIn = false;

            return;
        }

        $this->isSaved = $user->savedEvents()->where('event_id', $this->event->id)->exists();
        $this->isInterested = $user->interestedEvents()->where('event_id', $this->event->id)->exists();
        $this->isGoing = $user->goingEvents()->where('event_id', $this->event->id)->exists();
        $this->isCheckedIn = EventCheckin::query()
            ->where('event_id', $this->event->id)
            ->where('user_id', $user->id)
            ->exists();
    }

    /**
     * @return array{url: string, thumb: string, alt: string}
     */
    protected function buildGalleryImagePayload(Media $media, string $fallbackAlt): array
    {
        $fullImageUrl = $media->getAvailableUrl(['preview', 'thumb']);
        $thumbnailUrl = $media->getAvailableUrl(['thumb']);

        return [
            'url' => $fullImageUrl !== '' ? $fullImageUrl : $media->getUrl(),
            'thumb' => $thumbnailUrl !== '' ? $thumbnailUrl : ($fullImageUrl !== '' ? $fullImageUrl : $media->getUrl()),
            'alt' => filled($media->name) ? (string) $media->name : $fallbackAlt,
        ];
    }

    public function render(): View
    {
        return view('livewire.pages.events.show');
    }

    protected function isPubliclyVisibleStatus(Event $event): bool
    {
        $status = $event->status;

        if ($status instanceof EventStatus) {
            return $status->equals(Approved::class)
                || $status->equals(Pending::class)
                || $status->equals(Cancelled::class);
        }

        return in_array((string) $status, Event::PUBLIC_STATUSES, true);
    }

    protected function isEngagementStatus(Event $event): bool
    {
        $status = $event->status;

        if ($status instanceof EventStatus) {
            return $status->equals(Approved::class) || $status->equals(Pending::class);
        }

        return in_array((string) $status, Event::ENGAGEABLE_STATUSES, true);
    }

    /**
     * Determine if the currently authenticated user is the owner of the event.
     * Checks both user_id (owner) and submitter_id (submitter).
     */
    protected function isEventOwner(Event $event): bool
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return false;
        }

        if ($event->user_id === $user->id || $event->submitter_id === $user->id) {
            return true;
        }

        return \App\Models\EventSubmission::where('event_id', $event->id)
            ->where('submitted_by', $user->id)
            ->exists();
    }

    /**
     * @return array{
     *   available: bool,
     *   reason: string|null,
     *   method: 'self_reported'|'registered_self_checkin',
     *   registration_id: string|null,
     *   event_session_id: string|null
     * }
     */
    protected function resolveCheckInState(User $user): array
    {
        if (! $this->event->is_active || $this->event->visibility !== EventVisibility::Public || ! $this->isEngagementStatus($this->event)) {
            return [
                'available' => false,
                'reason' => __('Majlis ini tidak tersedia untuk check-in.'),
                'method' => 'self_reported',
                'registration_id' => null,
                'event_session_id' => null,
            ];
        }

        $startsAt = $this->event->starts_at;
        if (! $startsAt instanceof CarbonInterface) {
            return [
                'available' => false,
                'reason' => __('Masa majlis belum ditetapkan untuk check-in.'),
                'method' => 'self_reported',
                'registration_id' => null,
                'event_session_id' => null,
            ];
        }

        $eventTimezone = $this->event->timezone ?: 'Asia/Kuala_Lumpur';
        $windowStartsAt = $startsAt->copy()->setTimezone($eventTimezone)->subHours(2);
        $windowEndsAt = $startsAt->copy()->setTimezone($eventTimezone)->addHours(8);
        $now = now($eventTimezone);

        if ($now->lt($windowStartsAt)) {
            return [
                'available' => false,
                'reason' => __('Check-in dibuka 2 jam sebelum majlis bermula.'),
                'method' => 'self_reported',
                'registration_id' => null,
                'event_session_id' => null,
            ];
        }

        if ($now->gt($windowEndsAt)) {
            return [
                'available' => false,
                'reason' => __('Tempoh check-in telah tamat.'),
                'method' => 'self_reported',
                'registration_id' => null,
                'event_session_id' => null,
            ];
        }

        $registrationRequired = (bool) data_get($this->event, 'settings.registration_required', false);
        if (! $registrationRequired) {
            return [
                'available' => true,
                'reason' => null,
                'method' => 'self_reported',
                'registration_id' => null,
                'event_session_id' => null,
            ];
        }

        /** @var Registration|null $registration */
        $registration = Registration::query()
            ->where('event_id', $this->event->id)
            ->where('user_id', $user->id)
            ->where('status', '!=', 'cancelled')
            ->latest('created_at')
            ->first();

        if (! $registration instanceof Registration) {
            return [
                'available' => false,
                'reason' => __('Majlis ini memerlukan pendaftaran sebelum check-in.'),
                'method' => 'registered_self_checkin',
                'registration_id' => null,
                'event_session_id' => null,
            ];
        }

        return [
            'available' => true,
            'reason' => null,
            'method' => 'registered_self_checkin',
            'registration_id' => $registration->id,
            'event_session_id' => $registration->event_session_id,
        ];
    }
}
