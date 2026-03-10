<?php

use App\Livewire\Pages\Dashboard\AccountSettings;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the dedicated account settings page for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->withSession(['locale' => 'en'])
        ->actingAs($user)
        ->get(route('dashboard.account-settings'));

    $response->assertOk()
        ->assertSee('Account Settings')
        ->assertSee('Manage your profile details')
        ->assertSee('Digest Preferences')
        ->assertSee('Back to Dashboard')
        ->assertSee('Save Account Settings');
});

it('updates account settings and resets verification when contact details change', function () {
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.test',
        'phone' => '+60111111111',
        'timezone' => 'Asia/Kuala_Lumpur',
        'email_verified_at' => now(),
        'phone_verified_at' => now(),
    ]);

    session(['user_timezone' => 'Asia/Kuala_Lumpur']);

    Livewire::actingAs($user)
        ->test(AccountSettings::class)
        ->set('name', 'Updated Name')
        ->set('email', 'updated@example.test')
        ->set('phone', '+60122222222')
        ->set('timezone', 'Asia/Jakarta')
        ->call('saveAccountSettings')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Updated Name')
        ->and($user->email)->toBe('updated@example.test')
        ->and($user->phone)->toBe('+60122222222')
        ->and($user->timezone)->toBe('Asia/Jakarta')
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->phone_verified_at)->toBeNull();
});

it('requires at least one contact method on account settings', function () {
    $user = User::factory()->create([
        'email' => 'member@example.test',
        'phone' => '+60113334444',
    ]);

    Livewire::actingAs($user)
        ->test(AccountSettings::class)
        ->set('email', '')
        ->set('phone', '')
        ->call('saveAccountSettings')
        ->assertHasErrors([
            'email' => 'required_without',
            'phone' => 'required_without',
        ]);
});
