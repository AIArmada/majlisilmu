<?php

namespace App\Services\Notifications\Senders;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Models\NotificationDestination;
use App\Models\NotificationMessage;
use App\Services\Notifications\ChannelSendResult;
use App\Services\Notifications\Contracts\NotificationChannelSender;
use Illuminate\Support\Facades\Http;

class WhatsappChannelSender implements NotificationChannelSender
{
    public function channel(): NotificationChannel
    {
        return NotificationChannel::Whatsapp;
    }

    public function send(NotificationMessage $message, ?NotificationDestination $destination, array $payload = []): ChannelSendResult
    {
        $phone = $destination?->address;
        $baseUrl = rtrim((string) config('notification-center.whatsapp.base_url'), '/');
        $version = trim((string) config('notification-center.whatsapp.version'), '/');
        $phoneNumberId = config('notification-center.whatsapp.phone_number_id');
        $accessToken = config('notification-center.whatsapp.access_token');

        if (! is_string($phone) || $phone === '' || ! is_string($phoneNumberId) || $phoneNumberId === '' || ! is_string($accessToken) || $accessToken === '') {
            return new ChannelSendResult(NotificationDeliveryStatus::Skipped);
        }

        $template = (string) ($payload['template'] ?? 'notification_update');

        $response = Http::withToken($accessToken)->post("{$baseUrl}/{$version}/{$phoneNumberId}/messages", [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => [
                    'code' => (string) ($payload['language'] ?? 'en'),
                ],
                'components' => [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $message->title],
                            ['type' => 'text', 'text' => $message->body],
                            ['type' => 'text', 'text' => (string) ($message->action_url ?? route('dashboard'))],
                        ],
                    ],
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
            providerMessageId: (string) ($response->json('messages.0.id') ?? ''),
            meta: ['response' => $response->json()]
        );
    }
}
