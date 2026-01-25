<?php

namespace App\States\EventStatus;

use A909M\FilamentStateFusion\Concerns\StateFusionInfo;
use A909M\FilamentStateFusion\Contracts\HasFilamentStateFusion;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class EventStatus extends State implements HasFilamentStateFusion
{
    use StateFusionInfo;

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Pending::class, Transitions\SubmitForModeration::class)
            ->allowTransition(Pending::class, Approved::class, Transitions\ApproveEvent::class)
            ->allowTransition(Pending::class, NeedsChanges::class, Transitions\RequestChanges::class)
            ->allowTransition(Pending::class, Rejected::class, Transitions\RejectEvent::class)
            ->allowTransition(NeedsChanges::class, Pending::class, Transitions\SubmitForModeration::class)
            ->allowTransition(Approved::class, Pending::class) // In case we need to re-moderate (e.g. sensitive changes)
            ->allowTransition(Rejected::class, Draft::class); // Allow recycling rejected events as drafts
    }
}
