<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EventEscalationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Event $event,
        public string $escalationType,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = match ($this->escalationType) {
            '48_hours' => "⚠️ Event Pending Review > 48 Hours: {$this->event->title}",
            '72_hours' => "🚨 URGENT: Event Pending > 72 Hours: {$this->event->title}",
            'urgent' => "⏰ Time-Sensitive: Event Starting Soon: {$this->event->title}",
            'priority' => "🔴 PRIORITY: Event Starting Soon: {$this->event->title}",
            default => "Event Escalation: {$this->event->title}",
        };

        $message = (new MailMessage)
            ->subject($subject)
            ->greeting($this->getGreeting())
            ->line($this->getMainMessage());

        if ($this->event->starts_at) {
            $message->line("Event starts: {$this->event->starts_at->format('l, F j, Y \\a\\t h:i A')}");
        }

        if ($this->event->institution) {
            $message->line("Institution: {$this->event->institution->name}");
        }

        $message->action('Review Event', url("/admin/events/{$this->event->id}"));

        if ($this->escalationType === 'priority') {
            $message->line('⚠️ This event requires immediate attention as it starts very soon.');
        } elseif ($this->escalationType === 'urgent') {
            $message->line('⏰ Please review this event soon - it starts within 24 hours.');
        }

        return $message;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'event_id' => $this->event->id,
            'event_title' => $this->event->title,
            'escalation_type' => $this->escalationType,
            'starts_at' => $this->event->starts_at?->toIso8601String(),
        ];
    }

    private function getGreeting(): string
    {
        return match ($this->escalationType) {
            '48_hours' => 'Moderation SLA Alert',
            '72_hours' => 'URGENT: Super Admin Escalation',
            'urgent' => 'Time-Sensitive Event Alert',
            'priority' => 'Priority Event Alert',
            default => 'Event Escalation',
        };
    }

    private function getMainMessage(): string
    {
        return match ($this->escalationType) {
            '48_hours' => 'The following event has been pending moderation for more than 48 hours:',
            '72_hours' => 'The following event has been pending moderation for more than 72 hours and requires immediate attention:',
            'urgent' => 'The following event is still pending moderation and starts within 24 hours:',
            'priority' => 'The following event is still pending moderation but starts within 6 hours:',
            default => 'An event requires your attention:',
        };
    }
}
