<?php

use AIArmada\FilamentAuthz\Facades\Authz;
use App\Actions\Events\PublishEventChangeAnnouncement;
use App\Enums\EventChangeSeverity;
use App\Enums\EventChangeStatus;
use App\Enums\EventChangeType;
use App\Enums\NotificationPriority;
use App\Enums\NotificationTrigger;
use App\Enums\ScheduleState;
use App\Models\Event;
use App\Models\EventChangeAnnouncement;
use App\Models\EventKeyPerson;
use App\Models\Institution;
use App\Models\PendingNotification;
use App\Models\Registration;
use App\Models\SlugRedirect;
use App\Models\Speaker;
use App\Models\User;
use App\Services\Notifications\EventNotificationService;
use App\Support\Authz\MemberRoleScopes;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ScopedMemberRolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('publishes cancellation announcements and notifies committed users only once', function () {
    $administrator = eventChangeAdministrator();
    $institution = Institution::factory()->create();
    $committedUser = User::factory()->create();
    $follower = User::factory()->create();

    $event = eventChangeApprovedEvent([
        'institution_id' => $institution->id,
        'title' => 'Kuliah Dibatalkan',
    ]);

    $committedUser->savedEvents()->attach($event->id);
    $committedUser->goingEvents()->attach($event->id);
    Registration::factory()->for($event)->for($committedUser)->create([
        'status' => 'registered',
    ]);
    $follower->follow($institution);

    $announcement = app(PublishEventChangeAnnouncement::class)->handle(
        event: $event,
        actor: $administrator,
        type: EventChangeType::Cancelled,
        publicMessage: 'Majlis ini dibatalkan oleh pihak penganjur.',
    );

    $event->refresh();

    expect((string) $event->status)->toBe('cancelled')
        ->and($event->schedule_state)->toBe(ScheduleState::Cancelled)
        ->and($announcement->status)->toBe(EventChangeStatus::Published)
        ->and($announcement->severity)->toBe(EventChangeSeverity::Urgent)
        ->and($announcement->changed_fields)->toContain('status', 'schedule_state');

    $notification = PendingNotification::query()
        ->where('user_id', $committedUser->id)
        ->where('fingerprint', 'event-change:'.$announcement->id)
        ->firstOrFail();

    expect($notification->trigger)->toBe(NotificationTrigger::EventCancelled)
        ->and($notification->priority)->toBe(NotificationPriority::Urgent)
        ->and($notification->meta['event_change_announcement_id'] ?? null)->toBe($announcement->id)
        ->and($notification->meta['replacement_event_id'] ?? null)->toBeNull();

    expect(PendingNotification::query()
        ->where('user_id', $committedUser->id)
        ->where('fingerprint', 'event-change:'.$announcement->id)
        ->count())->toBe(1);

    $this->assertDatabaseMissing('notification_messages', [
        'user_id' => $follower->id,
        'fingerprint' => 'event-change:'.$announcement->id,
    ]);
});

it('keeps ordinary edits out of the change announcement workflow', function () {
    $event = eventChangeApprovedEvent([
        'title' => 'Kuliah Biasa',
    ]);

    $event->update([
        'title' => 'Kuliah Biasa Dikemas Kini',
    ]);

    $event->refresh();

    expect((string) $event->status)->toBe('approved')
        ->and(EventChangeAnnouncement::query()->where('event_id', $event->id)->exists())->toBeFalse()
        ->and(PendingNotification::query()->where('entity_id', $event->id)->exists())->toBeFalse();
});

