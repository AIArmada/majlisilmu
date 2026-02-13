<?php

namespace App\Services;

use App\Enums\ScheduleKind;
use App\Enums\SessionStatus;
use App\Models\Event;
use App\Models\EventSession;
use Illuminate\Support\Carbon;

class EventScheduleProjectorService
{
    public function project(Event $event): void
    {
        if ($event->schedule_kind === ScheduleKind::Single && ! $event->sessions()->exists()) {
            return;
        }

        $timezone = $event->timezone ?: 'Asia/Kuala_Lumpur';
        $now = now($timezone);

        /** @var EventSession|null $nextSession */
        $nextSession = $event->sessions()
            ->where('status', SessionStatus::Scheduled->value)
            ->where('starts_at', '>=', $now)
            ->orderBy('starts_at')
            ->first();

        if (! $nextSession instanceof EventSession) {
            /** @var EventSession|null $nextSession */
            $nextSession = $event->sessions()
                ->where('status', SessionStatus::Scheduled->value)
                ->orderByDesc('starts_at')
                ->first();
        }

        if (! $nextSession instanceof EventSession) {
            return;
        }

        $sessionStart = $this->asCarbon($nextSession->starts_at);
        $sessionEnd = $this->asCarbon($nextSession->ends_at);

        if (! $sessionStart instanceof Carbon) {
            return;
        }

        $dirty = false;

        if (! $event->starts_at?->equalTo($sessionStart)) {
            $event->starts_at = $sessionStart;
            $dirty = true;
        }

        if (
            ($event->ends_at === null && $sessionEnd !== null)
            || ($event->ends_at !== null && $sessionEnd === null)
            || ($event->ends_at !== null && $sessionEnd !== null && ! $event->ends_at->equalTo($sessionEnd))
        ) {
            $event->ends_at = $sessionEnd;
            $dirty = true;
        }

        if ($dirty) {
            $event->save();
        }
    }

    private function asCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance((clone $value));
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
