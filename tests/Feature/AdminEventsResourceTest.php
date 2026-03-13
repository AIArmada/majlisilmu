<?php

use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\TimingMode;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;

it('resolves pending transitionable states without moderation context', function () {
    $event = Event::factory()->create([
        'status' => 'pending',
    ]);

    $states = $event->status->transitionableStates();

    expect($states)
        ->toContain('approved')
        ->not->toContain('needs_changes')
        ->not->toContain('rejected');
});

it('resolves moderation transitions when context is provided', function () {
    $this->seed(RoleSeeder::class);

    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    $event = Event::factory()->create([
        'status' => 'pending',
    ]);

    $states = $event->status->transitionableStates($moderator, 'incomplete_info');

    expect($states)
        ->toContain('approved')
        ->toContain('needs_changes')
        ->toContain('rejected');
});

it('renders admin events list and search with pending events', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $administrator = User::factory()->create();
    $administrator->assignRole('super_admin');

    $event = Event::factory()->create([
        'status' => 'pending',
        'title' => 'Admin Event Resource Search Coverage',
    ]);

    $this->actingAs($administrator)
        ->get('/admin/events')
        ->assertSuccessful()
        ->assertSee($event->title);

    $this->actingAs($administrator)
        ->get('/admin/events?search='.urlencode($event->title))
        ->assertSuccessful()
        ->assertSee($event->title);
});

it('shows typed event fields on the admin edit form', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $administrator = User::factory()->create();
    $administrator->assignRole('super_admin');

    $event = Event::factory()->create([
        'status' => 'pending',
        'event_url' => 'https://example.org/events/admin-typed-fields',
        'live_url' => 'https://youtube.com/watch?v=typed-admin-fields',
        'event_format' => EventFormat::Hybrid,
        'gender' => EventGenderRestriction::MenOnly,
        'age_group' => [EventAgeGroup::Adults],
        'children_allowed' => false,
    ]);

    $this->actingAs($administrator)
        ->get("/admin/events/{$event->id}/edit")
        ->assertSuccessful()
        ->assertSee('Maklumat Majlis')
        ->assertSee('Kategori & Bidang')
        ->assertSee('Penganjur & Lokasi')
        ->assertSee('Penceramah & Media')
        ->assertSee('Semak & Moderasi')
        ->assertSee('Bahasa')
        ->assertSee('Kategori')
        ->assertSee('Bidang Ilmu')
        ->assertSee('Sumber Utama')
        ->assertSee('Tema / Isu');
});

it('renders the admin event view page with infolist tabs', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $administrator = User::factory()->create();
    $administrator->assignRole('super_admin');

    $event = Event::factory()->create([
        'status' => 'pending',
    ]);

    $this->actingAs($administrator)
        ->get("/admin/events/{$event->id}")
        ->assertSuccessful()
        ->assertSee('Maklumat Majlis')
        ->assertSee('Kategori & Bidang')
        ->assertSee('Penganjur & Lokasi')
        ->assertSee('Penceramah & Media')
        ->assertSee('Semak & Moderasi');
});

it('sanitizes description and uses full-width layout on admin event view page', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $administrator = User::factory()->create();
    $administrator->assignRole('super_admin');

    $event = Event::factory()->create([
        'status' => 'pending',
        'description' => '<p>Ini adalah <strong>forum</strong> sekolah</p>',
    ]);

    $this->actingAs($administrator)
        ->get("/admin/events/{$event->id}")
        ->assertSuccessful()
        ->assertSee('Ini adalah forum sekolah')
        ->assertDontSee('&lt;p&gt;Ini adalah &lt;strong&gt;forum&lt;/strong&gt; sekolah&lt;/p&gt;', false)
        ->assertSee('fi-width-full', false);
});

it('shows date-only start time for prayer-relative events in the admin view', function () {
    $this->seed(PermissionSeeder::class);
    $this->seed(RoleSeeder::class);

    $administrator = User::factory()->create();
    $administrator->assignRole('super_admin');

    $prayerRelativeEvent = Event::factory()->create([
        'status' => 'pending',
        'starts_at' => '2026-02-19 03:33:00',
        'timing_mode' => TimingMode::PrayerRelative,
        'prayer_reference' => null,
        'prayer_offset' => null,
        'prayer_display_text' => 'Selepas Maghrib',
    ]);

    $absoluteEvent = Event::factory()->create([
        'status' => 'pending',
        'starts_at' => '2026-02-20 09:41:00',
        'timing_mode' => TimingMode::Absolute,
        'prayer_reference' => null,
        'prayer_offset' => null,
        'prayer_display_text' => null,
    ]);

    $prayerRelativeDate = $prayerRelativeEvent->starts_at?->format('M d, Y');
    $prayerRelativeDateTime = $prayerRelativeEvent->starts_at?->format('M d, Y H:i:s');
    $absoluteDateTime = $absoluteEvent->starts_at?->format('M d, Y H:i:s');

    $this->actingAs($administrator)
        ->get("/admin/events/{$prayerRelativeEvent->id}")
        ->assertSuccessful()
        ->assertSee($prayerRelativeDate)
        ->assertDontSee($prayerRelativeDateTime);

    $absoluteResponse = $this->actingAs($administrator)
        ->get("/admin/events/{$absoluteEvent->id}")
        ->assertSuccessful();

    $absoluteResponseContent = $absoluteResponse->getContent();
    $possibleAbsoluteDateTimes = [
        $absoluteDateTime,
        $absoluteEvent->starts_at?->copy()->timezone('Asia/Kuala_Lumpur')->format('M d, Y H:i:s'),
        $absoluteEvent->starts_at?->format('M d, Y g:i:s A'),
        $absoluteEvent->starts_at?->copy()->timezone('Asia/Kuala_Lumpur')->format('M d, Y g:i:s A'),
    ];

    expect(collect($possibleAbsoluteDateTimes)
        ->filter()
        ->contains(fn (string $value): bool => str_contains((string) $absoluteResponseContent, $value)))->toBeTrue();
});
