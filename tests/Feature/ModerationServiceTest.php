<?php

use App\Models\Event;
use App\Models\Institution;
use App\Models\ModerationReview;
use App\Models\User;
use App\Notifications\EventApprovedNotification;
use App\Notifications\EventNeedsChangesNotification;
use App\Notifications\EventRejectedNotification;
use App\Notifications\EventSubmittedNotification;
use App\Services\ModerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    Notification::fake();
    $this->service = new ModerationService;
});

describe('Event Submission', function () {
    it('sets event to pending and notifies moderators', function () {
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');

        $institution = Institution::factory()->create();

        // Create with initial state string, factory should handle this if cast properly
        // However, factory sets string. Spatie writes string to DB.
        $event = Event::factory()->create([
            'institution_id' => $institution->id,
            'status' => 'draft',
            'published_at' => null,
        ]);

        $this->service->submitForModeration($event);

        expect((string) $event->fresh()->status)->toBe('pending');
        Notification::assertSentTo($moderator, EventSubmittedNotification::class);
    });

    it('does not auto-approve events during submission', function () {
        $institution = Institution::factory()->create();

        $event = Event::factory()->create([
            'institution_id' => $institution->id,
            'status' => 'draft',
            'starts_at' => now()->addDays(7),
            'title' => 'Moderation test '.uniqid(),
            'published_at' => null,
        ]);

        $this->service->submitForModeration($event);

        expect((string) $event->fresh()->status)->toBe('pending');
        expect($event->fresh()->published_at)->toBeNull();
    });
});

describe('Event Approval', function () {
    it('creates review record and updates status', function () {
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');

        $event = Event::factory()->create([
            'status' => 'pending',
        ]);

        // Service returns void now (transitions handle logic), so we check DB for review
        $this->service->approve($event, $moderator, 'Looks good!');

        $review = ModerationReview::where('event_id', $event->id)->latest()->first();

        expect($review)->toBeInstanceOf(ModerationReview::class);
        expect($review->decision)->toBe('approved');
        expect((string) $event->fresh()->status)->toBe('approved');
        expect($event->fresh()->published_at)->not->toBeNull();
    });

    it('notifies submitter on approval', function () {
        $submitter = User::factory()->create();

        $event = Event::factory()->create([
            'status' => 'pending',
            'submitter_id' => $submitter->id,
        ]);

        $this->service->approve($event);

        Notification::assertSentTo($submitter, EventApprovedNotification::class);
    });

    it('auto-verifies pending related records on approval', function () {
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');

        // Create pending speaker
        $speaker = \App\Models\Speaker::factory()->create([
            'status' => 'pending',
        ]);

        // Create pending institution (organizer)
        $organizerInstitution = Institution::factory()->create([
            'status' => 'pending',
        ]);

        // Create pending institution (location)
        $locationInstitution = Institution::factory()->create([
            'status' => 'pending',
        ]);

        // Create pending venue
        $venue = \App\Models\Venue::factory()->create([
            'status' => 'pending',
        ]);

        // Create pending tags
        $disciplineTag = \App\Models\Tag::create([
            'name' => ['ms' => 'Pending Fiqh', 'en' => 'Pending Fiqh'],
            'type' => 'discipline',
            'status' => 'pending',
        ]);

        $issueTag = \App\Models\Tag::create([
            'name' => ['ms' => 'Pending Issue', 'en' => 'Pending Issue'],
            'type' => 'issue',
            'status' => 'pending',
        ]);

        $event = Event::factory()->create([
            'status' => 'pending',
            'organizer_type' => Institution::class,
            'organizer_id' => $organizerInstitution->id,
            'institution_id' => $locationInstitution->id,
            'venue_id' => $venue->id,
        ]);

        $event->speakers()->attach($speaker->id);
        $event->syncTags([$disciplineTag, $issueTag]);

        // Approve event
        $this->service->approve($event, $moderator);

        // Verify all related records are now verified
        expect($speaker->fresh()->status)->toBe('verified')
            ->and($organizerInstitution->fresh()->status)->toBe('verified')
            ->and($locationInstitution->fresh()->status)->toBe('verified')
            ->and($venue->fresh()->status)->toBe('verified')
            ->and($disciplineTag->fresh()->status)->toBe('verified')
            ->and($issueTag->fresh()->status)->toBe('verified');
    });

    it('does not change already verified records on approval', function () {
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');

        // Create already verified speaker
        $speaker = \App\Models\Speaker::factory()->create([
            'status' => 'verified',
        ]);

        $event = Event::factory()->create([
            'status' => 'pending',
        ]);

        $event->speakers()->attach($speaker->id);

        $this->service->approve($event, $moderator);

        // Should remain verified
        expect($speaker->fresh()->status)->toBe('verified');
    });
});

describe('Event Needs Changes', function () {
    it('creates review with reason and notifies', function () {
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');

        $submitter = User::factory()->create();

        $event = Event::factory()->create([
            'status' => 'pending',
            'submitter_id' => $submitter->id,
        ]);

        $this->service->requestChanges(
            $event,
            $moderator,
            'incomplete_info',
            'Please add speaker details'
        );

        $review = ModerationReview::where('event_id', $event->id)->latest()->first();

        expect($review->decision)->toBe('needs_changes');
        expect($review->reason_code)->toBe('incomplete_info');
        expect((string) $event->fresh()->status)->toBe('needs_changes');

        Notification::assertSentTo($submitter, EventNeedsChangesNotification::class);
    });
});

describe('Event Rejection', function () {
    it('rejects event and notifies submitter', function () {
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');

        $submitter = User::factory()->create();

        $event = Event::factory()->create([
            'status' => 'pending',
            'submitter_id' => $submitter->id,
        ]);

        $this->service->reject(
            $event,
            $moderator,
            'spam',
            'This appears to be spam.'
        );

        $review = ModerationReview::where('event_id', $event->id)->latest()->first();

        expect($review->decision)->toBe('rejected');
        expect((string) $event->fresh()->status)->toBe('rejected');

        Notification::assertSentTo($submitter, EventRejectedNotification::class);
    });
});

describe('Sensitive Change Handling', function () {
    it('sets approved event back to pending on sensitive change', function () {
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');

        $event = Event::factory()->create([
            'status' => 'approved',
            'published_at' => now(),
        ]);

        $this->service->handleSensitiveChange($event, [
            'venue_id' => 'new-venue-id',
        ]);

        expect((string) $event->fresh()->status)->toBe('pending');

        Notification::assertSentTo($moderator, EventSubmittedNotification::class);
    });

    it('creates review record for sensitive changes', function () {
        $event = Event::factory()->create([
            'status' => 'approved',
        ]);

        $this->service->handleSensitiveChange($event, [
            'starts_at' => now()->addDays(1),
        ]);

        $review = ModerationReview::where('event_id', $event->id)->latest()->first();

        expect($review)->not->toBeNull();
        expect($review->decision)->toBe('pending_review');
        expect($review->note)->toContain('starts_at');
    });

    it('ignores non-sensitive changes', function () {
        $event = Event::factory()->create([
            'status' => 'approved',
        ]);

        $this->service->handleSensitiveChange($event, [
            'title' => 'New Title',
        ]);

        // Status should remain approved
        expect((string) $event->fresh()->status)->toBe('approved');
    });
});
