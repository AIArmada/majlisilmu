<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventInterestController extends Controller
{
    /**
     * List all interested events for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $interestedEvents = $request->user()
            ->interestedEvents()
            ->with(['institution:id,name,slug', 'venue:id,name', 'speakers:id,name,slug'])
            ->where('status', 'approved')
            ->where('visibility', 'public')
            ->orderBy('starts_at')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $interestedEvents->items(),
            'meta' => [
                'request_id' => request()->header('X-Request-ID', (string) \Illuminate\Support\Str::uuid()),
                'pagination' => [
                    'page' => $interestedEvents->currentPage(),
                    'per_page' => $interestedEvents->perPage(),
                    'total' => $interestedEvents->total(),
                ],
            ],
        ]);
    }

    /**
     * Mark interest in an event.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'uuid', 'exists:events,id'],
        ]);

        $event = Event::query()->find($validated['event_id']);

        if (! $event) {
            return response()->json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Event not found.',
                ],
            ], 404);
        }

        // Only allow interest for public, approved events
        if ((string) $event->status !== 'approved' || $event->visibility !== \App\Enums\EventVisibility::Public) {
            return response()->json([
                'error' => [
                    'code' => 'forbidden',
                    'message' => 'This event cannot be marked as interested.',
                ],
            ], 403);
        }

        // Check if event has already passed
        if ($event->starts_at && $event->starts_at->isPast()) {
            return response()->json([
                'error' => [
                    'code' => 'forbidden',
                    'message' => 'Cannot mark interest for past events.',
                ],
            ], 403);
        }

        $interestState = DB::transaction(function () use ($event, $request): string {
            $lockedEvent = Event::query()
                ->whereKey($event->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedEvent) {
                return 'not_found';
            }

            $inserted = DB::table('event_interests')->insertOrIgnore([
                'user_id' => $request->user()->id,
                'event_id' => $lockedEvent->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                return 'conflict';
            }

            $this->syncInterestsCount($lockedEvent->id);

            return 'created';
        }, 3);

        if ($interestState === 'not_found') {
            return response()->json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Event not found.',
                ],
            ], 404);
        }

        if ($interestState === 'conflict') {
            return response()->json([
                'error' => [
                    'code' => 'conflict',
                    'message' => 'You have already marked interest in this event.',
                ],
            ], 409);
        }

        return response()->json([
            'data' => [
                'message' => 'Interest recorded successfully.',
                'interests_count' => (int) (Event::query()->whereKey($event->id)->value('interests_count') ?? 0),
            ],
            'meta' => [
                'request_id' => $request->header('X-Request-ID', (string) \Illuminate\Support\Str::uuid()),
            ],
        ], 201);
    }

    /**
     * Remove interest.
     */
    public function destroy(Request $request, string $eventId): JsonResponse
    {
        $interestsCount = DB::transaction(function () use ($eventId, $request): ?int {
            $event = Event::query()
                ->whereKey($eventId)
                ->lockForUpdate()
                ->first();

            $deletedRows = DB::table('event_interests')
                ->where('user_id', $request->user()->id)
                ->where('event_id', $eventId)
                ->delete();

            if ($deletedRows === 0) {
                return null;
            }

            if ($event) {
                $this->syncInterestsCount($eventId);
            }

            return (int) DB::table('event_interests')
                ->where('event_id', $eventId)
                ->count();
        }, 3);

        if ($interestsCount === null) {
            return response()->json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Interest not found.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => [
                'message' => 'Interest removed successfully.',
                'interests_count' => $interestsCount,
            ],
            'meta' => [
                'request_id' => $request->header('X-Request-ID', (string) \Illuminate\Support\Str::uuid()),
            ],
        ]);
    }

    /**
     * Check if user has marked interest in an event.
     */
    public function show(Request $request, string $eventId): JsonResponse
    {
        $isInterested = DB::table('event_interests')
            ->where('user_id', $request->user()->id)
            ->where('event_id', $eventId)
            ->exists();

        $event = Event::find($eventId);

        return response()->json([
            'data' => [
                'is_interested' => $isInterested,
                'interests_count' => $event ? $event->interests_count : 0,
            ],
            'meta' => [
                'request_id' => $request->header('X-Request-ID', (string) \Illuminate\Support\Str::uuid()),
            ],
        ]);
    }

    private function syncInterestsCount(string $eventId): void
    {
        $interestsCount = DB::table('event_interests')
            ->where('event_id', $eventId)
            ->count();

        Event::query()
            ->whereKey($eventId)
            ->update(['interests_count' => $interestsCount]);
    }
}
