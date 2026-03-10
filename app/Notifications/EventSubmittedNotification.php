<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent to moderators when a new event is submitted.
 * Per documentation B4a.
 */
class EventSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Event $event
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
        $institution = $this->event->institution;
        $institutionName = $institution ? $institution->name : 'Public Submission';

        return (new MailMessage)
            ->subject('🕌 New Event Submitted for Review')
            ->greeting('Assalamualaikum!')
            ->line('A new event has been submitted and requires moderation:')
            ->line("**{$this->event->title}**")
            ->line('Institution: '.$institutionName)
            ->line('Date: '.($this->event->starts_at?->format('D, M d, Y h:i A') ?? 'TBD'))
            ->action('Review Event', url('/admin/moderation-queue'))
            ->line('Please review this submission at your earliest convenience.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $institution = $this->event->institution;

        return [
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'institution_name' => $institution ? $institution->name : null,
            'type' => 'event_submitted',
        ];
    }
}
