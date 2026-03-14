<?php

namespace App\Actions\Events;

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsAction;

class RemoveEventInterestAction
{
    use AsAction;

    /**
     * @return array{deleted: bool, interests_count: int}
     */
    public function handle(string $eventId, User $user): array
    {
        return DB::transaction(function () use ($eventId, $user): array {
            $event = Event::query()
                ->whereKey($eventId)
                ->lockForUpdate()
                ->first();

            $deletedRows = DB::table('event_interests')
                ->where('user_id', $user->getKey())
                ->where('event_id', $eventId)
                ->delete();

            if ($deletedRows === 0) {
                return ['deleted' => false, 'interests_count' => 0];
            }

            return [
                'deleted' => true,
                'interests_count' => $event instanceof Event ? $this->syncInterestsCount($eventId) : 0,
            ];
        }, 3);
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