it('blocks registration calendar and check-in surfaces for unknown postponements', function () {
    $administrator = eventChangeAdministrator();
    $event = eventChangeApprovedEvent([
        'title' => 'Kuliah Ditangguhkan',
    ]);

    $event->settings()->updateOrCreate(['event_id' => $event->id], [
        'registration_required' => true,
        'registration_opens_at' => now()->subDay(),
        'registration_closes_at' => now()->addDay(),
    ]);

    app(PublishEventChangeAnnouncement::class)->handle(
        event: $event,
        actor: $administrator,
        type: EventChangeType::Postponed,
        publicMessage: 'Tarikh baharu belum disahkan.',
        notify: false,
    );

    $event->refresh();

    expect($event->schedule_state)->toBe(ScheduleState::Postponed);

    $this->get(route('events.show', $event))
        ->assertOk()
        ->assertSee('Ditangguhkan')
        ->assertSee('Tarikh baharu belum disahkan')
        ->assertSee('Pendaftaran ditutup sehingga tarikh disahkan');

    $this->get(route('events.calendar', $event))->assertNotFound();

    $this->from(route('events.show', $event))
        ->post(route('events.register', $event), [
            'name' => 'Ahmad',
            'email' => 'ahmad@example.test',
        ])
        ->assertSessionHasErrors('registration');
});

it('keeps replacement event URLs separate from the original source of truth notice', function () {
    $administrator = eventChangeAdministrator();
    $original = eventChangeApprovedEvent([
        'title' => 'Kuliah Asal',
        'slug' => 'kuliah-asal',
    ]);
    $replacement = eventChangeApprovedEvent([
        'title' => 'Kuliah Pengganti',
        'slug' => 'kuliah-pengganti',
        'starts_at' => now()->addDays(8),
        'ends_at' => now()->addDays(8)->addHours(2),
    ]);

    app(PublishEventChangeAnnouncement::class)->handle(
        event: $original,
        actor: $administrator,
        type: EventChangeType::ReplacementLinked,
        publicMessage: 'Sila hadir ke majlis pengganti.',
        replacementEvent: $replacement,
        notify: false,
    );

    $this->get(route('events.show', $original))
        ->assertOk()
        ->assertSee('Lihat Majlis Pengganti');
});

it('keeps replacement CTAs after later notices and resolves replacement chains', function () {
    $administrator = eventChangeAdministrator();
    $original = eventChangeApprovedEvent([
        'title' => 'Kuliah Asal Berantai',
        'slug' => 'kuliah-asal-berantai',
    ]);
    $firstReplacement = eventChangeApprovedEvent([
        'title' => 'Kuliah Pengganti Pertama',
        'slug' => 'kuliah-pengganti-pertama',
    ]);
    $finalReplacement = eventChangeApprovedEvent([
        'title' => 'Kuliah Pengganti Terkini',
        'slug' => 'kuliah-pengganti-terkini',
    ]);

    app(PublishEventChangeAnnouncement::class)->handle(
        event: $original,
        actor: $administrator,
        type: EventChangeType::ReplacementLinked,
        publicMessage: 'Sila rujuk majlis pengganti pertama.',
        replacementEvent: $firstReplacement,
        notify: false,
    );

    app(PublishEventChangeAnnouncement::class)->handle(
        event: $firstReplacement,
        actor: $administrator,
        type: EventChangeType::ReplacementLinked,
        publicMessage: 'Majlis pengganti pertama diganti pula.',
        replacementEvent: $finalReplacement,
        notify: false,
    );

    app(PublishEventChangeAnnouncement::class)->handle(
        event: $original,
        actor: $administrator,
        type: EventChangeType::Other,
        publicMessage: 'Nota terkini untuk pautan lama.',
        notify: false,
    );

    expect(EventChangeAnnouncement::query()
        ->where('event_id', $original->id)
        ->whereNotNull('replacement_event_id')
        ->count())->toBe(1)
        ->and($original->fresh()->latestPublishedReplacementAnnouncement?->replacement_event_id)
        ->toBe($firstReplacement->id);

    $this->get(route('events.show', $original))
        ->assertOk()
        ->assertSee('Nota terkini untuk pautan lama.')
        ->assertSee('Lihat Majlis Pengganti')
        ->assertSee(route('events.show', $finalReplacement), false);
});

