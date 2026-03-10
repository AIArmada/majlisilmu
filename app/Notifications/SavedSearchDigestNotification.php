<?php

namespace App\Notifications;

use App\Enums\NotificationChannel;
use App\Enums\NotificationPreferenceKey;
use App\Models\Event;
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
     *
     * @param  Collection<int, Event>  $events
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
        $configuredChannels = method_exists($notifiable, 'notificationChannelsFor')
            ? $notifiable->notificationChannelsFor(
                NotificationPreferenceKey::SavedSearchDigest->value,
                [NotificationChannel::Email->value]
            )
            : [NotificationChannel::Email->value];

        $channels = [];

        foreach ($configuredChannels as $channel) {
            if ($channel === NotificationChannel::Email->value) {
                if (filled($notifiable->email ?? null)) {
                    $channels[] = 'mail';
                }

                continue;
            }

            if ($channel === NotificationChannel::InApp->value) {
                $channels[] = 'database';
            }
        }

        return array_values(array_unique($channels));
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
            $venue = 'Location TBA';
            if ($event->venue) {
                $venue = $event->venue->name;
            } elseif ($event->institution) {
                $venue = $event->institution->name;
            }

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
