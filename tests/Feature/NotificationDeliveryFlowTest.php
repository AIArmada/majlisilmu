<?php

use App\Enums\NotificationCadence;
use App\Enums\NotificationDeliveryStatus;
use App\Enums\NotificationFamily;
use App\Enums\NotificationPriority;
use App\Enums\NotificationTrigger;
use App\Jobs\DispatchNotificationDigests;
use App\Models\Event;
use App\Models\Institution;
use App\Models\NotificationDelivery;
use App\Models\NotificationDestination;
use App\Models\NotificationMessage;
use App\Models\Registration;
use App\Models\Speaker;
use App\Models\User;
use App\Services\Notifications\NotificationEngine;
use App\Services\Notifications\EventNotificationService;
use App\Services\Notifications\NotificationSettingsManager;
use App\Support\Notifications\NotificationDispatchData;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

it('notifies followers when a public approved future event is published', function () {
    Mail::fake();
    Http::fake();

    $user = User::factory()->create([
        'email' => 'follower@example.test',
    ]);
    $speaker = Speaker::factory()->create([
        'name' => 'Ustaz Aiman',
    ]);
    $institution = Institution::factory()->create();
    $event = Event::factory()->for($institution)->create([
        'title' => 'Majlis Tafsir Malam Jumaat',
        'status' => 'approved',
        'visibility' => 'public',
        'is_active' => true,
        'starts_at' => now()->addDays(5),
    ]);

    $event->speakers()->attach($speaker->id);
    $user->follow($speaker);

    app(EventNotificationService::class)->notifyPublication($event->fresh('speakers'));

    $messages = NotificationMessage::query()
        ->where('user_id', $user->id)
        ->get();

    expect($messages)->toHaveCount(1)
        ->and($messages->first()->trigger->value)->toBe('followed_speaker_event')
        ->and($messages->first()->title)->toContain('Majlis Tafsir Malam Jumaat');
});

it('sends reminders only to going and registered users, not saved or interested users', function () {
    Mail::fake();
    Http::fake();

    $savedUser = User::factory()->create(['email' => 'saved@example.test']);
    $interestedUser = User::factory()->create(['email' => 'interested@example.test']);
    $goingUser = User::factory()->create(['email' => 'going@example.test']);
    $registeredUser = User::factory()->create(['email' => 'registered@example.test']);
    $institution = Institution::factory()->create();
    $event = Event::factory()->for($institution)->create([
        'title' => 'Kuliah Subuh Khas',
        'status' => 'approved',
        'visibility' => 'public',
        'is_active' => true,
        'starts_at' => now()->addHours(24)->addMinutes(5),
    ]);

    $savedUser->savedEvents()->attach($event->id);
    $interestedUser->interestedEvents()->attach($event->id);
    $goingUser->goingEvents()->attach($event->id);
    Registration::factory()->for($event)->for($registeredUser)->create([
        'status' => 'registered',
    ]);

    app(EventNotificationService::class)->dispatchDueReminderNotifications(now()->toImmutable());

    expect(NotificationMessage::query()->where('user_id', $savedUser->id)->count())->toBe(0)
        ->and(NotificationMessage::query()->where('user_id', $interestedUser->id)->count())->toBe(0)
        ->and(NotificationMessage::query()->where('user_id', $goingUser->id)->where('trigger', 'reminder_24_hours')->count())->toBe(1)
        ->and(NotificationMessage::query()->where('user_id', $registeredUser->id)->where('trigger', 'reminder_24_hours')->count())->toBe(1);
});

