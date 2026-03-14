<?php

namespace App\Http\Controllers\Api;

use App\Actions\Events\RecordEventCheckInAction;
use App\Actions\Events\ResolveEventCheckInStateAction;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\User;
use DateTimeInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class EventCheckInController extends Controller
{
    public function show(Request $request, Event $event, ResolveEventCheckInStateAction $resolveEventCheckInStateAction): JsonResponse
    {
        $user = $this->currentUser($request);
        $state = $resolveEventCheckInStateAction->handle($event->loadMissing('settings'), $user);

        $isCheckedIn = EventCheckin::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->exists();

        return response()->json([
            'data' => [
                'is_checked_in' => $isCheckedIn,
                'available' => $state['available'],
                'reason' => $state['reason'],
                'method' => $state['method'],
                'registration_id' => $state['registration_id'],
            ],
            'meta' => [
                'request_id' => $request->header('X-Request-ID', (string) Str::uuid()),
            ],
        ]);
    }

    public function store(
        Request $request,
        Event $event,
        ResolveEventCheckInStateAction $resolveEventCheckInStateAction,
        RecordEventCheckInAction $recordEventCheckInAction,
    ): JsonResponse {
        $user = $this->currentUser($request);
        $state = $resolveEventCheckInStateAction->handle($event->loadMissing('settings'), $user);

        if (! $state['available']) {
            return response()->json([
                'error' => [
                    'code' => 'forbidden',
                    'message' => (string) ($state['reason'] ?? 'Check-in is not available.'),
                ],
            ], 403);
        }

        $result = $recordEventCheckInAction->handle(
            $event,
            $user,
            $state['registration_id'],
            $state['method'],
            $request,
        );

        $statusCode = $result['status'] === 'created' ? 201 : 200;
        $message = $result['status'] === 'created'
            ? 'Check-in recorded successfully.'
            : 'You have already checked in for this event.';

        return response()->json([
            'message' => $message,
            'data' => [
                'status' => $result['status'],
                'checkin' => $this->checkinData($result['checkin']),
            ],
            'meta' => [
                'request_id' => $request->header('X-Request-ID', (string) Str::uuid()),
            ],
        ], $statusCode);
    }

    protected function currentUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user->fresh() ?? $user;
    }

    /**
     * @return array<string, mixed>
     */
    protected function checkinData(EventCheckin $checkin): array
    {
        return [
            'id' => $checkin->id,
            'event_id' => $checkin->event_id,
            'user_id' => $checkin->user_id,
            'registration_id' => $checkin->registration_id,
            'method' => $checkin->method,
            'checked_in_at' => $checkin->checked_in_at instanceof DateTimeInterface
                ? $checkin->checked_in_at->format(DateTimeInterface::ATOM)
                : null,
        ];
    }
}
