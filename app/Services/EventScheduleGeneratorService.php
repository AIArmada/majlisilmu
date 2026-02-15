<?php

namespace App\Services;

use App\Enums\PrayerOffset;
use App\Enums\PrayerReference;
use App\Enums\RecurrenceFrequency;
use App\Enums\ScheduleState;
use App\Enums\SessionStatus;
use App\Enums\TimingMode;
use App\Models\Event;
use App\Models\EventRecurrenceRule;
use App\Models\EventSession;
use App\States\EventStatus\Approved;
use Carbon\Carbon;
use Illuminate\Support\Arr;

class EventScheduleGeneratorService
{
    public function __construct(
        protected PrayerTimeService $prayerTimeService,
        protected EventScheduleProjectorService $projector,
        protected ModerationService $moderationService,
    ) {}

    public function syncRecurringSessions(Event $event, EventRecurrenceRule $rule, bool $projectAfterSync = true): int
    {
        if ($rule->status !== ScheduleState::Active) {
            return 0;
        }

        $timezone = $rule->timezone ?: ($event->timezone ?: 'Asia/Kuala_Lumpur');
        $startDate = Carbon::parse($rule->start_date, $timezone)->startOfDay();
        $untilDate = $rule->until_date ? Carbon::parse($rule->until_date, $timezone)->endOfDay() : null;
        $maxCount = $rule->occurrence_count;

        if (! $untilDate instanceof Carbon && ! is_int($maxCount)) {
            throw new \InvalidArgumentException('Recurring schedules must have until_date or occurrence_count.');
        }

        $existingStarts = $rule->sessions()
            ->pluck('starts_at')
            ->map(fn (mixed $date): string => Carbon::parse($date, $timezone)->format('Y-m-d H:i:s'))
            ->flip();

        $created = 0;
        $matchedCount = $existingStarts->count();
        $cursor = $startDate->copy();

        if ($rule->generated_until !== null) {
            $generatedUntil = Carbon::parse($rule->generated_until, $timezone)->addDay()->startOfDay();

            if ($generatedUntil->greaterThan($cursor)) {
                $cursor = $generatedUntil;
            }
        }

        $safetyCounter = 0;

        EventSession::withoutEvents(function () use (
            &$cursor,
            &$created,
            &$matchedCount,
            &$safetyCounter,
            $untilDate,
            $maxCount,
            $startDate,
            $rule,
            $event,
            $timezone,
            $existingStarts
        ): void {
            while (true) {
                if ($untilDate instanceof Carbon && $cursor->greaterThan($untilDate)) {
                    break;
                }

                if (is_int($maxCount) && $matchedCount >= $maxCount) {
                    break;
                }

                if ($this->matchesRuleDate($cursor, $rule, $startDate)) {
                    $startsAt = $this->resolveSessionStart($event, $rule, $cursor, $timezone);

                    if ($startsAt instanceof Carbon) {
                        $matchedCount++;
                        $startKey = $startsAt->copy()->setTimezone($timezone)->format('Y-m-d H:i:s');

                        if (! $existingStarts->has($startKey)) {
                            $endsAt = $this->resolveSessionEnd($rule, $startsAt, $timezone);

                            EventSession::query()->create([
                                'event_id' => $event->id,
                                'recurrence_rule_id' => $rule->id,
                                'starts_at' => $startsAt,
                                'ends_at' => $endsAt,
                                'timezone' => $timezone,
                                'status' => SessionStatus::Scheduled,
                                'is_generated' => true,
                                'timing_mode' => $rule->timing_mode,
                                'prayer_reference' => $rule->prayer_reference,
                                'prayer_offset' => $rule->prayer_offset,
                                'prayer_display_text' => $rule->prayer_display_text,
                            ]);

                            $existingStarts->put($startKey, $startKey);
                            $created++;
                        }
                    }
                }

                $cursor->addDay();
                $safetyCounter++;

                if ($safetyCounter > 1800) {
                    break;
                }
            }
        });

        $rule->forceFill([
            'generated_until' => $cursor->copy()->subDay()->toDateString(),
        ])->save();

        if ($created > 0 && $projectAfterSync) {
            $this->projector->project($event->fresh());
        }

        return $created;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsertManualSession(Event $event, array $data, ?EventSession $session = null): EventSession
    {
        $session ??= new EventSession;
        $existingRecurrenceRuleId = $session->exists ? $session->recurrence_rule_id : null;
        $existingIsGenerated = $session->exists && (bool) $session->is_generated;

        $session->fill([
            'event_id' => $event->id,
            'recurrence_rule_id' => Arr::get($data, 'recurrence_rule_id', $existingRecurrenceRuleId),
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'timezone' => $data['timezone'] ?? ($event->timezone ?: 'Asia/Kuala_Lumpur'),
            'status' => $data['status'] ?? SessionStatus::Scheduled->value,
            'is_generated' => Arr::has($data, 'is_generated')
                ? (bool) Arr::get($data, 'is_generated')
                : $existingIsGenerated,
            'capacity' => Arr::get($data, 'capacity'),
            'timing_mode' => $data['timing_mode'] ?? TimingMode::Absolute->value,
            'prayer_reference' => Arr::get($data, 'prayer_reference'),
            'prayer_offset' => Arr::get($data, 'prayer_offset'),
            'prayer_display_text' => Arr::get($data, 'prayer_display_text'),
        ]);

        $session->save();

        $this->remoderateIfApproved($event, 'schedule_changed', 'Schedule session updated.');

        return $session;
    }

    public function cancelSession(EventSession $session): void
    {
        $session->update([
            'status' => SessionStatus::Cancelled->value,
        ]);

        $event = $session->event;

        if ($event instanceof Event) {
            $this->remoderateIfApproved($event->fresh(), 'schedule_changed', 'Session cancelled.');
        }
    }

    public function pauseSeries(Event $event): void
    {
        $event->recurrenceRules()->update([
            'status' => ScheduleState::Paused->value,
        ]);

        $event->sessions()
            ->where('starts_at', '>=', now($event->timezone ?: 'Asia/Kuala_Lumpur'))
            ->where('status', SessionStatus::Scheduled->value)
            ->update(['status' => SessionStatus::Paused->value]);

        $event->update([
            'schedule_state' => ScheduleState::Paused->value,
            'is_active' => false,
        ]);

        $this->projector->project($event->fresh());
        $this->remoderateIfApproved($event->fresh(), 'schedule_changed', 'Recurring schedule paused.');
    }

    public function resumeSeries(Event $event): void
    {
        $event->recurrenceRules()->update([
            'status' => ScheduleState::Active->value,
        ]);

        $event->sessions()
            ->where('starts_at', '>=', now($event->timezone ?: 'Asia/Kuala_Lumpur'))
            ->where('status', SessionStatus::Paused->value)
            ->update(['status' => SessionStatus::Scheduled->value]);

        foreach ($event->recurrenceRules as $rule) {
            if ($rule->status === ScheduleState::Active) {
                $this->syncRecurringSessions($event, $rule, false);
            }
        }

        $event->update([
            'schedule_state' => ScheduleState::Active->value,
            'is_active' => true,
        ]);

        $this->projector->project($event->fresh());
        $this->remoderateIfApproved($event->fresh(), 'schedule_changed', 'Recurring schedule resumed.');
    }

    public function cancelSeries(Event $event): void
    {
        $event->recurrenceRules()->update([
            'status' => ScheduleState::Cancelled->value,
        ]);

        $event->sessions()
            ->where('starts_at', '>=', now($event->timezone ?: 'Asia/Kuala_Lumpur'))
            ->whereIn('status', [SessionStatus::Scheduled->value, SessionStatus::Paused->value])
            ->update(['status' => SessionStatus::Cancelled->value]);

        $event->update([
            'schedule_state' => ScheduleState::Cancelled->value,
            'is_active' => false,
        ]);

        $this->projector->project($event->fresh());
        $this->remoderateIfApproved($event->fresh(), 'schedule_changed', 'Recurring schedule cancelled.');
    }

    protected function matchesRuleDate(Carbon $date, EventRecurrenceRule $rule, Carbon $startDate): bool
    {
        if ($date->lt($startDate)) {
            return false;
        }

        $interval = max(1, (int) ($rule->interval ?? 1));

        return match ($rule->frequency) {
            RecurrenceFrequency::Daily => $startDate->diffInDays($date) % $interval === 0,
            RecurrenceFrequency::Weekly => $this->matchesWeekly($date, $rule, $startDate, $interval),
            RecurrenceFrequency::Monthly => $this->matchesMonthly($date, $rule, $startDate, $interval),
            default => false,
        };
    }

    protected function matchesWeekly(Carbon $date, EventRecurrenceRule $rule, Carbon $startDate, int $interval): bool
    {
        $weeksFromStart = (int) floor($startDate->diffInDays($date) / 7);

        if ($weeksFromStart % $interval !== 0) {
            return false;
        }

        $weekdays = collect($rule->by_weekdays ?? [$startDate->dayOfWeek])
            ->map(fn (mixed $day): int => (int) $day)
            ->unique()
            ->values()
            ->all();

        return in_array($date->dayOfWeek, $weekdays, true);
    }

    protected function matchesMonthly(Carbon $date, EventRecurrenceRule $rule, Carbon $startDate, int $interval): bool
    {
        $monthDiff = (($date->year - $startDate->year) * 12) + ($date->month - $startDate->month);

        if ($monthDiff < 0 || $monthDiff % $interval !== 0) {
            return false;
        }

        $targetDay = (int) ($rule->by_month_day ?: $startDate->day);
        $expectedDay = min($targetDay, $date->daysInMonth);

        return $date->day === $expectedDay;
    }

    protected function resolveSessionStart(Event $event, EventRecurrenceRule $rule, Carbon $date, string $timezone): ?Carbon
    {
        if ($rule->timing_mode === TimingMode::PrayerRelative) {
            if (! $rule->prayer_reference instanceof PrayerReference || ! $rule->prayer_offset instanceof PrayerOffset) {
                return null;
            }

            $coords = $event->prayer_coordinates ?? ['lat' => 3.1390, 'lng' => 101.6869];

            return $this->prayerTimeService->calculateStartTime(
                eventDate: $date,
                prayer: $rule->prayer_reference,
                offset: $rule->prayer_offset,
                latitude: (float) $coords['lat'],
                longitude: (float) $coords['lng'],
                timezone: $timezone,
            );
        }

        $startsTime = $rule->starts_time instanceof Carbon
            ? $rule->starts_time->format('H:i:s')
            : ((string) ($rule->starts_time ?: '20:00:00'));

        [$hour, $minute, $second] = array_map(intval(...), explode(':', str_pad($startsTime, 8, ':00')));

        return $date->copy()->setTimezone($timezone)->setTime($hour, $minute, $second);
    }

    protected function resolveSessionEnd(EventRecurrenceRule $rule, Carbon $startsAt, string $timezone): Carbon
    {
        $endsTime = $rule->ends_time instanceof Carbon
            ? $rule->ends_time->format('H:i:s')
            : ((string) ($rule->ends_time ?: ''));

        if ($endsTime === '') {
            return $startsAt->copy()->addHours(2);
        }

        [$hour, $minute, $second] = array_map(intval(...), explode(':', str_pad($endsTime, 8, ':00')));
        $endsAt = $startsAt->copy()->setTimezone($timezone)->setTime($hour, $minute, $second);

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            return $endsAt->addDay();
        }

        return $endsAt;
    }

    protected function remoderateIfApproved(Event $event, string $reasonCode, string $note): void
    {
        if (! $event->status instanceof Approved) {
            return;
        }

        $this->moderationService->remoderate($event, auth()->user(), $note, $reasonCode);
    }
}
