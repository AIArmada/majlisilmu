<?php

namespace App\Services\Notifications\Contracts;

use App\Enums\NotificationChannel;
use App\Models\NotificationDestination;
use App\Models\NotificationMessage;
use App\Services\Notifications\ChannelSendResult;

interface NotificationChannelSender
{
    public function channel(): NotificationChannel;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function send(NotificationMessage $message, ?NotificationDestination $destination, array $payload = []): ChannelSendResult;
}
