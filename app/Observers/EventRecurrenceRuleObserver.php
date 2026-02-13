<?php

namespace App\Observers;

use App\Enums\ScheduleState;
use App\Models\EventRecurrenceRule;
use App\Services\EventScheduleGeneratorService;
use App\Services\EventScheduleProjectorService;

class EventRecurrenceRuleObserver
{
    public function __construct(
        protected EventScheduleGeneratorService $generator,
        protected EventScheduleProjectorService $projector,
    ) {}

    public function created(EventRecurrenceRule $rule): void
    {
        if ($rule->status === ScheduleState::Active && $rule->event !== null) {
            $this->generator->syncRecurringSessions($rule->event, $rule);
        }
    }

    public function updated(EventRecurrenceRule $rule): void
    {
        if ($rule->wasChanged(['generated_until'])) {
            return;
        }

        if ($rule->event === null) {
            return;
        }

        if ($rule->status === ScheduleState::Active) {
            $this->generator->syncRecurringSessions($rule->event, $rule, false);
        }

        $this->projector->project($rule->event->fresh());
    }

    public function deleting(EventRecurrenceRule $rule): void
    {
        $rule->sessions()->delete();
    }
}
