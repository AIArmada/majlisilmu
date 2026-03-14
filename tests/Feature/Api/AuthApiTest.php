<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

it('registers an api user and returns a bearer token', function () {
    $response = $this->postJson(route('api.auth.register'), [
        'name' => 'Mobile User',
        'email' => 'mobile@example.test',
        'password' => 'password',
        'password_confirmation' => 'password',
        'device_name' => 'iPhone 15 Pro',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.email', 'mobile@example.test')
        ->assertJsonPath('data.access_token', fn (string $token) => filled($token));

    $token = $response->json('data.access_token');

    $this->withToken($token)
        ->getJson('/api/v1/user')
        ->assertOk()
        ->assertJsonPath('email', 'mobile@example.test');
});

it('logs in an api user with phone credentials and returns a bearer token', function () {
    User::factory()->create([
        'phone' => '+60111222333',
        'password' => 'password',
    ]);

    $response = $this->postJson(route('api.auth.login'), [
        'login' => '+60111222333',
        'password' => 'password',
        'device_name' => 'Pixel 9',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.token_type', 'Bearer')
        ->assertJsonPath('data.user.phone', '+60111222333')
        ->assertJsonPath('data.access_token', fn (string $token) => filled($token));
});

it('rejects invalid api login credentials', function () {
    User::factory()->create([
        'email' => 'mobile@example.test',
        'password' => 'password',
    ]);

    $this->postJson(route('api.auth.login'), [
        'login' => 'mobile@example.test',
        'password' => 'wrong-password',
        'device_name' => 'Pixel 9',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['login']);
});

it('revokes the current api token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Test Device');

    $this->withToken($token->plainTextToken)
        ->postJson(route('api.auth.logout'))
        ->assertOk()
        ->assertJsonPath('message', 'API token revoked successfully.');

    expect(PersonalAccessToken::query()->count())->toBe(0);
});

it('returns success for session-authenticated logout without a bearer token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('Other Device');

    $this->actingAs($user)
        ->postJson(route('api.auth.logout'))
        ->assertOk()
        ->assertJsonPath('message', 'API token revoked successfully.');

    expect(PersonalAccessToken::query()->whereKey($token->accessToken->id)->exists())->toBeTrue();
});
