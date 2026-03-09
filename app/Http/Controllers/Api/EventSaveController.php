<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Event;
use App\Services\DawahShare\DawahShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EventSaveController extends Controller
{
    public function __construct(
        private readonly DawahShareService $dawahShareService
    ) {}

    /**
     * List all saved events for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $savedEvents = $request->user()
            ->savedEvents()
            ->with(['institution:id,name,slug', 'venue:id,name', 'speakers:id,name,slug'])
            ->whereIn('status', Event::PUBLIC_STATUSES)
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

        $event = Event::query()->find($validated['event_id']);

        if (! $event) {
            return response()->json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Event not found.',
                ],
            ], 404);
        }

        // Only allow saving public, approved events
        if ((string) $event->status !== 'approved' || $event->visibility !== \App\Enums\EventVisibility::Public) {
            return response()->json([
                'error' => [
                    'code' => 'forbidden',
                    'message' => 'This event cannot be saved.',
                ],
            ], 403);
        }

        $savedState = DB::transaction(function () use ($event, $request): string {
            $lockedEvent = Event::query()
                ->whereKey($event->id)
                ->lockForUpdate()
                ->first();

            if (! $lockedEvent) {
                return 'not_found';
            }

            $inserted = DB::table('event_saves')->insertOrIgnore([
                'user_id' => $request->user()->id,
                'event_id' => $lockedEvent->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            if ($inserted === 0) {
                return 'conflict';
            }

            $this->syncSavesCount($lockedEvent->id);

            return 'created';
        }, 3);

        if ($savedState === 'not_found') {
            return response()->json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Event not found.',
                ],
            ], 404);
        }

        if ($savedState === 'conflict') {
            return response()->json([
                'error' => [
                    'code' => 'conflict',
                    'message' => 'Event is already saved.',
                ],
            ], 409);
        }

        /** @var User $user */
        $user = $request->user();

        $this->dawahShareService->recordOutcome(
            type: \App\Enums\DawahShareOutcomeType::EventSave,
            outcomeKey: 'event_save:user:'.$user->id.':event:'.$event->id,
            subject: $event,
            actor: $user,
            request: $request,
            metadata: [
                'event_id' => $event->id,
            ],
        );

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
        $deleted = DB::transaction(function () use ($eventId, $request): int {
            $event = Event::query()
                ->whereKey($eventId)
                ->lockForUpdate()
                ->first();

            $deletedRows = DB::table('event_saves')
                ->where('user_id', $request->user()->id)
                ->where('event_id', $eventId)
                ->delete();

            if ($deletedRows === 0) {
                return 0;
            }

            if ($event) {
                $this->syncSavesCount($eventId);
            }

            return $deletedRows;
        }, 3);

        if (! $deleted) {
            return response()->json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Save not found.',
                ],
            ], 404);
        }

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

    private function syncSavesCount(string $eventId): void
    {
        $savesCount = DB::table('event_saves')
            ->where('event_id', $eventId)
            ->count();

        Event::query()
            ->whereKey($eventId)
            ->update(['saves_count' => $savesCount]);
    }
}
