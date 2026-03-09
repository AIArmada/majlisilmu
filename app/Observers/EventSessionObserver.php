<?php

namespace App\Observers;

use App\Models\EventSession;
use App\Services\EventScheduleProjectorService;

class EventSessionObserver
{
    public function __construct(
        protected EventScheduleProjectorService $projector,
    ) {}

    public function created(EventSession $session): void
    {
        $this->syncEventProjection($session);
    }

    public function updated(EventSession $session): void
    {
        $this->syncEventProjection($session);
    }

    public function deleted(EventSession $session): void
    {
        $this->syncEventProjection($session);
    }

    protected function syncEventProjection(EventSession $session): void
    {
        $event = $session->event;

        if ($event === null) {
            return;
        }

        $refreshedEvent = $event->fresh();

        $this->projector->project($refreshedEvent);
        app(\App\Services\Notifications\EventNotificationService::class)
            ->notifySessionChange($refreshedEvent);
    }
}
