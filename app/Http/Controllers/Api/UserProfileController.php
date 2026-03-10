<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserProfileController extends Controller
{
    /**
     * Get the authenticated user's full profile.
     */
    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $user->loadCount([
            'savedEvents',
            'interestedEvents',
            'goingEvents',
            'registrations',
        ]);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'timezone' => $user->timezone,
                'email_verified_at' => $user->email_verified_at,
                'phone_verified_at' => $user->phone_verified_at,
                'saved_events_count' => $user->saved_events_count,
                'interested_events_count' => $user->interested_events_count,
                'going_events_count' => $user->going_events_count,
                'registrations_count' => $user->registrations_count,
            ],
        ]);
    }

    /**
     * Update the authenticated user's profile.
     */
    public function update(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'timezone' => ['sometimes', 'nullable', 'string', 'max:100'],
        ]);

        $user->update($validated);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'timezone' => $user->timezone,
                'email_verified_at' => $user->email_verified_at,
            ],
            'message' => 'Profile updated successfully.',
        ]);
    }

    /**
     * Update the authenticated user's password.
     */
    public function updatePassword(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        /** @var User $user */
        $user = $request->user();

        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'message' => 'The current password is incorrect.',
                'errors' => ['current_password' => ['The current password is incorrect.']],
            ], 422);
        }

        $user->update(['password' => Hash::make($request->password)]);

        // Revoke all other tokens for security
        $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();

        return response()->json(['message' => 'Password updated successfully.']);
    }

    /**
     * Get the user's followed speakers and institutions.
     */
    public function following(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $speakers = $user->followingSpeakers()->get()->map(function ($speaker): array {
            return [
                'id' => $speaker->id,
                'name' => $speaker->name,
                'formatted_name' => $speaker->formatted_name,
                'avatar_url' => $speaker->default_avatar_url,
                'type' => 'speaker',
            ];
        });

        $institutions = $user->followingInstitutions()->get()->map(function ($institution): array {
            return [
                'id' => $institution->id,
                'name' => $institution->name,
                'logo_url' => $institution->getFirstMediaUrl('logo', 'thumb') ?: null,
                'type' => 'institution',
            ];
        });

        return response()->json([
            'data' => [
                'speakers' => $speakers,
                'institutions' => $institutions,
            ],
        ]);
    }
}
