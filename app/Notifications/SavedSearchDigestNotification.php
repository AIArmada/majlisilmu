<?php

namespace App\Notifications;

use App\Models\SavedSearch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class SavedSearchDigestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public SavedSearch $savedSearch,
        public Collection $events
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $eventCount = $this->events->count();
        $searchName = $this->savedSearch->name;

        $mail = (new MailMessage)
            ->subject("🕌 {$eventCount} new events for \"{$searchName}\"")
            ->greeting("Assalamu'alaikum {$notifiable->name}!")
            ->line("We found **{$eventCount} new events** matching your saved search \"{$searchName}\":");

        // Add event summaries
        foreach ($this->events->take(5) as $event) {
            $date = $event->starts_at?->format('D, M d') ?? 'TBD';
            $venue = $event->venue?->name ?? ($event->institution?->name ?? 'Location TBA');

            $mail->line("📅 **{$event->title}**")
                ->line("   {$date} @ {$venue}");
        }

        if ($eventCount > 5) {
            $remaining = $eventCount - 5;
            $mail->line("...and {$remaining} more events.");
        }

        return $mail
            ->action('View All Events', route('events.index', ['search' => $this->savedSearch->query]))
            ->line('Jazakallahu khair for being part of our community!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'saved_search_id' => $this->savedSearch->id,
            'saved_search_name' => $this->savedSearch->name,
            'events_count' => $this->events->count(),
            'event_ids' => $this->events->pluck('id')->toArray(),
        ];
    }
}
