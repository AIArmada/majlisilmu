<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCadence;
use App\Enums\NotificationChannel;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationFamily;
use App\Enums\NotificationPriority;
use App\Enums\NotificationTrigger;
use App\Jobs\ProcessNotificationDelivery;
use App\Models\NotificationDelivery;
use App\Models\NotificationDestination;
use App\Models\NotificationMessage;
use App\Models\User;
use App\Services\Notifications\Contracts\NotificationChannelSender;
use App\Services\Notifications\Senders\InAppChannelSender;
use App\Services\Notifications\Senders\MailChannelSender;
use App\Services\Notifications\Senders\PushChannelSender;
use App\Services\Notifications\Senders\WhatsappChannelSender;
use App\Support\Notifications\NotificationDispatchData;
use App\Support\Notifications\ResolvedNotificationPolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Throwable;

class NotificationEngine
{
    /**
     * @var array<string, NotificationChannelSender>
     */
    protected array $senders = [];

    public function __construct(
        protected NotificationSettingsManager $settingsManager,
        MailChannelSender $mailChannelSender,
        InAppChannelSender $inAppChannelSender,
        PushChannelSender $pushChannelSender,
        WhatsappChannelSender $whatsappChannelSender,
    ) {
        $this->senders = [
            $mailChannelSender->channel()->value => $mailChannelSender,
            $inAppChannelSender->channel()->value => $inAppChannelSender,
            $pushChannelSender->channel()->value => $pushChannelSender,
            $whatsappChannelSender->channel()->value => $whatsappChannelSender,
        ];
    }

    /**
     * @param  iterable<int, User>  $users
     */
    public function dispatch(iterable $users, NotificationDispatchData $data): void
    {
        /** @var Collection<int, User> $collection */
        $collection = collect($users)
            ->unique('id')
            ->values();

        foreach ($collection as $user) {
            $this->dispatchToUser($user, $data);
        }
    }

    public function dispatchToUser(User $user, NotificationDispatchData $data): ?NotificationMessage
    {
        $policy = $this->settingsManager->resolvePolicy($user, $data->trigger);

        if (! $policy->enabled) {
            return null;
        }

        $effectiveCadence = $data->forcedCadence ?? $policy->cadence;

        if ($effectiveCadence === NotificationCadence::Off) {
            return null;
        }

        $message = $this->createMessage($user, $policy, $data, $effectiveCadence);
        $channelOrder = $this->orderedChannels($policy);
        $shouldBypassQuietHours = $data->bypassQuietHours && $policy->urgentOverride;
        $createsInAppDelivery = $effectiveCadence === NotificationCadence::Instant
            && $this->shouldCreateInAppDelivery($channelOrder);

        $message->forceFill([
            'channels_attempted' => array_values(array_unique(array_merge(
                $createsInAppDelivery ? [NotificationChannel::InApp->value] : [],
                $effectiveCadence === NotificationCadence::Instant ? $channelOrder : []
            ))),
        ])->save();

        if ($createsInAppDelivery) {
            $this->deliverInApp($message, $user, $policy);
        }

        if ($effectiveCadence !== NotificationCadence::Instant) {
            return $message;
        }

        foreach ($channelOrder as $channelValue) {
            if ($channelValue === NotificationChannel::InApp->value) {
                continue;
            }

            $channel = NotificationChannel::from($channelValue);
            $fallbackChannels = $this->fallbackChannelsFor($policy, $channel, $channelOrder);

            $this->queueChannelDelivery(
                user: $user,
                message: $message,
                policy: $policy,
                channel: $channel,
                fallbackChannels: $fallbackChannels,
                forceImmediate: true,
                bypassQuietHours: $shouldBypassQuietHours,
            );
        }

        return $message;
    }

