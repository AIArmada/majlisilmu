<?php

namespace App\Services\Notifications\Senders;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Models\NotificationDestination;
use App\Models\NotificationMessage;
use App\Services\Notifications\ChannelSendResult;
use App\Services\Notifications\Contracts\NotificationChannelSender;

class InAppChannelSender implements NotificationChannelSender
{
    public function channel(): NotificationChannel
    {
        return NotificationChannel::InApp;
    }

    public function send(NotificationMessage $message, ?NotificationDestination $destination, array $payload = []): ChannelSendResult
    {
        return new ChannelSendResult(NotificationDeliveryStatus::Delivered);
    }
}
