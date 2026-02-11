<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\ModerationReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when an event needs changes.
 * Per documentation B4a.
 */
class EventNeedsChangesNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Event $event,
        public ?ModerationReview $review = null
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $note = $this->review && $this->review->note
            ? $this->review->note
            : 'Please review the event details and make necessary corrections.';

        return (new MailMessage)
            ->subject('📝 Changes Requested for Your Event')
            ->greeting("Assalamualaikum {$notifiable->name},")
            ->line("Your event **\"{$this->event->title}\"** requires some changes before it can be approved.")
            ->line('**Moderator Feedback:**')
            ->line($note)
            ->action('Edit Your Event', url('/admin/events/'.$this->event->id.'/edit'))
            ->line('Once you have made the changes, the event will be automatically resubmitted for review.')
            ->line('If you have questions, please contact our support team.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'review_note' => $this->review instanceof \App\Models\ModerationReview ? $this->review->note : null,
            'type' => 'event_needs_changes',
        ];
    }
}