    public function processDelivery(NotificationDelivery $delivery): void
    {
        $delivery->loadMissing(['message', 'user', 'destination']);

        $message = $delivery->message;

        if (! $message instanceof NotificationMessage || $delivery->status === NotificationDeliveryStatus::Delivered) {
            return;
        }

        if (! $this->claimDelivery($delivery)) {
            return;
        }

        $delivery->refresh();
        $delivery->loadMissing(['message', 'user', 'destination']);

        $message = $delivery->message;

        if (! $message instanceof NotificationMessage) {
            return;
        }

        $deliveryChannel = $delivery->channel;
        $channel = $deliveryChannel instanceof NotificationChannel
            ? $deliveryChannel
            : NotificationChannel::from((string) $deliveryChannel);
        $sender = $this->senders[$channel->value] ?? null;

        if (! $sender instanceof NotificationChannelSender) {
            $delivery->forceFill([
                'status' => NotificationDeliveryStatus::Skipped->value,
                'failed_at' => now(),
                'meta' => array_merge((array) $delivery->meta, ['reason' => 'missing_sender']),
            ])->save();

            return;
        }

        try {
            $result = $sender->send($message, $delivery->destination, $delivery->payload ?? []);
        } catch (Throwable $exception) {
            $delivery->forceFill([
                'status' => NotificationDeliveryStatus::Failed->value,
                'failed_at' => now(),
                'meta' => array_merge((array) $delivery->meta, [
                    'exception' => $exception::class,
                    'message' => $exception->getMessage(),
                ]),
            ])->save();

            throw $exception;
        }

        $delivery->forceFill([
            'status' => $result->status->value,
            'provider_message_id' => $result->providerMessageId,
            'meta' => array_merge((array) $delivery->meta, $result->meta),
            'sent_at' => now(),
            'delivered_at' => $result->status === NotificationDeliveryStatus::Delivered ? now() : $delivery->delivered_at,
            'failed_at' => $result->status === NotificationDeliveryStatus::Failed ? now() : $delivery->failed_at,
        ])->save();

        if ($result->status === NotificationDeliveryStatus::Delivered) {
            $this->markDigestSourcesDelivered($delivery);
        }

        if (
            in_array($result->status, [NotificationDeliveryStatus::Failed, NotificationDeliveryStatus::Skipped], true)
            && $this->shouldQueueFallbackForChannel($delivery)
        ) {
            $this->queueFallbackDelivery($delivery);
        }
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    public function createDigestMessage(
        User $user,
        ResolvedNotificationPolicy $policy,
        string $title,
        string $body,
        string $fingerprint,
        array $meta = [],
    ): NotificationMessage {
        return NotificationMessage::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'fingerprint' => $fingerprint,
            ],
            [
                'family' => $policy->family->value,
                'trigger' => $policy->trigger->value,
                'title' => $title,
                'body' => $body,
                'priority' => $policy->trigger === NotificationTrigger::EventCancelled
                    ? NotificationPriority::Urgent->value
                    : NotificationPriority::Medium->value,
                'occurred_at' => now(),
                'meta' => $meta,
            ]
        );
    }

    /**
     * @param  list<string>  $channels
     */
    protected function shouldCreateInAppDelivery(array $channels): bool
    {
        return $channels === [] || in_array(NotificationChannel::InApp->value, $channels, true);
    }

    protected function deliverInApp(NotificationMessage $message, User $user, ResolvedNotificationPolicy $policy): void
    {
        $fingerprint = sha1(implode('|', [$message->id, NotificationChannel::InApp->value, 'archive']));

        NotificationDelivery::query()->firstOrCreate(
            ['fingerprint' => $fingerprint],
            [
                'notification_message_id' => $message->id,
                'user_id' => $user->id,
                'family' => $policy->family->value,
                'trigger' => $policy->trigger->value,
                'channel' => NotificationChannel::InApp->value,
                'status' => NotificationDeliveryStatus::Delivered->value,
                'sent_at' => now(),
                'delivered_at' => now(),
            ]
        );
    }

    /**
     * @param  list<string>  $fallbackChannels
     */
    protected function queueChannelDelivery(
        User $user,
        NotificationMessage $message,
        ResolvedNotificationPolicy $policy,
        NotificationChannel $channel,
        array $fallbackChannels,
        bool $forceImmediate,
        bool $bypassQuietHours,
    ): void {
        $destinations = $this->settingsManager->destinationsFor($user, $channel);

        if ($destinations->isEmpty()) {
            $this->queueFallbackByPolicy($user, $message, $policy, $fallbackChannels, $bypassQuietHours);

            return;
        }

        foreach ($destinations as $destination) {
            $deliverAfter = $this->deliverAfterFor($policy, $channel, $bypassQuietHours);
            $status = $deliverAfter === null || ! $forceImmediate
                ? NotificationDeliveryStatus::Pending
                : NotificationDeliveryStatus::Deferred;

            $delivery = NotificationDelivery::query()->firstOrCreate(
                [
                    'fingerprint' => sha1(implode('|', [
                        $message->id,
                        $channel->value,
                        (string) ($destination->id ?? 'none'),
                    ])),
                ],
                [
                    'notification_message_id' => $message->id,
                    'user_id' => $user->id,
                    'family' => $policy->family->value,
                    'trigger' => $policy->trigger->value,
                    'channel' => $channel->value,
                    'destination_id' => $destination->id,
                    'status' => $status->value,
                    'payload' => [
                        'action_label' => __('notifications.actions.open'),
                        'language' => $policy->locale,
                        'template' => 'notification_update',
                    ],
                    'meta' => [
                        'fallback_strategy' => $policy->fallbackStrategy,
                        'remaining_fallback_channels' => $fallbackChannels,
                        'deliver_after' => $deliverAfter?->toIso8601String(),
                        'bypass_quiet_hours' => $bypassQuietHours,
                    ],
                ]
            );

            if ($status === NotificationDeliveryStatus::Deferred) {
                continue;
            }

            ProcessNotificationDelivery::dispatch($delivery->id);
        }
    }

    /**
     * @param  list<string>  $fallbackChannels
     */
    protected function queueFallbackByPolicy(
        User $user,
        NotificationMessage $message,
        ResolvedNotificationPolicy $policy,
        array $fallbackChannels,
        bool $bypassQuietHours,
    ): void
    {
        if ($policy->fallbackStrategy === 'skip') {
            return;
        }

        if ($policy->fallbackStrategy === 'in_app_only') {
            $this->deliverInApp($message, $user, $policy);

            return;
        }

        foreach ($fallbackChannels as $fallbackChannel) {
            if ($fallbackChannel === NotificationChannel::InApp->value) {
                $this->deliverInApp($message, $user, $policy);

                return;
            }

            $destinations = $this->settingsManager->destinationsFor($user, NotificationChannel::from($fallbackChannel));

            if ($destinations->isNotEmpty()) {
                $this->queueChannelDelivery(
                    user: $user,
                    message: $message,
                    policy: $policy,
                    channel: NotificationChannel::from($fallbackChannel),
                    fallbackChannels: [],
                    forceImmediate: true,
                    bypassQuietHours: $bypassQuietHours,
                );

                return;
            }
        }
    }

    protected function queueFallbackDelivery(NotificationDelivery $delivery): void
    {
        /** @var array<string, mixed> $meta */
        $meta = is_array($delivery->meta) ? $delivery->meta : [];
        $strategy = (string) ($meta['fallback_strategy'] ?? 'skip');
        $message = $delivery->message;
        $user = $delivery->user;

        if (! $message instanceof NotificationMessage || ! $user instanceof User) {
            return;
        }

        $deliveryTrigger = $delivery->trigger;
        $trigger = $deliveryTrigger instanceof NotificationTrigger
            ? $deliveryTrigger
            : NotificationTrigger::from((string) $deliveryTrigger);
        $policy = $this->settingsManager->resolvePolicy($user, $trigger);

        if ($strategy === 'in_app_only') {
            $this->deliverInApp($message, $user, $policy);

            return;
        }

        if ($strategy !== 'next_available') {
            return;
        }

        $remainingFallbackChannels = is_array($meta['remaining_fallback_channels'] ?? null)
            ? $meta['remaining_fallback_channels']
            : [];

        $fallbackChannels = collect($remainingFallbackChannels)
            ->map(static fn (mixed $channel): string => (string) $channel)
            ->filter(static fn (string $channel): bool => $channel !== '')
            ->values()
            ->all();

        $this->queueFallbackByPolicy(
            $user,
            $message,
            $policy,
            $fallbackChannels,
            (bool) ($meta['bypass_quiet_hours'] ?? false),
        );
    }

    protected function claimDelivery(NotificationDelivery $delivery): bool
    {
        return NotificationDelivery::query()
            ->whereKey($delivery->id)
            ->whereIn('status', [
                NotificationDeliveryStatus::Pending->value,
                NotificationDeliveryStatus::Failed->value,
                NotificationDeliveryStatus::Skipped->value,
            ])
            ->update([
                'status' => NotificationDeliveryStatus::Sent->value,
                'failed_at' => null,
            ]) === 1;
    }

    protected function shouldQueueFallbackForChannel(NotificationDelivery $delivery): bool
    {
        $channel = $delivery->channel instanceof NotificationChannel
            ? $delivery->channel->value
            : (string) $delivery->channel;

        $siblingStatuses = NotificationDelivery::query()
            ->where('notification_message_id', $delivery->notification_message_id)
            ->where('channel', $channel)
            ->whereKeyNot($delivery->id)
            ->pluck('status')
            ->map(function (mixed $status): NotificationDeliveryStatus {
                return $status instanceof NotificationDeliveryStatus
                    ? $status
                    : NotificationDeliveryStatus::from((string) $status);
            });

        if ($siblingStatuses->contains(NotificationDeliveryStatus::Delivered)) {
            return false;
        }

        return ! $siblingStatuses->contains(function (NotificationDeliveryStatus $status): bool {
            return in_array($status, [
                NotificationDeliveryStatus::Pending,
                NotificationDeliveryStatus::Sent,
                NotificationDeliveryStatus::Deferred,
            ], true);
        });
    }

    protected function markDigestSourcesDelivered(NotificationDelivery $delivery): void
    {
        $digestMessage = $delivery->message;

        if (! $digestMessage instanceof NotificationMessage) {
            return;
        }

        /** @var array<string, mixed> $meta */
        $meta = is_array($digestMessage->meta) ? $digestMessage->meta : [];

        if (($meta['digest'] ?? false) !== true) {
            return;
        }

        $rawSourceMessageIds = is_array($meta['source_message_ids'] ?? null)
            ? $meta['source_message_ids']
            : [];

        $sourceMessageIds = collect($rawSourceMessageIds)
            ->map(static fn (mixed $messageId): string => (string) $messageId)
            ->filter(static fn (string $messageId): bool => $messageId !== '')
            ->values()
            ->all();

        if ($sourceMessageIds === []) {
            return;
        }

        $channel = $delivery->channel instanceof NotificationChannel
            ? $delivery->channel
            : NotificationChannel::from((string) $delivery->channel);

        NotificationMessage::query()
            ->whereIn('id', $sourceMessageIds)
            ->get()
            ->each(function (NotificationMessage $sourceMessage) use ($delivery, $digestMessage, $channel): void {
                NotificationDelivery::query()->firstOrCreate(
                    [
                        'fingerprint' => sha1('digest-source|'.$sourceMessage->id.'|'.$channel->value.'|'.$digestMessage->id),
                    ],
                    [
                        'notification_message_id' => $sourceMessage->id,
                        'user_id' => $delivery->user_id,
                        'family' => $this->messageFamily($sourceMessage)->value,
                        'trigger' => $this->messageTrigger($sourceMessage)->value,
                        'channel' => $channel->value,
                        'destination_id' => $delivery->destination_id,
                        'provider' => $delivery->provider,
                        'provider_message_id' => $delivery->provider_message_id,
                        'status' => NotificationDeliveryStatus::Delivered->value,
                        'payload' => ['digest_message_id' => $digestMessage->id],
                        'meta' => ['digest' => true],
                        'sent_at' => $delivery->sent_at,
                        'delivered_at' => $delivery->delivered_at,
                    ]
                );
            });
    }

    protected function createMessage(
        User $user,
        ResolvedNotificationPolicy $policy,
        NotificationDispatchData $data,
        NotificationCadence $effectiveCadence,
    ): NotificationMessage
    {
        $occurredAt = $data->occurredAt?->toDateTimeString() ?? now()->toDateTimeString();
        $meta = array_merge($data->meta, [
            'delivery_cadence' => $effectiveCadence->value,
            'inbox_visible' => $effectiveCadence === NotificationCadence::Instant,
        ]);

        if ($data->fingerprint !== null && $data->fingerprint !== '') {
            return NotificationMessage::query()->firstOrCreate(
                [
                    'user_id' => $user->id,
                    'fingerprint' => $data->fingerprint,
                ],
                [
                    'family' => $policy->family->value,
                    'trigger' => $policy->trigger->value,
                    'title' => $data->title,
                    'body' => $data->body,
                    'action_url' => $data->actionUrl,
                    'entity_type' => $data->entityType,
                    'entity_id' => $data->entityId,
                    'priority' => $data->priority->value,
                    'occurred_at' => $occurredAt,
                    'meta' => $meta,
                ]
            );
        }

        return NotificationMessage::query()->create([
            'user_id' => $user->id,
            'family' => $policy->family->value,
            'trigger' => $policy->trigger->value,
            'title' => $data->title,
            'body' => $data->body,
            'action_url' => $data->actionUrl,
            'entity_type' => $data->entityType,
            'entity_id' => $data->entityId,
            'priority' => $data->priority->value,
            'occurred_at' => $occurredAt,
            'meta' => $meta,
        ]);
    }

    /**
     * @return list<string>
     */
    protected function orderedChannels(ResolvedNotificationPolicy $policy): array
    {
        $preferred = collect($policy->preferredChannels)
            ->filter(static fn (string $channel): bool => in_array($channel, $policy->channels, true))
            ->values();

        $remaining = collect($policy->channels)
            ->reject(fn (string $channel): bool => $preferred->contains($channel))
            ->values();

        return $preferred->concat($remaining)->unique()->values()->all();
    }

    /**
     * @param  list<string>  $orderedChannels
     * @return list<string>
     */
    protected function fallbackChannelsFor(
        ResolvedNotificationPolicy $policy,
        NotificationChannel $channel,
        array $orderedChannels,
    ): array {
        $configuredFallbacks = collect($policy->fallbackChannels)
            ->map(static fn (string $value): string => $value)
            ->filter(function (string $fallbackChannel) use ($policy, $channel): bool {
                return $fallbackChannel !== NotificationChannel::InApp->value
                    && $fallbackChannel !== $channel->value
                    && in_array($fallbackChannel, $policy->channels, true);
            })
            ->values();

        if ($configuredFallbacks->isNotEmpty()) {
            return $configuredFallbacks->all();
        }

        return collect($orderedChannels)
            ->filter(fn (string $fallbackChannel): bool => $fallbackChannel !== NotificationChannel::InApp->value && $fallbackChannel !== $channel->value)
            ->values()
            ->all();
    }

    protected function messageFamily(NotificationMessage $message): NotificationFamily
    {
        $family = $message->family;

        return $family instanceof NotificationFamily
            ? $family
            : NotificationFamily::from((string) $family);
    }

    protected function messageTrigger(NotificationMessage $message): NotificationTrigger
    {
        $trigger = $message->trigger;

        return $trigger instanceof NotificationTrigger
            ? $trigger
            : NotificationTrigger::from((string) $trigger);
    }

    protected function deliverAfterFor(ResolvedNotificationPolicy $policy, NotificationChannel $channel, bool $bypassQuietHours): ?CarbonImmutable
    {
        if ($bypassQuietHours || ! in_array($channel, [NotificationChannel::Push, NotificationChannel::Whatsapp], true)) {
            return null;
        }

        if ($policy->quietHoursStart === null || $policy->quietHoursEnd === null) {
            return null;
        }

        $timezoneNow = CarbonImmutable::now($policy->timezone);
        $start = $timezoneNow->setTimeFromTimeString($policy->quietHoursStart);
        $end = $timezoneNow->setTimeFromTimeString($policy->quietHoursEnd);

        if ($end->lessThanOrEqualTo($start)) {
            $withinQuietHours = $timezoneNow->greaterThanOrEqualTo($start) || $timezoneNow->lessThan($end);

            if (! $withinQuietHours) {
                return null;
            }

            return $timezoneNow->lessThan($end)
                ? $end->utc()
                : $end->addDay()->utc();
        }

        return $timezoneNow->betweenIncluded($start, $end)
            ? $end->utc()
            : null;
    }
}
