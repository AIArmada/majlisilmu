<?php

namespace App\States\EventStatus;

use A909M\FilamentStateFusion\Concerns\StateFusionInfo;
use A909M\FilamentStateFusion\Contracts\HasFilamentStateFusion;
use App\Models\Event;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

/**
 * @extends State<Event>
 *
 * @implements HasFilamentStateFusion<Event>
 *
 * @use StateFusionInfo<Event>
 */
abstract class EventStatus extends State implements HasFilamentStateFusion
{
    /** @use StateFusionInfo<Event> */
    use StateFusionInfo;

    #[\Override]
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            // Submission flow
            ->allowTransition(Draft::class, Pending::class, Transitions\SubmitForModeration::class)
            ->allowTransition(NeedsChanges::class, Pending::class, Transitions\SubmitForModeration::class)
            // Moderation decisions (from Pending)
            ->allowTransition(Pending::class, Approved::class, Transitions\ApproveEvent::class)
            ->allowTransition(Pending::class, NeedsChanges::class, Transitions\RequestChanges::class)
            ->allowTransition(Pending::class, Rejected::class, Transitions\RejectEvent::class)
            // Re-moderation (Approved back to Pending)
            ->allowTransition(Approved::class, Pending::class, Transitions\RemoderateEvent::class)
            // Reconsider rejected events (back to Pending for review)
            ->allowTransition(Rejected::class, Pending::class, Transitions\ReconsiderEvent::class)
            // Revert to draft (from any terminal state)
            ->allowTransition(Rejected::class, Draft::class, Transitions\RevertToDraft::class)
            ->allowTransition(NeedsChanges::class, Draft::class, Transitions\RevertToDraft::class);
    }
}
