<?php

namespace App\Http\Controllers\Api;

use App\Actions\Events\MarkEventGoingAction;
use App\Actions\Events\RemoveEventGoingAction;
use App\Data\Api\EventEngagement\EventEngagementListItemData;
use App\Data\Api\EventGoing\EventGoingResultData;
use App\Data\Api\EventGoing\EventGoingStateData;
use App\Enums\EventVisibility;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EventGoingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $goingEvents = $this->currentUser($request)
            ->goingEvents()
            ->with(['institution:id,name,slug', 'venue:id,name', 'speakers:id,name,slug'])
            ->whereIn('status', Event::PUBLIC_STATUSES)
            ->where('visibility', 'public')
            ->orderBy('starts_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => collect($goingEvents->items())
                ->map(fn (Event $event): array => EventEngagementListItemData::fromModel($event)->payload())
                ->all(),
            'meta' => [
                'request_id' => $request->header('X-Request-ID', (string) Str::uuid()),
                'pagination' => [
                    'page' => $goingEvents->currentPage(),
                    'per_page' => $goingEvents->perPage(),
                    'total' => $goingEvents->total(),
                ],
            ],
        ]);
    }

    public function show(Request $request, Event $event): JsonResponse
    {
        $isGoing = DB::table('event_attendees')
            ->where('user_id', $this->currentUser($request)->id)
            ->where('event_id', $event->id)
            ->exists();

        return response()->json([
            'data' => EventGoingStateData::fromState($isGoing, (int) ($event->going_count ?? 0))->toArray(),
            'meta' => [
                'request_id' => $request->header('X-Request-ID', (string) Str::uuid()),
            ],
        ]);
    }

    public function store(Request $request, Event $event, MarkEventGoingAction $markEventGoingAction): JsonResponse
    {
        if (! in_array((string) $event->status, Event::ENGAGEABLE_STATUSES, true) || $event->visibility !== EventVisibility::Public) {
            return response()->json([
                'error' => [
                    'code' => 'forbidden',
                    'message' => 'This event cannot be marked as going.',
                ],
            ], 403);
        }

        if ($event->starts_at !== null && $event->starts_at->isPast()) {
            return response()->json([
                'error' => [
                    'code' => 'forbidden',
                    'message' => 'Cannot mark going for past events.',
                ],
            ], 403);
        }

        $goingState = $markEventGoingAction->handle($event, $this->currentUser($request), $request);

        if ($goingState['status'] === 'conflict') {
            return response()->json([
                'error' => [
                    'code' => 'conflict',
                    'message' => 'You have already marked going for this event.',
                ],
            ], 409);
        }

        return response()->json([
            'data' => EventGoingResultData::fromOutcome('Going recorded successfully.', $goingState['going_count'])->toArray(),
            'meta' => [
                'request_id' => $request->header('X-Request-ID', (string) Str::uuid()),
            ],
        ], 201);
    }

    public function destroy(Request $request, Event $event, RemoveEventGoingAction $removeEventGoingAction): JsonResponse
    {
        $result = $removeEventGoingAction->handle($event->id, $this->currentUser($request));

        if (! $result['deleted']) {
            return response()->json([
                'error' => [
                    'code' => 'not_found',
                    'message' => 'Going record not found.',
                ],
            ], 404);
        }

        return response()->json([
            'data' => EventGoingResultData::fromOutcome('Going removed successfully.', $result['going_count'])->toArray(),
            'meta' => [
                'request_id' => $request->header('X-Request-ID', (string) Str::uuid()),
            ],
        ]);
    }

    protected function currentUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user->fresh() ?? $user;
    }
}