it('defers urgent notifications during quiet hours when urgent override is disabled', function () {
    Queue::fake();

    $now = CarbonImmutable::parse('2026-03-09 23:15:00', 'Asia/Kuala_Lumpur');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    try {
        $user = User::factory()->create([
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

        NotificationDestination::factory()->for($user)->create([
            'channel' => 'push',
            'address' => 'device-1',
            'external_id' => 'push-token-1',
        ]);

        app(NotificationSettingsManager::class)->save($user, [
            'settings' => [
                'timezone' => 'Asia/Kuala_Lumpur',
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '07:00',
                'preferred_channels' => ['push'],
                'fallback_channels' => ['email'],
                'urgent_override' => false,
            ],
            'families' => [
                'event_updates' => [
                    'enabled' => true,
                    'cadence' => 'instant',
                    'channels' => ['push'],
                ],
            ],
        ]);

        $message = app(NotificationEngine::class)->dispatchToUser($user, new NotificationDispatchData(
            trigger: NotificationTrigger::EventCancelled,
            title: 'Event cancelled',
            body: 'This should wait until quiet hours end.',
            fingerprint: 'quiet-hours-without-override',
            bypassQuietHours: true,
        ));

        expect($message)->toBeInstanceOf(NotificationMessage::class);

        $this->assertDatabaseHas('notification_deliveries', [
            'notification_message_id' => $message->id,
            'channel' => 'push',
            'status' => NotificationDeliveryStatus::Deferred->value,
        ]);
    } finally {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }
});

it('uses configured fallback channels instead of inferring them from preferred order', function () {
    Queue::fake();

    $user = User::factory()->create([
        'email' => 'fallback@example.test',
        'phone' => '+60129990000',
        'phone_verified_at' => now(),
    ]);

    NotificationDestination::factory()->for($user)->create([
        'channel' => 'push',
        'address' => 'device-2',
        'external_id' => 'push-token-2',
    ]);

    app(NotificationSettingsManager::class)->save($user, [
        'settings' => [
            'preferred_channels' => ['push', 'whatsapp', 'email'],
            'fallback_channels' => ['email'],
            'fallback_strategy' => 'next_available',
        ],
        'families' => [
            'event_updates' => [
                'enabled' => true,
                'cadence' => 'instant',
                'channels' => ['push', 'whatsapp', 'email'],
            ],
        ],
    ]);

    $message = app(NotificationEngine::class)->dispatchToUser($user, new NotificationDispatchData(
        trigger: NotificationTrigger::EventCancelled,
        title: 'Fallback test',
        body: 'Push should only fall back to email.',
        fingerprint: 'configured-fallback-order',
    ));

    expect($message)->toBeInstanceOf(NotificationMessage::class);

    $pushDelivery = NotificationDelivery::query()
        ->where('notification_message_id', $message->id)
        ->where('channel', 'push')
        ->firstOrFail();

    expect(data_get($pushDelivery->meta, 'remaining_fallback_channels'))->toBe(['email']);
});

it('does not resend deliveries that were already claimed by another worker', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'claimed@example.test',
    ]);
    $message = NotificationMessage::factory()->for($user)->create([
        'family' => NotificationFamily::EventUpdates->value,
        'trigger' => NotificationTrigger::EventCancelled->value,
    ]);
    $destination = NotificationDestination::factory()->for($user)->create([
        'channel' => 'email',
        'address' => 'claimed@example.test',
        'external_id' => null,
    ]);
    $delivery = NotificationDelivery::factory()->create([
        'notification_message_id' => $message->id,
        'user_id' => $user->id,
        'channel' => 'email',
        'destination_id' => $destination->id,
        'status' => NotificationDeliveryStatus::Sent->value,
        'sent_at' => now(),
        'delivered_at' => null,
        'failed_at' => null,
    ]);

    app(NotificationEngine::class)->processDelivery($delivery);

    Mail::assertNothingSent();
});

