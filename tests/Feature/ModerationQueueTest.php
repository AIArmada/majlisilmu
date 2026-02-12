<?php

use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\PermissionSeeder::class);
});

it('shows verification warnings in moderation queue', function () {
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

it('shows only needs changes events on the needs changes tab', function () {
    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    $pendingEvent = Event::factory()->create([
        'title' => 'Pending Queue Event',
        'status' => 'pending',
    ]);

    $needsChangesEvent = Event::factory()->create([
        'title' => 'Needs Changes Queue Event',
        'status' => 'needs_changes',
    ]);

    $needsChangesEvent->moderationReviews()->create([
        'moderator_id' => $moderator->id,
        'decision' => 'needs_changes',
        'reason_code' => 'incomplete_info',
        'note' => 'Please update venue details.',
    ]);

    $this->actingAs($moderator)
        ->get('/admin/moderation-queue?tab=needs_changes')
        ->assertSuccessful()
        ->assertSee($needsChangesEvent->title)
        ->assertSee('Please update venue details.')
        ->assertSee('Return to Pending')
        ->assertDontSee($pendingEvent->title);
});

it('shows only events with open reports on the reports tab', function () {
    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    $reporter = User::factory()->create();

    $reportedEvent = Event::factory()->create([
        'title' => 'Reported Approved Event',
        'status' => 'approved',
    ]);

    $reportedEvent->reports()->create([
        'reporter_id' => $reporter->id,
        'category' => 'other',
        'description' => 'Potentially misleading event information.',
        'status' => 'open',
    ]);

    $notOpenReportedEvent = Event::factory()->create([
        'title' => 'Resolved Report Event',
        'status' => 'pending',
    ]);

    $notOpenReportedEvent->reports()->create([
        'reporter_id' => $reporter->id,
        'handled_by' => $moderator->id,
        'category' => 'other',
        'description' => 'Already handled.',
        'status' => 'resolved',
        'resolution_note' => 'Reviewed and resolved.',
    ]);

    $this->actingAs($moderator)
        ->get('/admin/moderation-queue?tab=reports')
        ->assertSuccessful()
        ->assertSee($reportedEvent->title)
        ->assertDontSee($notOpenReportedEvent->title);
});

it('links moderation queue view action to the event infolist page', function () {
    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    $event = Event::factory()->create([
        'status' => 'pending',
        'title' => 'Pending Event For View Link',
    ]);

    $this->actingAs($moderator)
        ->get('/admin/moderation-queue')
        ->assertSuccessful()
        ->assertSee("/admin/events/{$event->id}");
});
