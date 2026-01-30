<?php

use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shows verification warnings in moderation queue', function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);

    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    $institution = Institution::factory()->create(['status' => 'verified']);
    $venue = Venue::factory()->create(['status' => 'unverified']);
    $speaker = Speaker::factory()->create(['status' => 'pending']);

    $event = Event::factory()->create([
        'status' => 'pending',
        'institution_id' => $institution->id,
        'venue_id' => $venue->id,
    ]);
    $event->speakers()->attach($speaker);

    $this->actingAs($moderator)
        ->get('/admin/moderation-queue')
        ->assertSuccessful()
        ->assertSee('Unverified')
        ->assertSee('1 unverified');
});
