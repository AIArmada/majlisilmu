<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Notification sent when an event is approved.
 * Per documentation B4a.
 */
class EventApprovedNotification extends Notification implements ShouldQueue
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
        return (new MailMessage)
            ->subject('✅ Your Event Has Been Approved!')
            ->greeting("Alhamdulillah {$notifiable->name}!")
            ->line("Great news! Your event **\"{$this->event->title}\"** has been approved and is now live.")
            ->line('Date: '.($this->event->starts_at?->format('l, F j, Y') ?? 'TBD'))
            ->line('Time: '.($this->event->starts_at?->format('h:i A') ?? 'TBD'))
            ->action('View Your Event', route('events.show', $this->event->slug))
            ->line('Share this event with your community to maximize attendance!')
            ->line('Jazakallahu khair for using Majlis Ilmu.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'event_slug' => $this->event->slug,
            'type' => 'event_approved',
        ];
    }
}
