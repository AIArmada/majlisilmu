<?php

namespace App\Actions\Events;

use App\Enums\DawahShareOutcomeType;
use App\Models\Event;
use App\Models\User;
use App\Services\ShareTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class MarkEventInterestAction
{
    use AsAction;

    public function __construct(
        private readonly ShareTrackingService $shareTrackingService,
    ) {}

    /**
     * @return array{status: 'created'|'conflict'|'not_found', interests_count: int}
     */
    public function handle(Event $event, User $user, Request $request): array
    {
        $interestState = DB::transaction(function () use ($event, $user): array {
            $lockedEvent = Event::query()
                ->whereKey($event->getKey())
                ->lockForUpdate()
                ->first();

            if (! $lockedEvent instanceof Event) {
                return ['status' => 'not_found', 'interests_count' => 0];
            }

            $inserted = DB::table('event_interests')->insertOrIgnore([
                'user_id' => $user->getKey(),
                'event_id' => $lockedEvent->getKey(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                return [
                    'status' => 'conflict',
                    'interests_count' => $this->syncInterestsCount((string) $lockedEvent->getKey()),
                ];
            }

            return [
                'status' => 'created',
                'interests_count' => $this->syncInterestsCount((string) $lockedEvent->getKey()),
            ];
        }, 3);

        if ($interestState['status'] === 'created') {
            $this->shareTrackingService->recordOutcome(
                type: DawahShareOutcomeType::EventInterest,
                outcomeKey: 'event_interest:user:'.$user->getKey().':event:'.$event->getKey(),
                subject: $event,
                actor: $user,
                request: $request,
                metadata: [
                    'event_id' => $event->getKey(),
                ],
            );
        }

        return $interestState;
    }

    private function syncInterestsCount(string $eventId): int
    {
        $interestsCount = (int) DB::table('event_interests')
            ->where('event_id', $eventId)
            ->count();

        Event::query()
            ->whereKey($eventId)
            ->update(['interests_count' => $interestsCount]);

        return $interestsCount;
    }
}
