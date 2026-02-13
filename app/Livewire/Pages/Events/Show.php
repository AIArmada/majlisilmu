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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

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
            'institution.media',
            'venue.media',
            'speakers.media',
            'tags',
            'donationChannel',
            'settings',
            'sessions',
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

        $poster = $this->event->getFirstMedia('poster');

        if ($poster instanceof Media) {
            $images[] = $this->buildGalleryImagePayload($poster, __('Poster'));
        }

        foreach ($this->event->getMedia('gallery') as $galleryMedia) {
            if ($galleryMedia->id === $poster?->id) {
                continue;
            }

            $images[] = $this->buildGalleryImagePayload(
                $galleryMedia,
                __('Photo :number', ['number' => $imageCounter++])
            );
        }

        return $images;
    }

    /**
     * @return Collection<int, Event>
     */
    #[Computed]
    public function relatedEvents(): Collection
    {
        $tagIds = $this->event->tags->pluck('id')->all();
        $query = Event::query()
            ->whereKeyNot($this->event->id)
            ->where('visibility', EventVisibility::Public)
            ->where('is_active', true)
            ->where(function (Builder $statusQuery): void {
                $statusQuery
                    ->whereState('status', Approved::class)
                    ->orWhereState('status', Pending::class);
            })
            ->with([
                'institution:id,name,slug',
                'institution.media',
                'venue:id,name',
                'speakers:id,name',
                'speakers.media',
                'media',
            ])
            ->withCount([
                'tags as shared_tags_count' => fn (Builder $tagQuery) => $tagQuery->whereIn('tags.id', $tagIds),
            ])
            ->orderByDesc('shared_tags_count');

        if ($this->event->institution_id !== null) {
            $query->orderByRaw(
                'CASE WHEN institution_id = ? THEN 1 ELSE 0 END DESC',
                [$this->event->institution_id]
            );
        }

        return $query
            ->orderBy('starts_at')
            ->limit(6)
            ->get();
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
                return $session->status === SessionStatus::Scheduled
                    && $session->starts_at !== null
                    && $session->starts_at->greaterThanOrEqualTo($now);
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
     * @return array{whatsapp: string, telegram: string, facebook: string, x: string, email: string}
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
            'facebook' => "https://www.facebook.com/sharer/sharer.php?u={$encodedUrl}",
            'x' => "https://x.com/intent/tweet?text={$encodedText}&url={$encodedUrl}",
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
