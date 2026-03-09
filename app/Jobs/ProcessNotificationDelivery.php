<?php

namespace App\Jobs;

use App\Models\NotificationDelivery;
use App\Services\Notifications\NotificationEngine;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessNotificationDelivery implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $deliveryId,
    ) {}

    public function handle(NotificationEngine $engine): void
    {
        $delivery = NotificationDelivery::query()->find($this->deliveryId);

        if (! $delivery instanceof NotificationDelivery) {
            return;
        }

        $engine->processDelivery($delivery);
    }
}
