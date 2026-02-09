<?php

use App\Models\Event;
use App\Models\User;

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
    $this->seed(\Database\Seeders\RoleSeeder::class);

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
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);

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
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);

    $administrator = User::factory()->create();
    $administrator->assignRole('super_admin');

    $event = Event::factory()->create([
        'status' => 'pending',
        'event_url' => 'https://example.org/events/admin-typed-fields',
        'live_url' => 'https://youtube.com/watch?v=typed-admin-fields',
        'event_format' => \App\Enums\EventFormat::Hybrid,
        'gender' => \App\Enums\EventGenderRestriction::MenOnly,
        'age_group' => [\App\Enums\EventAgeGroup::Adults],
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
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);

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
