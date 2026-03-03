<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Event $event,
        public ?string $note = null
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
        $eventDate = $this->event->starts_at
            ? \App\Support\Timezone\UserDateTimeFormatter::translatedFormat($this->event->starts_at, 'l, j F Y')
            : 'TBD';
        $eventTime = $this->event->starts_at
            ? \App\Support\Timezone\UserDateTimeFormatter::format($this->event->starts_at, 'h:i A')
            : 'TBD';

        return (new MailMessage)
            ->subject('⚠️ Event Cancelled: '.$this->event->title)
            ->greeting("Assalamualaikum {$notifiable->name},")
            ->line("The event **\"{$this->event->title}\"** has been cancelled by the organizer/admin.")
            ->line("Date: {$eventDate}")
            ->line("Time: {$eventTime}")
            ->when(filled($this->note), fn (MailMessage $mail): MailMessage => $mail->line('Note: '.$this->note))
            ->action('View Event Details', route('events.show', $this->event))
            ->line('Please refer to the event page for the latest updates.');
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
            'note' => $this->note,
            'type' => 'event_cancelled',
        ];
    }
}
