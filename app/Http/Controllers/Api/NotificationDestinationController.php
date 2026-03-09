<?php

namespace App\Http\Controllers\Api;

use App\Enums\NotificationChannel;
use App\Enums\NotificationDestinationStatus;
use App\Http\Controllers\Controller;
use App\Models\NotificationDestination;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationDestinationController extends Controller
{
    public function storePush(Request $request): JsonResponse
    {
        $validated = $this->validatePushPayload($request);
        $user = $this->currentUser($request);

        $destination = NotificationDestination::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'channel' => NotificationChannel::Push->value,
                'address' => $validated['installation_id'],
            ],
            [
                'external_id' => $validated['fcm_token'],
                'status' => NotificationDestinationStatus::Active->value,
                'is_primary' => false,
                'verified_at' => now(),
                'meta' => $this->pushMeta($validated),
            ],
        );

        return response()->json([
            'message' => __('notifications.api.push_registered'),
            'data' => $this->pushDestinationData($destination),
        ], 201);
    }

    public function updatePush(Request $request, string $installation): JsonResponse
    {
        $validated = $this->validatePushPayload($request, $installation);
        $user = $this->currentUser($request);

        $destination = $user->notificationDestinations()
            ->where('channel', NotificationChannel::Push->value)
            ->where('address', $installation)
            ->firstOrFail();

        $destination->forceFill([
            'external_id' => $validated['fcm_token'],
            'status' => NotificationDestinationStatus::Active->value,
            'verified_at' => now(),
            'meta' => $this->pushMeta($validated),
        ])->save();

        return response()->json([
            'message' => __('notifications.api.push_updated'),
            'data' => $this->pushDestinationData($destination->fresh()),
        ]);
    }

    public function destroyPush(Request $request, string $installation): \Illuminate\Http\Response
    {
        $this->currentUser($request)
            ->notificationDestinations()
            ->where('channel', NotificationChannel::Push->value)
            ->where('address', $installation)
            ->delete();

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    protected function validatePushPayload(Request $request, ?string $installation = null): array
    {
        return $request->validate([
            'installation_id' => [
                Rule::requiredIf($installation === null),
                'string',
                'max:255',
            ],
            'platform' => ['required', Rule::in(['ios', 'android'])],
            'fcm_token' => ['required', 'string', 'max:500'],
            'app_version' => ['nullable', 'string', 'max:50'],
            'device_label' => ['nullable', 'string', 'max:255'],
            'locale' => ['nullable', Rule::in(array_keys(config('app.supported_locales', [])))],
            'timezone' => ['nullable', Rule::in(\DateTimeZone::listIdentifiers())],
            'last_seen_at' => ['nullable', 'date'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function pushMeta(array $validated): array
    {
        return [
            'platform' => (string) $validated['platform'],
            'app_version' => (string) ($validated['app_version'] ?? ''),
            'device_label' => (string) ($validated['device_label'] ?? ''),
            'locale' => (string) ($validated['locale'] ?? ''),
            'timezone' => (string) ($validated['timezone'] ?? ''),
            'last_seen_at' => isset($validated['last_seen_at']) ? (string) $validated['last_seen_at'] : now()->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function pushDestinationData(NotificationDestination $destination): array
    {
        $verifiedAt = $destination->verified_at;

        return [
            'id' => $destination->id,
            'installation_id' => $destination->address,
            'platform' => (string) data_get($destination->meta, 'platform', ''),
            'device_label' => (string) data_get($destination->meta, 'device_label', ''),
            'app_version' => (string) data_get($destination->meta, 'app_version', ''),
            'locale' => (string) data_get($destination->meta, 'locale', ''),
            'timezone' => (string) data_get($destination->meta, 'timezone', ''),
            'last_seen_at' => (string) data_get($destination->meta, 'last_seen_at', ''),
            'verified_at' => $verifiedAt instanceof CarbonInterface ? $verifiedAt->toIso8601String() : null,
        ];
    }

    protected function currentUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user->fresh() ?? $user;
    }
}
