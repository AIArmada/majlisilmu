<?php

namespace App\Jobs;

use App\Enums\NotificationDeliveryStatus;
use App\Models\NotificationDelivery;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessDeferredNotificationDeliveries implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $now = CarbonImmutable::now();

        NotificationDelivery::query()
            ->where('status', NotificationDeliveryStatus::Deferred->value)
            ->get()
            ->filter(function (NotificationDelivery $delivery) use ($now): bool {
                $deliverAfter = data_get($delivery->meta, 'deliver_after');

                if (! is_string($deliverAfter) || $deliverAfter === '') {
                    return true;
                }

                return CarbonImmutable::parse($deliverAfter)->lessThanOrEqualTo($now);
            })
            ->each(function (NotificationDelivery $delivery): void {
                $delivery->forceFill(['status' => NotificationDeliveryStatus::Pending->value])->save();

                ProcessNotificationDelivery::dispatch($delivery->id);
            });
    }
}
