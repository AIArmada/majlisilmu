<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\ModerationReview;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when an event is rejected.
 * Per documentation B4a.
 */
class EventRejectedNotification extends Notification implements ShouldQueue
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
        $reason = $this->review?->reason_code ?? 'policy_violation';
        $note = $this->review?->note ?? 'The event did not meet our content guidelines.';

        $reasonLabels = [
            'duplicate' => 'This event appears to be a duplicate of an existing listing.',
            'incomplete_info' => 'The event information was incomplete or unclear.',
            'policy_violation' => 'The event did not comply with our content policies.',
            'spam' => 'The submission was flagged as spam.',
            'other' => $note,
        ];

        return (new MailMessage)
            ->subject('❌ Event Not Approved')
            ->greeting("Assalamualaikum {$notifiable->name},")
            ->line("We were unable to approve your event **\"{$this->event->title}\"**.")
            ->line('**Reason:** '.($reasonLabels[$reason] ?? $note))
            ->line('**Moderator Note:** '.$note)
            ->line('You may edit the event and resubmit it for review.')
            ->action('Edit and Resubmit', url('/admin/events/'.$this->event->id.'/edit'))
            ->line('If you believe this decision was made in error, please contact our support team.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'reason_code' => $this->review?->reason_code,
            'review_note' => $this->review?->note,
            'type' => 'event_rejected',
        ];
    }
}
