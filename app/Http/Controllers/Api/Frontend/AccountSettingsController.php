<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Models\Institution;
use App\Models\User;
use App\Services\Notifications\NotificationSettingsManager;
use DateTimeZone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AccountSettingsController extends FrontendController
{
    public function __construct(
        private readonly NotificationSettingsManager $notificationSettingsManager,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);

        return response()->json([
            'data' => [
                'profile' => $this->profileData($user),
            ],
            'meta' => [
                'request_id' => $this->requestId($request),
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $this->requireUser($request);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique(User::class, 'email')->ignore($user->getKey())],
            'phone' => ['nullable', 'string', Rule::unique(User::class, 'phone')->ignore($user->getKey())],
            'timezone' => ['nullable', 'string', Rule::in(DateTimeZone::listIdentifiers())],
            'daily_prayer_institution_id' => ['nullable', 'uuid'],
            'friday_prayer_institution_id' => ['nullable', 'uuid'],
        ]);

        $normalizedName = trim((string) ($validated['name'] ?? ''));
        $normalizedEmail = $this->normalizeOptionalString($validated['email'] ?? null);
        $normalizedPhone = $this->normalizeOptionalString($validated['phone'] ?? null);
        $normalizedTimezone = $this->normalizeOptionalString($validated['timezone'] ?? null);
        $dailyPrayerInstitutionId = $this->normalizeOptionalString($validated['daily_prayer_institution_id'] ?? null);
        $fridayPrayerInstitutionId = $this->normalizeOptionalString($validated['friday_prayer_institution_id'] ?? null);

        if ($normalizedEmail === null && $normalizedPhone === null) {
            throw ValidationException::withMessages([
                'email' => __('Email or phone is required.'),
                'phone' => __('Email or phone is required.'),
            ]);
        }

        $emailChanged = $normalizedEmail !== $user->email;
        $phoneChanged = $normalizedPhone !== $user->phone;

        $this->assertPrayerInstitutionSelectionAllowed(
            $dailyPrayerInstitutionId,
            $user->daily_prayer_institution_id,
            'daily_prayer_institution_id',
        );
        $this->assertPrayerInstitutionSelectionAllowed(
            $fridayPrayerInstitutionId,
            $user->friday_prayer_institution_id,
            'friday_prayer_institution_id',
        );

        $user->forceFill([
            'name' => $normalizedName,
            'email' => $normalizedEmail,
            'phone' => $normalizedPhone,
            'timezone' => $normalizedTimezone,
            'daily_prayer_institution_id' => $dailyPrayerInstitutionId,
            'friday_prayer_institution_id' => $fridayPrayerInstitutionId,
            'email_verified_at' => $emailChanged ? null : $user->email_verified_at,
            'phone_verified_at' => $phoneChanged ? null : $user->phone_verified_at,
        ])->save();

        $freshUser = $user->fresh() ?? $user;
        $this->notificationSettingsManager->syncProfileSettings($freshUser);

        if ($emailChanged) {
            $freshUser->sendEmailVerificationNotification();
        }

        return response()->json([
            'data' => [
                'profile' => $this->profileData($freshUser),
                'message' => __('Account settings updated.'),
            ],
            'meta' => [
                'request_id' => $this->requestId($request),
            ],
        ]);
    }

    /**
     * @return Builder<Institution>
     */
    private function selectablePrayerInstitutionQuery(): Builder
    {
        return Institution::query()
            ->active()
            ->where('status', 'verified');
    }

    private function assertPrayerInstitutionSelectionAllowed(?string $selectedInstitutionId, ?string $currentInstitutionId, string $errorKey): void
    {
        if ($selectedInstitutionId === null || $selectedInstitutionId === $currentInstitutionId) {
            return;
        }

        if ($this->selectablePrayerInstitutionQuery()->whereKey($selectedInstitutionId)->exists()) {
            return;
        }

        throw ValidationException::withMessages([
            $errorKey => __('Please select a valid active institution.'),
        ]);
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function profileData(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => (string) ($user->email ?? ''),
            'phone' => (string) ($user->phone ?? ''),
            'timezone' => (string) ($user->timezone ?? ''),
            'daily_prayer_institution_id' => (string) ($user->daily_prayer_institution_id ?? ''),
            'friday_prayer_institution_id' => (string) ($user->friday_prayer_institution_id ?? ''),
            'email_verified_at' => $this->optionalDateTimeString($user->email_verified_at),
            'phone_verified_at' => $this->optionalDateTimeString($user->phone_verified_at),
        ];
    }
}
