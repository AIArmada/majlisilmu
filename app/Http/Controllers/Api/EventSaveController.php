<?php

namespace App\Http\Controllers\Api;

use App\Actions\Events\SaveEventAction;
use App\Actions\Events\UnsaveEventAction;
use App\Data\Api\EventEngagement\EventEngagementListItemData;
use App\Data\Api\EventSave\EventSaveResultData;
use App\Data\Api\EventSave\EventSaveStateData;
use App\Enums\EventVisibility;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

#[Group('Event Save', 'Authenticated endpoints for listing, creating, removing, and checking saved public events.')]
class EventSaveController extends Controller
{
    /**
     * List all saved events for the authenticated user.
     */
    #[Endpoint(
        title: 'List saved events',
        description: 'Returns the authenticated user\'s saved public events with pagination metadata.',
    )]
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
            'data' => collect($savedEvents->items())
                ->map(fn (Event $event): array => EventEngagementListItemData::fromModel($event)->payload())
                ->all(),
            'meta' => [
                'request_id' => request()->header('X-Request-ID', (string) Str::uuid()),
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
    #[Endpoint(
        title: 'Save an event',
        description: 'Creates a saved-event bookmark for the authenticated user when the target event is allowed to be saved.',
    )]
    public function store(Request $request, SaveEventAction $saveEventAction): JsonResponse
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
        if ((string) $event->status !== 'approved' || $event->visibility !== EventVisibility::Public) {
            return response()->json([
                'error' => [
                    'code' => 'forbidden',
                    'message' => 'This event cannot be saved.',
                ],
            ], 403);
        }

        /** @var User $user */
        $user = $request->user();
        $savedState = $saveEventAction->handle($event, $user, $request);

        if ($savedState['status'] === 'not_found') {
            return response()->json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Event not found.',
                ],
            ], 404);
        }

        if ($savedState['status'] === 'conflict') {
            return response()->json([
                'error' => [
                    'code' => 'conflict',
                    'message' => 'Event is already saved.',
                ],
            ], 409);
        }

        return response()->json([
            'data' => EventSaveResultData::fromMessage('Event saved successfully.')->toArray(),
            'meta' => [
                'request_id' => request()->header('X-Request-ID', (string) Str::uuid()),
            ],
        ], 201);
    }

    /**
     * Remove a saved event (unbookmark).
     */
    #[Endpoint(
        title: 'Remove a saved event',
        description: 'Deletes a saved-event bookmark for the authenticated user.',
    )]
    public function destroy(Request $request, string $eventId, UnsaveEventAction $unsaveEventAction): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $result = $unsaveEventAction->handle($eventId, $user);

        if (! $result['deleted']) {
            return response()->json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Save not found.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => EventSaveResultData::fromMessage('Event unsaved successfully.')->toArray(),
            'meta' => [
                'request_id' => request()->header('X-Request-ID', (string) Str::uuid()),
            ],
        ]);
    }

    /**
     * Check if an event is saved.
     */
    #[Endpoint(
        title: 'Check saved-event state',
        description: 'Returns whether the authenticated user has already saved the target public event.',
    )]
    public function show(Request $request, string $eventId): JsonResponse
    {
        $isSaved = DB::table('event_saves')
            ->where('user_id', $request->user()->id)
            ->where('event_id', $eventId)
            ->exists();

        return response()->json([
            'data' => EventSaveStateData::fromState($isSaved)->toArray(),
            'meta' => [
                'request_id' => request()->header('X-Request-ID', (string) Str::uuid()),
            ],
        ]);
    }
}
