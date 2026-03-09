<?php

namespace App\Jobs;

use App\Enums\NotificationCadence;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationFamily;
use App\Enums\NotificationPriority;
use App\Enums\NotificationTrigger;
use App\Models\NotificationDelivery;
use App\Models\NotificationMessage;
use App\Models\NotificationSetting;
use App\Models\User;
use App\Services\Notifications\NotificationEngine;
use App\Services\Notifications\NotificationSettingsManager;
use App\Support\Notifications\NotificationDispatchData;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

class DispatchNotificationDigests implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $cadence = 'daily',
    ) {}

    public function handle(NotificationEngine $engine, NotificationSettingsManager $settingsManager): void
    {
        $cadence = NotificationCadence::tryFrom($this->cadence) ?? NotificationCadence::Daily;
        $now = CarbonImmutable::now('UTC');
        $queryStart = $this->candidateQueryStart($cadence, $now);

        if (! $queryStart instanceof CarbonImmutable) {
            return;
        }

        /** @var Collection<int, NotificationMessage> $messages */
        $messages = NotificationMessage::query()
            ->with('user')
            ->where('occurred_at', '>=', $queryStart)
            ->where('occurred_at', '<=', $now)
            ->get()
            ->filter(fn (NotificationMessage $message): bool => (string) data_get($message->meta, 'delivery_cadence') === $cadence->value)
            ->values();

        $grouped = $messages->groupBy(fn (NotificationMessage $message): string => implode('|', [
            $message->user_id,
            $this->messageFamily($message)->value,
            $this->messageTrigger($message)->value,
        ]));

        foreach ($grouped as $group) {
            /** @var Collection<int, NotificationMessage> $group */
            $sample = $group->first();

            if (! $sample instanceof NotificationMessage || ! $sample->user instanceof User) {
                continue;
            }

            $user = $sample->user;
            $sampleTrigger = $this->messageTrigger($sample);
            $samplePriority = $this->messagePriority($sample);
            $policy = $settingsManager->resolvePolicy($user, $sampleTrigger);

            $window = $this->digestWindow($policy, $cadence, $now);

            if ($policy->cadence !== $cadence || $window === null) {
                continue;
            }

            $undelivered = $group->filter(function (NotificationMessage $message) use ($policy, $window): bool {
                $occurredAt = $message->getRawOriginal('occurred_at');

                if (! is_string($occurredAt) || $occurredAt === '') {
                    return false;
                }

                $occurredAtUtc = CarbonImmutable::parse($occurredAt, 'UTC');

                if ($occurredAtUtc->lessThan($window['start']) || ! $occurredAtUtc->lessThan($window['end'])) {
                    return false;
                }

                return ! NotificationDelivery::query()
                    ->where('notification_message_id', $message->id)
                    ->whereIn('channel', array_diff($policy->channels, ['in_app']))
                    ->where('status', NotificationDeliveryStatus::Delivered->value)
                    ->exists();
            })->values();

            if ($undelivered->isEmpty()) {
                continue;
            }

            $count = $undelivered->count();
            $title = __('notifications.messages.digest.title', [
                'count' => $count,
                'label' => $sample->title,
            ]);
            $body = __('notifications.messages.digest.body', [
                'count' => $count,
            ]);
            $digestFingerprint = 'digest:'.$cadence->value.':'.$sampleTrigger->value.':'.$sample->user_id.':'.$window['end']->format('YmdHi');

            $digestMessage = $engine->dispatchToUser($user, new NotificationDispatchData(
                trigger: $sampleTrigger,
                title: $title,
                body: $body,
                actionUrl: route('dashboard.notifications'),
                entityType: $sample->entity_type,
                entityId: $sample->entity_id,
                priority: $samplePriority,
                forcedCadence: NotificationCadence::Instant,
                fingerprint: $digestFingerprint,
                meta: [
                    'digest' => true,
                    'source_message_ids' => $undelivered->pluck('id')->all(),
                ],
                occurredAt: $window['end'],
            ));

            if (! $digestMessage instanceof NotificationMessage) {
                continue;
            }
        }
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}|null
     */
    protected function digestWindow(
        \App\Support\Notifications\ResolvedNotificationPolicy $policy,
        NotificationCadence $cadence,
        CarbonImmutable $now,
    ): ?array {
        return $this->digestWindowForSchedule(
            timezone: $policy->timezone,
            digestDeliveryTime: $policy->digestDeliveryTime,
            digestWeeklyDay: $policy->digestWeeklyDay,
            cadence: $cadence,
            now: $now,
        );
    }

    protected function candidateQueryStart(NotificationCadence $cadence, CarbonImmutable $now): ?CarbonImmutable
    {
        /** @var Collection<int, NotificationSetting> $settings */
        $settings = NotificationSetting::query()->get();

        /** @var Collection<int, CarbonImmutable> $windowStarts */
        $windowStarts = $settings
            ->map(function (NotificationSetting $setting) use ($cadence, $now): ?CarbonImmutable {
                $window = $this->digestWindowForSchedule(
                    timezone: (string) ($setting->timezone ?: config('app.timezone')),
                    digestDeliveryTime: is_string($setting->digest_delivery_time) && $setting->digest_delivery_time !== ''
                        ? $setting->digest_delivery_time
                        : (string) config('notification-center.defaults.digest_delivery_time', '08:00:00'),
                    digestWeeklyDay: (int) ($setting->digest_weekly_day ?: 1),
                    cadence: $cadence,
                    now: $now,
                );

                return $window['start'] ?? null;
            })
            ->filter(fn (mixed $start): bool => $start instanceof CarbonImmutable)
            ->values();

        if ($windowStarts->isEmpty()) {
            return null;
        }

        /** @var CarbonImmutable $earliest */
        $earliest = $windowStarts
            ->sortBy(fn (CarbonImmutable $start): int => $start->getTimestamp())
            ->first();

        return $earliest;
    }

    /**
     * @return array{start: CarbonImmutable, end: CarbonImmutable}|null
     */
    protected function digestWindowForSchedule(
        string $timezone,
        ?string $digestDeliveryTime,
        int $digestWeeklyDay,
        NotificationCadence $cadence,
        CarbonImmutable $now,
    ): ?array {
        $localNow = $now->timezone($timezone);
        $scheduledAt = $localNow->setTimeFromTimeString($digestDeliveryTime ?: '08:00:00');

        if ($cadence === NotificationCadence::Weekly && $localNow->dayOfWeekIso !== $digestWeeklyDay) {
            return null;
        }

        if ($localNow->lessThan($scheduledAt)) {
            return null;
        }

        if ($scheduledAt->diffInMinutes($localNow) >= 15) {
            return null;
        }

        $windowStart = $cadence === NotificationCadence::Weekly
            ? $scheduledAt->subWeek()
            : $scheduledAt->subDay();

        return [
            'start' => $windowStart->utc(),
            'end' => $scheduledAt->utc(),
        ];
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

    protected function messagePriority(NotificationMessage $message): NotificationPriority
    {
        $priority = $message->priority;

        return $priority instanceof NotificationPriority
            ? $priority
            : NotificationPriority::from((string) $priority);
    }
}
