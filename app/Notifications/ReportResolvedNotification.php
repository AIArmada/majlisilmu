<?php

namespace App\Notifications;

use App\Models\Report;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ReportResolvedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Report $report,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->report->status === 'resolved' ? 'resolved' : 'dismissed';
        $entityType = ucfirst($this->report->entity_type);

        return (new MailMessage)
            ->subject("Your Report Has Been {$status}")
            ->greeting('Hello!')
            ->line("Your report about a {$entityType} has been reviewed by our moderation team.")
            ->line('Status: '.ucfirst($status))
            ->when($this->report->resolution_note, function ($message) {
                $message->line("Note: {$this->report->resolution_note}");
            })
            ->line('Thank you for helping us maintain the quality and accuracy of our platform.')
            ->salutation('The Majlis Ilmu Team');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'report_id' => $this->report->id,
            'entity_type' => $this->report->entity_type,
            'entity_id' => $this->report->entity_id,
            'status' => $this->report->status,
            'category' => $this->report->category,
        ];
    }
}