it('rejects self replacement announcements', function () {
    $administrator = eventChangeAdministrator();
    $event = eventChangeApprovedEvent([
        'title' => 'Kuliah Tidak Boleh Ganti Diri',
    ]);

    expect(fn () => app(PublishEventChangeAnnouncement::class)->handle(
        event: $event,
        actor: $administrator,
        type: EventChangeType::ReplacementLinked,
        publicMessage: 'Ganti diri sendiri.',
        replacementEvent: $event,
        notify: false,
    ))->toThrow(ValidationException::class);

    expect(EventChangeAnnouncement::query()->where('event_id', $event->id)->exists())->toBeFalse();
});

it('rejects replacement chains that would loop back to the original event', function () {
    $administrator = eventChangeAdministrator();
    $original = eventChangeApprovedEvent([
        'title' => 'Kuliah Asal Gelung',
    ]);
    $replacement = eventChangeApprovedEvent([
        'title' => 'Kuliah Pengganti Gelung',
    ]);

    app(PublishEventChangeAnnouncement::class)->handle(
        event: $original,
        actor: $administrator,
        type: EventChangeType::ReplacementLinked,
        publicMessage: 'Sila rujuk majlis pengganti.',
        replacementEvent: $replacement,
        notify: false,
    );

    expect(fn () => app(PublishEventChangeAnnouncement::class)->handle(
        event: $replacement,
        actor: $administrator,
        type: EventChangeType::ReplacementLinked,
        publicMessage: 'Pautan balik tidak dibenarkan.',
        replacementEvent: $original,
        notify: false,
    ))->toThrow(ValidationException::class);

    expect(EventChangeAnnouncement::query()
        ->where('event_id', $replacement->id)
        ->where('replacement_event_id', $original->id)
        ->exists())->toBeFalse();
});

it('rejects replacement events that are not publicly reachable', function () {
    $administrator = eventChangeAdministrator();
    $original = eventChangeApprovedEvent([
        'title' => 'Kuliah Asal Umum',
    ]);
    $privateReplacement = eventChangeApprovedEvent([
        'title' => 'Kuliah Ganti Peribadi',
        'visibility' => 'private',
    ]);

    expect(fn () => app(PublishEventChangeAnnouncement::class)->handle(
        event: $original,
        actor: $administrator,
        type: EventChangeType::ReplacementLinked,
        publicMessage: 'Majlis ganti tidak boleh dicapai umum.',
        replacementEvent: $privateReplacement,
        notify: false,
    ))->toThrow(ValidationException::class);

    expect(EventChangeAnnouncement::query()->where('event_id', $original->id)->exists())->toBeFalse();
});

it('allows speaker members for listed event speakers to publish change announcements', function () {
    eventChangeSeedScopedRoles();

    $editor = User::factory()->create();
    $speaker = Speaker::factory()->create();
    $event = eventChangeApprovedEvent([
        'title' => 'Kuliah Penceramah Ahli',
    ]);

    EventKeyPerson::factory()
        ->for($event)
        ->for($speaker)
        ->create();

    $speaker->members()->syncWithoutDetaching([$editor->id]);
    eventChangeAssignSpeakerRole($editor, 'editor');

    $announcement = app(PublishEventChangeAnnouncement::class)->handle(
        event: $event,
        actor: $editor,
        type: EventChangeType::TopicChanged,
        publicMessage: 'Tajuk majlis dikemas kini.',
        notify: false,
    );

    expect($announcement->event_id)->toBe($event->id)
        ->and($announcement->type)->toBe(EventChangeType::TopicChanged);
});

