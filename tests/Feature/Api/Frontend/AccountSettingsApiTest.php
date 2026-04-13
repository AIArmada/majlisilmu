<?php

use App\Models\Institution;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('normalizes missing optional account settings fields as empty strings', function () {
    $user = User::factory()->create([
        'name' => 'Blank Profile User',
        'email' => 'blank-profile@example.test',
        'phone' => null,
        'timezone' => null,
        'daily_prayer_institution_id' => null,
        'friday_prayer_institution_id' => null,
        'email_verified_at' => null,
        'phone_verified_at' => null,
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(route('api.client.account-settings.show'));

    $response->assertOk();

    expect($response->json('data.profile'))->toEqual([
        'name' => 'Blank Profile User',
        'email' => 'blank-profile@example.test',
        'phone' => '',
        'timezone' => '',
        'daily_prayer_institution_id' => '',
        'friday_prayer_institution_id' => '',
        'email_verified_at' => null,
        'phone_verified_at' => null,
    ]);
});

it('shows the authenticated account settings profile contract', function () {
    $dailyInstitution = Institution::factory()->create([
        'name' => 'Masjid Harian API',
        'status' => 'verified',
        'is_active' => true,
    ]);
    $fridayInstitution = Institution::factory()->create([
        'name' => 'Masjid Jumaat API',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'API Settings User',
        'email' => 'settings@example.test',
        'phone' => '+60112223344',
        'timezone' => 'Asia/Kuala_Lumpur',
        'daily_prayer_institution_id' => $dailyInstitution->id,
        'friday_prayer_institution_id' => $fridayInstitution->id,
        'email_verified_at' => now()->subHour()->startOfSecond(),
        'phone_verified_at' => now()->subMinutes(20)->startOfSecond(),
    ]);

    Sanctum::actingAs($user);

    $response = $this->getJson(route('api.client.account-settings.show'));

    $response->assertOk()
        ->assertJsonPath('meta.request_id', fn (string $requestId) => filled($requestId));

    expect($response->json('data.profile'))->toEqual([
        'name' => 'API Settings User',
        'email' => 'settings@example.test',
        'phone' => '+60112223344',
        'timezone' => 'Asia/Kuala_Lumpur',
        'daily_prayer_institution_id' => $dailyInstitution->id,
        'friday_prayer_institution_id' => $fridayInstitution->id,
        'email_verified_at' => $user->fresh()->email_verified_at?->toIso8601String(),
        'phone_verified_at' => $user->fresh()->phone_verified_at?->toIso8601String(),
    ]);
});

it('returns the updated account settings profile payload after saving', function () {
    $dailyInstitution = Institution::factory()->create([
        'name' => 'Masjid Harian Baru',
        'status' => 'verified',
        'is_active' => true,
    ]);
    $fridayInstitution = Institution::factory()->create([
        'name' => 'Masjid Jumaat Baru',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'name' => 'Original User',
        'email' => 'original@example.test',
        'phone' => '+60117778899',
        'timezone' => 'UTC',
        'email_verified_at' => now()->subDay()->startOfSecond(),
        'phone_verified_at' => now()->subHours(12)->startOfSecond(),
    ]);

    Sanctum::actingAs($user);

    $response = $this->putJson(route('api.client.account-settings.update'), [
        'name' => 'Updated API User',
        'email' => 'original@example.test',
        'phone' => '+60117778899',
        'timezone' => 'Asia/Kuala_Lumpur',
        'daily_prayer_institution_id' => $dailyInstitution->id,
        'friday_prayer_institution_id' => $fridayInstitution->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.message', __('Account settings updated.'))
        ->assertJsonPath('meta.request_id', fn (string $requestId) => filled($requestId));

    $user->refresh();

    expect($response->json('data.profile'))->toEqual([
        'name' => 'Updated API User',
        'email' => 'original@example.test',
        'phone' => '+60117778899',
        'timezone' => 'Asia/Kuala_Lumpur',
        'daily_prayer_institution_id' => $dailyInstitution->id,
        'friday_prayer_institution_id' => $fridayInstitution->id,
        'email_verified_at' => $user->email_verified_at?->toIso8601String(),
        'phone_verified_at' => $user->phone_verified_at?->toIso8601String(),
    ]);

    expect($user->name)->toBe('Updated API User')
        ->and($user->timezone)->toBe('Asia/Kuala_Lumpur')
        ->and($user->daily_prayer_institution_id)->toBe($dailyInstitution->id)
        ->and($user->friday_prayer_institution_id)->toBe($fridayInstitution->id);
});
