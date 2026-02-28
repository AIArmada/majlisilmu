<?php

namespace App\Livewire\Pages\Events;

use App\Enums\EventVisibility;
use App\Enums\RegistrationMode;
use App\Enums\SessionStatus;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\User;
use App\Services\CalendarService;
use App\States\EventStatus\Approved;
use App\States\EventStatus\EventStatus;
use App\States\EventStatus\Pending;
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

    public int $interestsCount = 0;

    public int $goingCount = 0;

    public function mount(Event $event): void
    {
        $isViewable = $event->is_active && $this->isApprovedOrPending($event);
        $isOwner = $this->isEventOwner($event);

        // Public events: anyone can view if active and approved/pending
        if ($isViewable && $event->visibility === EventVisibility::Public) {
            // Allow access
        }
        // Unlisted events: anyone with link can view if active and approved/pending
        elseif ($isViewable && $event->visibility === EventVisibility::Unlisted) {
            // Allow access
        }
        // Private events: only owner can view if active and approved/pending
        elseif ($isViewable && $event->visibility === EventVisibility::Private && $isOwner) {
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
                    && $startsAt instanceof Carbon
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

        if (! $startsAt instanceof Carbon) {
            return 'upcoming';
        }

        if ($endsAt instanceof Carbon && $now->greaterThan($endsAt)) {
            return 'past';
        }

        if ($startsAt->isPast() && (! $endsAt instanceof Carbon || $now->lessThanOrEqualTo($endsAt))) {
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
        $eventUrl = route('events.show', $this->event);
        $shareText = trim($this->event->title.' - '.config('app.name'));
        $encodedUrl = urlencode($eventUrl);
        $encodedText = urlencode($shareText);
        $encodedBody = urlencode($shareText."\n".$eventUrl);

        return [
            'whatsapp' => "https://wa.me/?text={$encodedText}%20{$encodedUrl}",
            'telegram' => "https://t.me/share/url?url={$encodedUrl}&text={$encodedText}",
            'line' => "https://social-plugins.line.me/lineit/share?url={$encodedUrl}",
            'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$encodedUrl}",
            'x' => "https://x.com/intent/tweet?text={$encodedText}&url={$encodedUrl}",
            'instagram' => 'https://www.instagram.com/',
            'tiktok' => 'https://www.tiktok.com/',
            'email' => "mailto:?subject={$encodedText}&body={$encodedBody}",
        ];
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

    protected function toggleEngagement(string $relation, string $stateProperty, string $countColumn, ?string $countProperty = null): void
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            $this->redirectRoute('login');

            return;
        }

        if (! $this->isApprovedOrPending($this->event) || $this->event->visibility !== EventVisibility::Public) {
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
        }
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

            return;
        }

        $this->isSaved = $user->savedEvents()->where('event_id', $this->event->id)->exists();
        $this->isInterested = $user->interestedEvents()->where('event_id', $this->event->id)->exists();
        $this->isGoing = $user->goingEvents()->where('event_id', $this->event->id)->exists();
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

    protected function isApprovedOrPending(Event $event): bool
    {
        $status = $event->status;

        if ($status instanceof EventStatus) {
            return $status->equals(Approved::class) || $status->equals(Pending::class);
        }

        return in_array((string) $status, ['approved', 'pending'], true);
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

        return $event->user_id === $user->id || $event->submitter_id === $user->id;
    }
}
