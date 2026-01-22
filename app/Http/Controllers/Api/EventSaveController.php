<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventSaveController extends Controller
{
    /**
     * List all saved events for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $savedEvents = $request->user()
            ->savedEvents()
            ->with(['institution:id,name,slug', 'venue:id,name', 'speakers:id,name,slug'])
            ->where('status', 'approved')
            ->where('visibility', 'public')
            ->orderBy('starts_at')
            ->paginate($request->input('per_page', 20));

        return response()->json([
            'data' => $savedEvents->items(),
            'meta' => [
                'request_id' => request()->header('X-Request-ID', (string) \Illuminate\Support\Str::uuid()),
                'pagination' => [
                    'page' => $savedEvents->currentPage(),
                    'per_page' => $savedEvents->perPage(),
                    'total' => $savedEvents->total(),
                ],
            ],
        ]);
    }

    /**
     * Save an event (bookmark).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'uuid', 'exists:events,id'],
        ]);

        $event = Event::find($validated['event_id']);

        // Only allow saving public, approved events
        if ($event->status !== 'approved' || $event->visibility !== 'public') {
            return response()->json([
                'error' => [
                    'code' => 'forbidden',
                    'message' => 'This event cannot be saved.',
                ],
            ], 403);
        }

        // Check if already saved
        $exists = DB::table('event_saves')
            ->where('user_id', $request->user()->id)
            ->where('event_id', $validated['event_id'])
            ->exists();

        if ($exists) {
            return response()->json([
                'error' => [
                    'code' => 'conflict',
                    'message' => 'Event is already saved.',
                ],
            ], 409);
        }

        DB::table('event_saves')->insert([
            'user_id' => $request->user()->id,
            'event_id' => $validated['event_id'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Increment saves_count on event
        $event->increment('saves_count');

        return response()->json([
            'data' => [
                'message' => 'Event saved successfully.',
            ],
            'meta' => [
                'request_id' => request()->header('X-Request-ID', (string) \Illuminate\Support\Str::uuid()),
            ],
        ], 201);
    }

    /**
     * Remove a saved event (unbookmark).
     */
    public function destroy(Request $request, string $eventId): JsonResponse
    {
        $deleted = DB::table('event_saves')
            ->where('user_id', $request->user()->id)
            ->where('event_id', $eventId)
            ->delete();

        if (! $deleted) {
            return response()->json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Save not found.',
                ],
            ], 404);
        }

        // Decrement saves_count on event
        Event::where('id', $eventId)->decrement('saves_count');

        return response()->json([
            'data' => [
                'message' => 'Event unsaved successfully.',
            ],
            'meta' => [
                'request_id' => request()->header('X-Request-ID', (string) \Illuminate\Support\Str::uuid()),
            ],
        ]);
    }

    /**
     * Check if an event is saved.
     */
    public function show(Request $request, string $eventId): JsonResponse
    {
        $isSaved = DB::table('event_saves')
            ->where('user_id', $request->user()->id)
            ->where('event_id', $eventId)
            ->exists();

        return response()->json([
            'data' => [
                'is_saved' => $isSaved,
            ],
            'meta' => [
                'request_id' => request()->header('X-Request-ID', (string) \Illuminate\Support\Str::uuid()),
            ],
        ]);
    }
}