it('defers overnight quiet hours only until the same morning boundary', function () {
    Queue::fake();

    $now = CarbonImmutable::parse('2026-03-10 06:00:00', 'Asia/Kuala_Lumpur');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    try {
        $user = User::factory()->create([
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

        NotificationDestination::factory()->for($user)->create([
            'channel' => 'push',
            'address' => 'device-overnight',
            'external_id' => 'push-token-overnight',
        ]);

        app(NotificationSettingsManager::class)->save($user, [
            'settings' => [
                'timezone' => 'Asia/Kuala_Lumpur',
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '07:00',
                'preferred_channels' => ['push'],
            ],
            'families' => [
                'event_updates' => [
                    'enabled' => true,
                    'cadence' => 'instant',
                    'channels' => ['push'],
                ],
            ],
        ]);

        $message = app(NotificationEngine::class)->dispatchToUser($user, new NotificationDispatchData(
            trigger: NotificationTrigger::EventScheduleChanged,
            title: 'Schedule changed',
            body: 'This should wait only until 7 AM today.',
            fingerprint: 'overnight-quiet-hours-boundary',
        ));

        $delivery = NotificationDelivery::query()
            ->where('notification_message_id', $message?->id)
            ->where('channel', 'push')
            ->firstOrFail();

        expect($delivery->status)->toBe(NotificationDeliveryStatus::Deferred)
            ->and(
                CarbonImmutable::parse((string) data_get($delivery->meta, 'deliver_after'))
                    ->timezone('Asia/Kuala_Lumpur')
                    ->format('Y-m-d H:i')
            )->toBe('2026-03-10 07:00');
    } finally {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }
});

it('does not queue fallback when another destination for the same channel succeeds', function () {
    Queue::fake();
    Http::fakeSequence()
        ->push(['error' => 'stale token'], 500)
        ->push(['name' => 'projects/test/messages/123'], 200);

    $user = User::factory()->create([
        'email' => 'fallback-success@example.test',
        'email_verified_at' => now(),
    ]);
    $message = NotificationMessage::factory()->for($user)->create([
        'family' => NotificationFamily::EventUpdates->value,
        'trigger' => NotificationTrigger::EventCancelled->value,
        'meta' => ['inbox_visible' => true],
    ]);
    $emailDestination = NotificationDestination::factory()->for($user)->create([
        'channel' => 'email',
        'address' => 'fallback-success@example.test',
        'external_id' => null,
    ]);
    $pushDestinationA = NotificationDestination::factory()->for($user)->create([
        'channel' => 'push',
        'address' => 'device-a',
        'external_id' => 'push-token-a',
    ]);
    $pushDestinationB = NotificationDestination::factory()->for($user)->create([
        'channel' => 'push',
        'address' => 'device-b',
        'external_id' => 'push-token-b',
    ]);

    config()->set('notification-center.push.project_id', 'demo-project');
    config()->set('notification-center.push.credentials', 'demo-token');

    $deliveryMeta = [
        'fallback_strategy' => 'next_available',
        'remaining_fallback_channels' => ['email'],
        'deliver_after' => null,
        'bypass_quiet_hours' => false,
    ];

    $firstDelivery = NotificationDelivery::factory()->create([
        'notification_message_id' => $message->id,
        'user_id' => $user->id,
        'family' => NotificationFamily::EventUpdates->value,
        'trigger' => NotificationTrigger::EventCancelled->value,
        'channel' => 'push',
        'destination_id' => $pushDestinationA->id,
        'status' => NotificationDeliveryStatus::Pending->value,
        'sent_at' => null,
        'delivered_at' => null,
        'failed_at' => null,
        'meta' => $deliveryMeta,
    ]);
    $secondDelivery = NotificationDelivery::factory()->create([
        'notification_message_id' => $message->id,
        'user_id' => $user->id,
        'family' => NotificationFamily::EventUpdates->value,
        'trigger' => NotificationTrigger::EventCancelled->value,
        'channel' => 'push',
        'destination_id' => $pushDestinationB->id,
        'status' => NotificationDeliveryStatus::Pending->value,
        'sent_at' => null,
        'delivered_at' => null,
        'failed_at' => null,
        'meta' => $deliveryMeta,
    ]);

    app(NotificationEngine::class)->processDelivery($firstDelivery);

    $this->assertDatabaseMissing('notification_deliveries', [
        'notification_message_id' => $message->id,
        'channel' => 'email',
        'destination_id' => $emailDestination->id,
    ]);

    app(NotificationEngine::class)->processDelivery($secondDelivery);

    $this->assertDatabaseMissing('notification_deliveries', [
        'notification_message_id' => $message->id,
        'channel' => 'email',
        'destination_id' => $emailDestination->id,
    ]);
});

it('preserves quiet-hours bypass when an urgent notification falls back to whatsapp', function () {
    Queue::fake();

    $now = CarbonImmutable::parse('2026-03-09 23:15:00', 'Asia/Kuala_Lumpur');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    try {
        $user = User::factory()->create([
            'timezone' => 'Asia/Kuala_Lumpur',
            'phone' => '+60135557777',
            'phone_verified_at' => now(),
        ]);
        $message = NotificationMessage::factory()->for($user)->create([
            'family' => NotificationFamily::EventUpdates->value,
            'trigger' => NotificationTrigger::EventCancelled->value,
            'meta' => ['inbox_visible' => true],
        ]);
        NotificationDestination::factory()->for($user)->create([
            'channel' => 'whatsapp',
            'address' => '+60135557777',
            'external_id' => null,
        ]);

        app(NotificationSettingsManager::class)->save($user, [
            'settings' => [
                'timezone' => 'Asia/Kuala_Lumpur',
                'quiet_hours_start' => '22:00',
                'quiet_hours_end' => '07:00',
                'preferred_channels' => ['push', 'whatsapp'],
                'fallback_channels' => ['whatsapp'],
                'urgent_override' => true,
            ],
            'families' => [
                'event_updates' => [
                    'enabled' => true,
                    'cadence' => 'instant',
                    'channels' => ['push', 'whatsapp'],
                ],
            ],
        ]);

        $pushDelivery = NotificationDelivery::factory()->create([
            'notification_message_id' => $message->id,
            'user_id' => $user->id,
            'family' => NotificationFamily::EventUpdates->value,
            'trigger' => NotificationTrigger::EventCancelled->value,
            'channel' => 'push',
            'destination_id' => null,
            'status' => NotificationDeliveryStatus::Pending->value,
            'sent_at' => null,
            'delivered_at' => null,
            'failed_at' => null,
            'meta' => [
                'fallback_strategy' => 'next_available',
                'remaining_fallback_channels' => ['whatsapp'],
                'deliver_after' => null,
                'bypass_quiet_hours' => true,
            ],
        ]);

        app(NotificationEngine::class)->processDelivery($pushDelivery);

        $delivery = NotificationDelivery::query()
            ->where('notification_message_id', $message->id)
            ->where('channel', 'whatsapp')
            ->firstOrFail();

        expect($delivery->status)->toBe(NotificationDeliveryStatus::Pending)
            ->and(data_get($delivery->meta, 'deliver_after'))->toBeNull();
    } finally {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }
});

it('builds daily digests from the scheduled window even when the job runs late', function () {
    Queue::fake();

    $now = CarbonImmutable::parse('2026-03-10 08:14:00', 'Asia/Kuala_Lumpur');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    try {
        $user = User::factory()->create([
            'email' => 'digest-window@example.test',
            'email_verified_at' => now(),
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);

        app(NotificationSettingsManager::class)->save($user, [
            'settings' => [
                'timezone' => 'Asia/Kuala_Lumpur',
                'digest_delivery_time' => '08:00',
                'preferred_channels' => ['email'],
            ],
            'families' => [
                'followed_content' => [
                    'enabled' => true,
                    'cadence' => 'daily',
                    'channels' => ['email'],
                ],
            ],
        ]);

        $earlyBoundaryMessage = NotificationMessage::factory()->for($user)->create([
            'family' => NotificationFamily::FollowedContent->value,
            'trigger' => NotificationTrigger::FollowedSpeakerEvent->value,
            'title' => 'Boundary message',
            'occurred_at' => CarbonImmutable::parse('2026-03-09 08:05:00', 'Asia/Kuala_Lumpur')->utc(),
            'meta' => [
                'delivery_cadence' => NotificationCadence::Daily->value,
                'inbox_visible' => false,
            ],
        ]);
        $regularWindowMessage = NotificationMessage::factory()->for($user)->create([
            'family' => NotificationFamily::FollowedContent->value,
            'trigger' => NotificationTrigger::FollowedSpeakerEvent->value,
            'title' => 'Regular message',
            'occurred_at' => CarbonImmutable::parse('2026-03-09 09:00:00', 'Asia/Kuala_Lumpur')->utc(),
            'meta' => [
                'delivery_cadence' => NotificationCadence::Daily->value,
                'inbox_visible' => false,
            ],
        ]);

        (new DispatchNotificationDigests('daily'))
            ->handle(app(NotificationEngine::class), app(NotificationSettingsManager::class));

        $digestMessage = NotificationMessage::query()
            ->where('user_id', $user->id)
            ->where('meta->digest', true)
            ->firstOrFail();

        expect(data_get($digestMessage->meta, 'source_message_ids'))->toBe([
            $earlyBoundaryMessage->id,
            $regularWindowMessage->id,
        ]);
    } finally {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }
});

it('builds daily digests across daylight saving fall back without missing early messages', function () {
    Queue::fake();

    $now = CarbonImmutable::parse('2026-11-02 08:14:00', 'America/New_York');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow($now);

    try {
        $user = User::factory()->create([
            'email' => 'digest-dst@example.test',
            'email_verified_at' => now(),
            'timezone' => 'America/New_York',
        ]);

        app(NotificationSettingsManager::class)->save($user, [
            'settings' => [
                'timezone' => 'America/New_York',
                'digest_delivery_time' => '08:00',
                'preferred_channels' => ['email'],
            ],
            'families' => [
                'followed_content' => [
                    'enabled' => true,
                    'cadence' => 'daily',
                    'channels' => ['email'],
                ],
            ],
        ]);

        $earlyBoundaryMessage = NotificationMessage::factory()->for($user)->create([
            'family' => NotificationFamily::FollowedContent->value,
            'trigger' => NotificationTrigger::FollowedSpeakerEvent->value,
            'title' => 'DST boundary message',
            'occurred_at' => CarbonImmutable::parse('2026-11-01 08:05:00', 'America/New_York')->utc(),
            'meta' => [
                'delivery_cadence' => NotificationCadence::Daily->value,
                'inbox_visible' => false,
            ],
        ]);
        $regularWindowMessage = NotificationMessage::factory()->for($user)->create([
            'family' => NotificationFamily::FollowedContent->value,
            'trigger' => NotificationTrigger::FollowedSpeakerEvent->value,
            'title' => 'DST regular message',
            'occurred_at' => CarbonImmutable::parse('2026-11-01 09:00:00', 'America/New_York')->utc(),
            'meta' => [
                'delivery_cadence' => NotificationCadence::Daily->value,
                'inbox_visible' => false,
            ],
        ]);

        (new DispatchNotificationDigests('daily'))
            ->handle(app(NotificationEngine::class), app(NotificationSettingsManager::class));

        $digestMessage = NotificationMessage::query()
            ->where('user_id', $user->id)
            ->where('meta->digest', true)
            ->firstOrFail();

        expect(data_get($digestMessage->meta, 'source_message_ids'))->toBe([
            $earlyBoundaryMessage->id,
            $regularWindowMessage->id,
        ]);
    } finally {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }
});

it('marks digest source deliveries only after the digest delivery succeeds', function () {
    Mail::fake();

    $user = User::factory()->create([
        'email' => 'digest@example.test',
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);
    $sourceMessage = NotificationMessage::factory()->for($user)->create([
        'family' => NotificationFamily::EventUpdates->value,
        'trigger' => NotificationTrigger::EventScheduleChanged->value,
    ]);
    $digestMessage = NotificationMessage::factory()->for($user)->create([
        'family' => NotificationFamily::EventUpdates->value,
        'trigger' => NotificationTrigger::EventScheduleChanged->value,
        'meta' => [
            'digest' => true,
            'source_message_ids' => [$sourceMessage->id],
        ],
    ]);
    $destination = NotificationDestination::factory()->for($user)->create([
        'channel' => 'email',
        'address' => 'digest@example.test',
        'external_id' => null,
    ]);
    $digestDelivery = NotificationDelivery::factory()->create([
        'notification_message_id' => $digestMessage->id,
        'user_id' => $user->id,
        'channel' => 'email',
        'destination_id' => $destination->id,
        'status' => NotificationDeliveryStatus::Pending->value,
        'sent_at' => null,
        'delivered_at' => null,
        'failed_at' => null,
    ]);

    $this->assertDatabaseMissing('notification_deliveries', [
        'notification_message_id' => $sourceMessage->id,
        'channel' => 'email',
        'status' => NotificationDeliveryStatus::Delivered->value,
    ]);

    app(NotificationEngine::class)->processDelivery($digestDelivery);

    $this->assertDatabaseHas('notification_deliveries', [
        'notification_message_id' => $sourceMessage->id,
        'channel' => 'email',
        'status' => NotificationDeliveryStatus::Delivered->value,
    ]);
});
