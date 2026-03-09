<?php

namespace App\Services\Notifications\Senders;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Mail\NotificationMessageMail;
use App\Models\NotificationDestination;
use App\Models\NotificationMessage;
use App\Services\Notifications\ChannelSendResult;
use App\Services\Notifications\Contracts\NotificationChannelSender;
use Illuminate\Support\Facades\Mail;

class MailChannelSender implements NotificationChannelSender
{
    public function channel(): NotificationChannel
    {
        return NotificationChannel::Email;
    }

    public function send(NotificationMessage $message, ?NotificationDestination $destination, array $payload = []): ChannelSendResult
    {
        $address = $destination?->address;

        if (! is_string($address) || $address === '') {
            return new ChannelSendResult(NotificationDeliveryStatus::Skipped);
        }

        Mail::to($address)->send(new NotificationMessageMail(
            subjectLine: $message->title,
            title: $message->title,
            body: $message->body,
            actionUrl: $message->action_url,
            actionLabel: (string) ($payload['action_label'] ?? __('notifications.actions.open')),
        ));

        return new ChannelSendResult(NotificationDeliveryStatus::Delivered);
    }
}
