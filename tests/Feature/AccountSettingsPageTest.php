<?php

use App\Livewire\Pages\Dashboard\AccountSettings;
use App\Models\NotificationDestination;
use App\Models\User;
use App\Services\Notifications\NotificationSettingsManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the account settings page with profile and notifications tabs', function () {
    expect(route('dashboard.account-settings'))->toEndWith('/tetapan-akaun');

    $user = User::factory()->create();

    $response = $this->withSession(['locale' => 'en'])
        ->actingAs($user)
        ->get(route('dashboard.account-settings'));

    $response->assertOk()
        ->assertSee('Account Settings')
        ->assertSee('Profile')
        ->assertSee('Notifications')
        ->assertSee('Profile Details')
        ->assertSee('Save Account Settings')
        ->assertDontSee('Digest Preferences')
        ->assertDontSee('Save Preferences')
        ->assertSee('fi-fo-phone-input', false);
});

it('renders the notifications tab in Malay without leaking raw translation keys', function () {
    $user = User::factory()->create();

    $response = $this->withSession(['locale' => 'ms'])
        ->actingAs($user)
        ->get(route('dashboard.account-settings', ['tab' => 'notifications']));

    $response->assertOk()
        ->assertSee('Tetapan Akaun')
        ->assertSee('Notifikasi')
        ->assertSee('Notifikasi Push')
        ->assertSee('Ikut tetapan kumpulan')
        ->assertDontSee('notifications.settings.triggers.use_family_defaults')
        ->assertDontSee('notifications.settings.triggers.inherits_family_help')
        ->assertDontSee('notifications.settings.triggers.urgent_override')
        ->assertDontSee('Push Notification');
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

    NotificationDestination::factory()->for($user)->create([
        'channel' => 'email',
        'address' => 'old@example.test',
        'external_id' => null,
    ]);
    NotificationDestination::factory()->for($user)->create([
        'channel' => 'whatsapp',
        'address' => '+60111111111',
        'external_id' => null,
    ]);

    session(['user_timezone' => 'Asia/Kuala_Lumpur']);

    Livewire::actingAs($user)
        ->test(AccountSettings::class)
        ->set('formData.name', 'Updated Name')
        ->set('formData.email', 'updated@example.test')
        ->set('formData.phone', '+60122222222')
        ->set('formData.timezone', 'Asia/Jakarta')
        ->call('saveAccountSettings')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Updated Name')
        ->and($user->email)->toBe('updated@example.test')
        ->and($user->phone)->toBe('+60122222222')
        ->and($user->timezone)->toBe('Asia/Jakarta')
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->phone_verified_at)->toBeNull();

    expect(NotificationDestination::query()
        ->where('user_id', $user->id)
        ->where('channel', 'email')
        ->pluck('address')
        ->all())->toBe(['updated@example.test']);

    $this->assertDatabaseHas('notification_destinations', [
        'user_id' => $user->id,
        'channel' => 'email',
        'address' => 'updated@example.test',
        'status' => 'inactive',
    ]);

    $this->assertDatabaseMissing('notification_destinations', [
        'user_id' => $user->id,
        'channel' => 'email',
        'address' => 'old@example.test',
    ]);

    $this->assertDatabaseMissing('notification_destinations', [
        'user_id' => $user->id,
        'channel' => 'whatsapp',
        'address' => '+60111111111',
    ]);

    $notificationState = app(NotificationSettingsManager::class)->stateFor($user->fresh());

    expect($notificationState['settings']['timezone'])->toBe('Asia/Jakarta');
});

it('saves trigger overrides and fallback channels from account settings', function () {
    $user = User::factory()->create([
        'email' => 'member@example.test',
        'email_verified_at' => now(),
        'phone' => '+60128889999',
        'phone_verified_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(AccountSettings::class)
        ->set('tab', 'notifications')
        ->set('preferredChannelSlots', ['push', 'email', 'in_app', ''])
        ->set('fallbackChannelSlots', ['whatsapp', 'email', '', ''])
        ->set('notificationTriggersState.event_cancelled.inherits_family', false)
        ->set('notificationTriggersState.event_cancelled.channels', ['whatsapp'])
        ->set('notificationTriggersState.event_cancelled.urgent_override', true)
        ->call('saveNotificationPreferences')
        ->assertHasNoErrors();

    $state = app(NotificationSettingsManager::class)->stateFor($user->fresh());

    expect($state['settings']['preferred_channels'])->toBe(['push', 'email', 'in_app'])
        ->and($state['settings']['fallback_channels'])->toBe(['whatsapp', 'email'])
        ->and($state['triggers']['event_cancelled']['inherits_family'])->toBeFalse()
        ->and($state['triggers']['event_cancelled']['channels'])->toBe(['whatsapp'])
        ->and($state['triggers']['event_cancelled']['urgent_override'])->toBeTrue();
});

it('does not persist unsaved notification changes when saving profile details', function () {
    $user = User::factory()->create([
        'name' => 'Original Name',
        'email' => 'profile@example.test',
        'email_verified_at' => now(),
    ]);

    app(NotificationSettingsManager::class)->save($user, [
        'settings' => [
            'preferred_channels' => ['email'],
            'fallback_channels' => ['email'],
        ],
        'families' => [
            'event_updates' => [
                'enabled' => true,
                'cadence' => 'instant',
                'channels' => ['email'],
            ],
        ],
    ]);

    Livewire::actingAs($user)
        ->test(AccountSettings::class)
        ->set('tab', 'notifications')
        ->set('preferredChannelSlots', ['push', '', '', ''])
        ->set('notificationFamiliesState.event_updates.channels', ['push'])
        ->set('formData.name', 'Profile Saved Name')
        ->call('saveAccountSettings')
        ->assertHasNoErrors();

    $user->refresh();
    $state = app(NotificationSettingsManager::class)->stateFor($user);

    expect($user->name)->toBe('Profile Saved Name')
        ->and($state['settings']['preferred_channels'])->toBe(['email'])
        ->and($state['families']['event_updates']['channels'])->toBe(['email']);
});

it('keeps inherited trigger controls aligned with live family changes', function () {
    $user = User::factory()->create([
        'email' => 'sync@example.test',
        'email_verified_at' => now(),
    ]);

    Livewire::actingAs($user)
        ->test(AccountSettings::class)
        ->set('tab', 'notifications')
        ->assertSet('notificationTriggersState.followed_speaker_event.inherits_family', true)
        ->set('notificationFamiliesState.followed_content.cadence', 'weekly')
        ->set('notificationFamiliesState.followed_content.channels', ['push'])
        ->assertSet('notificationTriggersState.followed_speaker_event.cadence', 'weekly')
        ->assertSet('notificationTriggersState.followed_speaker_event.channels', ['push']);
});

it('requires at least one contact method on account settings', function () {
    $user = User::factory()->create([
        'email' => 'member@example.test',
        'phone' => '+60113334444',
    ]);

    Livewire::actingAs($user)
        ->test(AccountSettings::class)
        ->set('formData.email', '')
        ->set('formData.phone', '')
        ->call('saveAccountSettings')
        ->assertHasErrors([
            'formData.email' => 'required_without',
            'formData.phone' => 'required_without',
        ]);
});
