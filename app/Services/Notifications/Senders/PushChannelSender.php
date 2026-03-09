<?php

namespace App\Services\Notifications\Senders;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Models\NotificationDestination;
use App\Models\NotificationMessage;
use App\Services\Notifications\ChannelSendResult;
use App\Services\Notifications\Contracts\NotificationChannelSender;
use Illuminate\Support\Facades\Http;

class PushChannelSender implements NotificationChannelSender
{
    public function channel(): NotificationChannel
    {
        return NotificationChannel::Push;
    }

    public function send(NotificationMessage $message, ?NotificationDestination $destination, array $payload = []): ChannelSendResult
    {
        $deviceToken = $destination?->external_id;
        $projectId = config('notification-center.push.project_id');
        $credentials = config('notification-center.push.credentials');

        if (! is_string($deviceToken) || $deviceToken === '' || ! is_string($projectId) || $projectId === '' || ! is_string($credentials) || $credentials === '') {
            return new ChannelSendResult(NotificationDeliveryStatus::Skipped);
        }

        $endpoint = str_replace('{project}', $projectId, (string) config('notification-center.push.endpoint'));

        $response = Http::withToken($credentials)->post($endpoint, [
            'message' => [
                'token' => $deviceToken,
                'notification' => [
                    'title' => $message->title,
                    'body' => $message->body,
                ],
                'data' => [
                    'notification_id' => $message->id,
                    'family' => (string) $message->family?->value,
                    'trigger' => (string) $message->trigger?->value,
                    'action_url' => (string) ($message->action_url ?? ''),
                ],
            ],
        ]);

        if ($response->failed()) {
            return new ChannelSendResult(
                NotificationDeliveryStatus::Failed,
                meta: ['response' => $response->json()]
            );
        }

        return new ChannelSendResult(
            NotificationDeliveryStatus::Delivered,
            providerMessageId: (string) ($response->json('name') ?? ''),
            meta: ['response' => $response->json()]
        );
    }
}
