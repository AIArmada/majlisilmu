<?php

use App\Enums\NotificationFamily;
use App\Enums\NotificationPriority;
use App\Enums\NotificationTrigger;
use App\Livewire\Pages\Dashboard\NotificationsIndex;
use App\Models\NotificationDelivery;
use App\Models\NotificationMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the notifications inbox for authenticated users', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    NotificationMessage::factory()->create([
        'user_id' => $user->id,
        'family' => NotificationFamily::EventUpdates->value,
        'trigger' => NotificationTrigger::EventCancelled->value,
        'priority' => NotificationPriority::Urgent->value,
        'title' => 'Inbox notification',
        'read_at' => null,
        'meta' => ['inbox_visible' => true],
    ]);
    $visibleMessage = NotificationMessage::query()
        ->where('user_id', $user->id)
        ->where('title', 'Inbox notification')
        ->firstOrFail();
    NotificationDelivery::factory()->create([
        'notification_message_id' => $visibleMessage->id,
        'user_id' => $user->id,
        'family' => NotificationFamily::EventUpdates->value,
        'trigger' => NotificationTrigger::EventCancelled->value,
        'channel' => 'in_app',
        'destination_id' => null,
        'provider' => null,
        'provider_message_id' => null,
        'status' => 'delivered',
        'payload' => [],
        'meta' => [],
    ]);

    NotificationMessage::factory()->create([
        'user_id' => $user->id,
        'title' => 'Hidden email-only notification',
        'read_at' => null,
        'meta' => ['inbox_visible' => false],
    ]);

    NotificationMessage::factory()->create([
        'user_id' => $otherUser->id,
        'title' => 'Other user notification',
    ]);

    $response = $this->withSession(['locale' => 'en'])
        ->actingAs($user)
        ->get(route('dashboard.notifications'));

    $response->assertOk()
        ->assertSee('Notifications')
        ->assertSee('Inbox notification')
        ->assertDontSee('Hidden email-only notification')
        ->assertDontSee('Other user notification')
        ->assertSee('Mark all as read');
});

it('filters unread notifications and marks them as read in the inbox component', function () {
    $user = User::factory()->create();

    $unread = NotificationMessage::factory()->create([
        'user_id' => $user->id,
        'title' => 'Unread only',
        'read_at' => null,
        'meta' => ['inbox_visible' => true],
    ]);
    NotificationDelivery::factory()->create([
        'notification_message_id' => $unread->id,
        'user_id' => $user->id,
        'channel' => 'in_app',
        'destination_id' => null,
        'provider' => null,
        'provider_message_id' => null,
        'status' => 'delivered',
        'payload' => [],
        'meta' => [],
    ]);

    $read = NotificationMessage::factory()->create([
        'user_id' => $user->id,
        'title' => 'Read already',
        'read_at' => now(),
        'meta' => ['inbox_visible' => true],
    ]);
    NotificationDelivery::factory()->create([
        'notification_message_id' => $read->id,
        'user_id' => $user->id,
        'channel' => 'in_app',
        'destination_id' => null,
        'provider' => null,
        'provider_message_id' => null,
        'status' => 'delivered',
        'payload' => [],
        'meta' => [],
    ]);

    Livewire::actingAs($user)
        ->test(NotificationsIndex::class)
        ->set('status', 'unread')
        ->assertSee('Unread only')
        ->assertDontSee('Read already')
        ->call('markAsRead', $unread->id)
        ->assertHasNoErrors();

    expect($unread->fresh()->read_at)->not->toBeNull();
});
