<?php

namespace App\Http\Controllers\Api;

use App\Actions\Events\RegisterForEventAction;
use App\Data\Api\EventRegistration\EventRegistrationData;
use App\Data\Api\EventRegistration\EventRegistrationStatusData;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Dedoc\Scramble\Attributes\Endpoint;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

#[Group('Event Registration', 'Public event registration submission and authenticated registration-status endpoints.')]
class EventRegistrationController extends Controller
{
    #[Endpoint(
        title: 'Register for an event',
        description: 'Creates a registration for the target event using guest contact details or the current authenticated user context.',
    )]
    public function store(Request $request, Event $event, RegisterForEventAction $registerForEventAction): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $user = $request->user();

        $registration = $registerForEventAction->handle(
            $event,
            $validated,
            $user instanceof User ? $user : null,
            $request,
        );

        return response()->json([
            'data' => EventRegistrationData::fromModel($registration)->toArray(),
            'meta' => [
                'request_id' => $request->header('X-Request-ID', (string) Str::uuid()),
            ],
        ], 201);
    }

    #[Endpoint(
        title: 'Get the current user registration status for an event',
        description: 'Returns the current authenticated user\'s registration state for the target event.',
    )]
    public function status(Request $request, Event $event): JsonResponse
    {
        $registration = Registration::query()
            ->where('event_id', $event->id)
            ->where('user_id', $this->currentUser($request)->id)
            ->where('status', '!=', 'cancelled')
            ->latest('created_at')
            ->first();

        $registrationData = $registration instanceof Registration
            ? EventRegistrationData::fromModel($registration)
            : null;

        return response()->json([
            'data' => EventRegistrationStatusData::fromNullableRegistration($registrationData)->toArray(),
            'meta' => [
                'request_id' => $request->header('X-Request-ID', (string) Str::uuid()),
            ],
        ]);
    }

    protected function currentUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