it('creates same-event slug aliases when a published change mutates the schedule', function () {
    $administrator = eventChangeAdministrator();
    $event = eventChangeApprovedEvent([
        'title' => 'Kuliah Tukar Masa',
        'slug' => 'kuliah-tukar-masa-10-5-26',
        'starts_at' => Carbon::parse('2026-05-10 20:00:00', 'Asia/Kuala_Lumpur')->utc(),
        'ends_at' => Carbon::parse('2026-05-10 22:00:00', 'Asia/Kuala_Lumpur')->utc(),
    ]);
    $oldPath = route('events.show', $event, false);

    app(PublishEventChangeAnnouncement::class)->handle(
        event: $event,
        actor: $administrator,
        type: EventChangeType::ScheduleChanged,
        publicMessage: 'Masa majlis telah berubah.',
        changes: [
            'starts_at' => Carbon::parse('2026-05-11 20:00:00', 'Asia/Kuala_Lumpur')->utc(),
            'ends_at' => Carbon::parse('2026-05-11 22:00:00', 'Asia/Kuala_Lumpur')->utc(),
        ],
        notify: false,
    );

    $event->refresh();

    $redirect = SlugRedirect::query()->where('source_path', $oldPath)->firstOrFail();

    expect($redirect->destination_path)->toBe(route('events.show', $event, false));

    $this->get($oldPath)
        ->assertRedirect(route('events.show', $event));
});

it('marks schedule changes into the next 24 hours as urgent', function () {
    $now = Carbon::parse('2026-05-01 00:00:00', 'UTC');
    Carbon::setTestNow($now);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-05-01 00:00:00', 'UTC'));

    $administrator = eventChangeAdministrator();
    $event = eventChangeApprovedEvent([
        'title' => 'Kuliah Hampir',
        'starts_at' => $now->copy()->addDays(3),
        'ends_at' => $now->copy()->addDays(3)->addHours(2),
    ]);

    try {
        $announcement = app(PublishEventChangeAnnouncement::class)->handle(
            event: $event,
            actor: $administrator,
            type: EventChangeType::ScheduleChanged,
            publicMessage: 'Masa baharu dalam 24 jam.',
            changes: [
                'starts_at' => $now->copy()->addHours(12),
                'ends_at' => $now->copy()->addHours(14),
            ],
            notify: false,
        );

        expect($announcement->severity)->toBe(EventChangeSeverity::Urgent);
    } finally {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }
});

it('does not send reminders for unknown postponed events using their last known time', function () {
    $now = CarbonImmutable::parse('2026-05-01 00:00:00', 'UTC');
    Carbon::setTestNow(Carbon::parse('2026-05-01 00:00:00', 'UTC'));
    CarbonImmutable::setTestNow($now);

    $goingUser = User::factory()->create();
    $event = eventChangeApprovedEvent([
        'title' => 'Kuliah Ditangguh Hampir',
        'schedule_state' => ScheduleState::Postponed,
        'starts_at' => $now->addHours(2),
        'ends_at' => $now->addHours(4),
    ]);

    $goingUser->goingEvents()->attach($event->id);

    try {
        app(EventNotificationService::class)->dispatchDueReminderNotifications($now);

        expect(PendingNotification::query()
            ->where('user_id', $goingUser->id)
            ->where('entity_id', $event->id)
            ->whereIn('trigger', [
                NotificationTrigger::Reminder2Hours->value,
                NotificationTrigger::CheckinOpen->value,
            ])
            ->exists())->toBeFalse();
    } finally {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    }
});

function eventChangeAdministrator(): User
{
    eventChangeSeedScopedRoles();

    $administrator = User::factory()->create();
    $administrator->assignRole('super_admin');

    return $administrator;
}

function eventChangeSeedScopedRoles(): void
{
    test()->seed(RoleSeeder::class);
    test()->seed(PermissionSeeder::class);
    test()->seed(ScopedMemberRolesSeeder::class);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

function eventChangeAssignSpeakerRole(User $user, string $role): void
{
    Authz::withScope(app(MemberRoleScopes::class)->speaker(), function () use ($user, $role): void {
        $user->syncRoles([$role]);
    }, $user);
}

/**
 * @param  array<string, mixed>  $attributes
 */
function eventChangeApprovedEvent(array $attributes = []): Event
{
    return Event::factory()->create(array_replace([
        'status' => 'approved',
        'visibility' => 'public',
        'schedule_state' => ScheduleState::Active,
        'starts_at' => now()->addDays(7),
        'ends_at' => now()->addDays(7)->addHours(2),
        'timezone' => 'Asia/Kuala_Lumpur',
        'is_active' => true,
    ], $attributes));
}
