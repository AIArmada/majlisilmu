<?php

namespace App\States\EventStatus\Transitions;

use App\Models\Event;
use App\Models\Institution;
use App\Models\ModerationReview;
use App\Models\Speaker;
use App\Models\Tag;
use App\Models\User;
use App\Models\Venue;
use App\Services\Notifications\EventNotificationService;
use App\States\EventStatus\Approved;
use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\ModelStates\Transition;

class ApproveEvent extends Transition implements HasColor, HasIcon, HasLabel
{
    public function __construct(
        public Event $event,
        public ?User $moderator = null,
        public ?string $note = null
    ) {}

    public function handle(): Event
    {
        return DB::transaction(function () {
            // Create review record
            ModerationReview::create([
                'event_id' => $this->event->id,
                'moderator_id' => $this->moderator?->id,
                'decision' => 'approved',
                'note' => $this->note,
            ]);

            // Update event status
            $this->event->status = Approved::class;
            $this->event->published_at = now();
            $this->event->save();

            // Auto-verify pending related records (by approving the event, moderator implicitly verifies these entities)
            $this->verifyPendingRelatedRecords($this->event);

            // Make searchable (Scout)
            $this->event->searchable();

            app(EventNotificationService::class)->notifySubmissionApproved($this->event);
            app(EventNotificationService::class)->notifyPublication($this->event);

            Log::info('Event approved', [
                'event_id' => $this->event->id,
                'moderator_id' => $this->moderator?->id,
            ]);

            return $this->event;
        });
    }

    /**
     * Auto-verify pending Speaker/Institution/Venue records.
     * By approving the event, the moderator implicitly verifies these related entities.
     */
    protected function verifyPendingRelatedRecords(Event $event): void
    {
        // Verify linked speaker profiles across all event roles.
        Speaker::query()
            ->whereIn('id', $event->keyPeople()->whereNotNull('speaker_id')->pluck('speaker_id'))
            ->where('status', 'pending')
            ->update(['status' => 'verified']);

        // Verify organizer if Speaker
        if ($event->organizer_type === Speaker::class && $event->organizer_id) {
            Speaker::where('id', $event->organizer_id)
                ->where('status', 'pending')
                ->update(['status' => 'verified']);
        }

        // Verify organizer if Institution
        if ($event->organizer_type === Institution::class && $event->organizer_id) {
            Institution::where('id', $event->organizer_id)
                ->where('status', 'pending')
                ->update(['status' => 'verified']);
        }

        // Verify location institution
        if ($event->institution_id) {
            Institution::where('id', $event->institution_id)
                ->where('status', 'pending')
                ->update(['status' => 'verified']);
        }

        // Verify venue
        if ($event->venue_id) {
            Venue::where('id', $event->venue_id)
                ->where('status', 'pending')
                ->update(['status' => 'verified']);
        }

        // Verify tags
        Tag::whereIn('id', $event->tags->pluck('id'))
            ->where('status', 'pending')
            ->update(['status' => 'verified']);

        // Verify references
        $event->references()
            ->where('status', 'pending')
            ->update(['status' => 'verified']);

        Log::info('Auto-verified pending related records', [
            'event_id' => $event->id,
        ]);
    }

    public function getLabel(): string
    {
        return __('Approve Event');
    }

    public function getColor(): string|array
    {
        return Color::Emerald;
    }

    public function getIcon(): string
    {
        return 'heroicon-o-check-circle';
    }
}
