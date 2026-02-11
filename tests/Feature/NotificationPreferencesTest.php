<?php

use App\Enums\NotificationChannel;
use App\Enums\NotificationFrequency;
use App\Enums\NotificationPreferenceKey;
use App\Jobs\SendSavedSearchDigest;
use App\Livewire\Pages\Dashboard\UserDashboard;
use App\Models\NotificationEndpoint;
use App\Models\NotificationPreference;
use App\Models\SavedSearch;
use App\Models\User;
use App\Notifications\SavedSearchDigestNotification;
use App\Services\EventSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

it('creates notifications table using laravel database notification structure', function () {
    expect(\Illuminate\Support\Facades\Schema::hasTable('notifications'))->toBeTrue()
        ->and(\Illuminate\Support\Facades\Schema::hasColumns('notifications', [
            'id',
            'type',
            'notifiable_type',
            'notifiable_id',
            'data',
            'read_at',
        ]))->toBeTrue();
});

it('stores notification endpoints and preferences for a user', function () {
    $user = User::factory()->create();

    $endpoint = NotificationEndpoint::factory()
        ->for($user, 'owner')
        ->create([
            'channel' => NotificationChannel::Whatsapp->value,
            'address' => '+60123456789',
        ]);

    $preference = NotificationPreference::factory()
        ->for($user, 'owner')
        ->create([
            'notification_key' => NotificationPreferenceKey::SavedSearchDigest->value,
            'frequency' => NotificationFrequency::Weekly->value,
            'channels' => [
                NotificationChannel::Email->value,
                NotificationChannel::InApp->value,
                NotificationChannel::Whatsapp->value,
            ],
        ]);

    expect($endpoint->owner->is($user))->toBeTrue()
        ->and($preference->owner->is($user))->toBeTrue()
        ->and($preference->channels)->toContain(
            NotificationChannel::InApp->value,
            NotificationChannel::Whatsapp->value
        );
});

it('resolves digest notification channels from user preferences', function () {
    $user = User::factory()->create(['email' => 'digest@example.test']);

    NotificationPreference::factory()
        ->for($user, 'owner')
        ->create([
            'notification_key' => NotificationPreferenceKey::SavedSearchDigest->value,
            'enabled' => true,
            'channels' => [
                NotificationChannel::Email->value,
                NotificationChannel::InApp->value,
                NotificationChannel::Whatsapp->value,
            ],
        ]);

    $savedSearch = SavedSearch::factory()->for($user)->create();
    $notification = new SavedSearchDigestNotification($savedSearch, collect());

    expect($notification->via($user))
        ->toBe(['mail', 'database']);
});

it('skips digest delivery when user opts out globally', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'digest-off@example.test']);
    SavedSearch::factory()
        ->for($user)
        ->create([
            'notify' => NotificationFrequency::Daily->value,
        ]);

    NotificationPreference::factory()
        ->for($user, 'owner')
        ->create([
            'notification_key' => NotificationPreferenceKey::SavedSearchDigest->value,
            'enabled' => false,
            'frequency' => NotificationFrequency::Off->value,
        ]);

    $searchService = mock(EventSearchService::class);
    $searchService->shouldNotReceive('search');
    $searchService->shouldNotReceive('searchNearby');

    new SendSavedSearchDigest(NotificationFrequency::Daily->value)
        ->handle($searchService);

    Notification::assertNothingSent();
});

it('checks digest frequency through user helper', function () {
    $user = User::factory()->create();

    NotificationPreference::factory()
        ->for($user, 'owner')
        ->create([
            'notification_key' => NotificationPreferenceKey::SavedSearchDigest->value,
            'enabled' => true,
            'frequency' => NotificationFrequency::Weekly->value,
        ]);

    expect($user->shouldReceiveNotificationFor(
        NotificationPreferenceKey::SavedSearchDigest->value,
        NotificationFrequency::Daily->value
    ))->toBeFalse()
        ->and($user->shouldReceiveNotificationFor(
            NotificationPreferenceKey::SavedSearchDigest->value,
            NotificationFrequency::Weekly->value
        ))->toBeTrue();
});

it('allows users to update digest preferences from dashboard settings', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test(UserDashboard::class)
        ->set('digestNotificationsEnabled', true)
        ->set('digestNotificationFrequency', NotificationFrequency::Weekly->value)
        ->set('digestNotificationChannels', [
            NotificationChannel::Email->value,
            NotificationChannel::InApp->value,
        ])
        ->call('saveDigestNotificationPreferences')
        ->assertHasNoErrors();

    /** @var NotificationPreference $preference */
    $preference = NotificationPreference::query()
        ->where('owner_id', $user->id)
        ->where('notification_key', NotificationPreferenceKey::SavedSearchDigest->value)
        ->firstOrFail();

    expect($preference->enabled)->toBeTrue()
        ->and($preference->frequency)->toBe(NotificationFrequency::Weekly)
        ->and($preference->channels)->toBe([
            NotificationChannel::Email->value,
            NotificationChannel::InApp->value,
        ]);
});

it('skips digest delivery when frequency does not match user preference', function () {
    Notification::fake();

    $user = User::factory()->create(['email' => 'digest-weekly@example.test']);
    SavedSearch::factory()
        ->for($user)
        ->create([
            'notify' => NotificationFrequency::Daily->value,
        ]);

    NotificationPreference::factory()
        ->for($user, 'owner')
        ->create([
            'notification_key' => NotificationPreferenceKey::SavedSearchDigest->value,
            'enabled' => true,
            'frequency' => NotificationFrequency::Weekly->value,
        ]);

    $searchService = mock(EventSearchService::class);
    $searchService->shouldNotReceive('search');
    $searchService->shouldNotReceive('searchNearby');

    new SendSavedSearchDigest(NotificationFrequency::Daily->value)
        ->handle($searchService);

    Notification::assertNothingSent();
});
