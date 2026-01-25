<?php

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

it('creates a user and social account on callback', function () {
    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-123',
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'avatar' => 'https://example.com/avatar.jpg',
    ]));

    $response = $this->get(route('socialite.callback', ['provider' => 'google']));

    $response->assertRedirect(route('home'));

    $this->assertDatabaseHas('users', [
        'email' => 'jane@example.com',
        'name' => 'Jane Doe',
    ]);

    $user = User::query()->where('email', 'jane@example.com')->first();

    expect($user)->not->toBeNull();

    $this->assertDatabaseHas('socialite', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-123',
        'avatar_url' => 'https://example.com/avatar.jpg',
    ]);
});

it('links a social account to an existing user', function () {
    $user = User::factory()->create([
        'email' => 'existing@example.com',
        'name' => 'Existing User',
    ]);

    Socialite::fake('google', (new SocialiteUser)->map([
        'id' => 'google-456',
        'name' => 'Existing User',
        'email' => 'existing@example.com',
        'avatar' => 'https://example.com/existing.jpg',
    ]));

    $response = $this->get(route('socialite.callback', ['provider' => 'google']));

    $response->assertRedirect(route('home'));

    expect(User::query()->where('email', 'existing@example.com')->count())
        ->toBe(1);

    $this->assertDatabaseHas('socialite', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => 'google-456',
        'avatar_url' => 'https://example.com/existing.jpg',
    ]);

    expect(SocialAccount::query()->where('user_id', $user->id)->count())
        ->toBe(1);
});
