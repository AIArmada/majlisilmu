<?php

namespace App\Http\Controllers\Api;

use App\Actions\Events\MarkEventInterestAction;
use App\Actions\Events\RemoveEventInterestAction;
use App\Enums\EventVisibility;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            ->whereIn('status', Event::PUBLIC_STATUSES)
            ->where('visibility', 'public')
            ->orderBy('starts_at')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $interestedEvents->items(),
            'meta' => [
                'request_id' => request()->header('X-Request-ID', (string) Str::uuid()),
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
    public function store(Request $request, MarkEventInterestAction $markEventInterestAction): JsonResponse
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
        if ((string) $event->status !== 'approved' || $event->visibility !== EventVisibility::Public) {
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

        /** @var User $user */
        $user = $request->user();
        $interestState = $markEventInterestAction->handle($event, $user, $request);

        if ($interestState['status'] === 'not_found') {
            return response()->json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Event not found.',
                ],
            ], 404);
        }

        if ($interestState['status'] === 'conflict') {
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
                'interests_count' => $interestState['interests_count'],
            ],
            'meta' => [
                'request_id' => $request->header('X-Request-ID', (string) Str::uuid()),
            ],
        ], 201);
    }

    /**
     * Remove interest.
     */
    public function destroy(Request $request, string $eventId, RemoveEventInterestAction $removeEventInterestAction): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $removeEventInterestAction->handle($eventId, $user);

        if (! $result['deleted']) {
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
                'interests_count' => $result['interests_count'],
            ],
            'meta' => [
                'request_id' => $request->header('X-Request-ID', (string) Str::uuid()),
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
                'request_id' => $request->header('X-Request-ID', (string) Str::uuid()),
            ],
        ]);
    }
}
