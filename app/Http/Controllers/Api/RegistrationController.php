<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RegistrationController extends Controller
{
    /**
     * Register the authenticated user for an event.
     */
    public function store(Request $request, string $eventIdentifier): JsonResponse
    {
        $event = Event::query()
            ->where(function ($query) use ($eventIdentifier): void {
                $query->where('id', $eventIdentifier)
                    ->orWhere('slug', $eventIdentifier);
            })
            ->where('is_active', true)
            ->whereIn('status', ['approved', 'pending'])
            ->where('visibility', 'public')
            ->firstOrFail();

        /** @var User $user */
        $user = $request->user();

        // Check if user already has a registration for this event
        $existingRegistration = Registration::query()
            ->where('event_id', $event->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingRegistration instanceof Registration) {
            return response()->json([
                'message' => 'You are already registered for this event.',
                'data' => $existingRegistration,
            ], 409);
        }

        $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'event_session_id' => ['sometimes', 'nullable', 'string', 'exists:event_sessions,id'],
        ]);

        $registration = Registration::query()->create([
            'event_id' => $event->id,
            'user_id' => $user->id,
            'event_session_id' => $request->input('event_session_id'),
            'name' => $request->input('name', $user->name),
            'email' => $user->email,
            'phone' => $request->input('phone', $user->phone),
            'status' => 'pending',
            'checkin_token' => Str::random(32),
        ]);

        return response()->json([
            'message' => 'Successfully registered for the event.',
            'data' => $registration,
        ], 201);
    }

    /**
     * Cancel (delete) a registration.
     */
    public function destroy(Request $request, string $registrationId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $registration = Registration::query()
            ->where('id', $registrationId)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $registration->delete();

        return response()->json(['message' => 'Registration cancelled successfully.']);
    }
}
