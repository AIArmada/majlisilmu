<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('can login with valid credentials', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'password123',
        'device_name' => 'test-device',
    ]);

    $response->assertSuccessful()
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email'],
        ]);
});

it('rejects login with invalid credentials', function () {
    $user = User::factory()->create([
        'password' => bcrypt('password123'),
    ]);

    $response = $this->postJson('/api/v1/auth/login', [
        'email' => $user->email,
        'password' => 'wrongpassword',
        'device_name' => 'test-device',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('can register a new user', function () {
    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Ahmad Bin Ali',
        'email' => 'ahmad@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'test-device',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'token',
            'user' => ['id', 'name', 'email'],
        ]);

    $this->assertDatabaseHas(User::class, ['email' => 'ahmad@example.com']);
});

it('rejects registration with duplicate email', function () {
    $existing = User::factory()->create();

    $response = $this->postJson('/api/v1/auth/register', [
        'name' => 'Someone',
        'email' => $existing->email,
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'device_name' => 'test-device',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('can logout and revoke token', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertSuccessful()
        ->assertJson(['message' => 'Logged out successfully.']);
});

it('cannot logout without authentication', function () {
    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertUnauthorized();
});
