<?php

use App\Models\Event;
use App\Models\EventMember;
use App\Models\User;

it('can create an event member', function () {
    $event = Event::factory()->create();
    $user = User::factory()->create();

    $member = EventMember::factory()
        ->for($event)
        ->for($user)
        ->organizer()
        ->create();

    expect($member->event_id)->toBe($event->id)
        ->and($member->user_id)->toBe($user->id)
        ->and($member->role)->toBe('organizer');
});

it('has event relationship', function () {
    $member = EventMember::factory()->create();

    expect($member->event)->toBeInstanceOf(Event::class);
});

it('has user relationship', function () {
    $member = EventMember::factory()->create();

    expect($member->user)->toBeInstanceOf(User::class);
});

it('can check if member is organizer', function () {
    $organizer = EventMember::factory()->organizer()->create();
    $volunteer = EventMember::factory()->volunteer()->create();

    expect($organizer->isOrganizer())->toBeTrue()
        ->and($volunteer->isOrganizer())->toBeFalse();
});

it('can check if member is co-organizer', function () {
    $coOrganizer = EventMember::factory()->coOrganizer()->create();
    $member = EventMember::factory()->create(['role' => 'member']);

    expect($coOrganizer->isCoOrganizer())->toBeTrue()
        ->and($member->isCoOrganizer())->toBeFalse();
});

it('can check if member can manage event', function () {
    $organizer = EventMember::factory()->organizer()->create();
    $coOrganizer = EventMember::factory()->coOrganizer()->create();
    $volunteer = EventMember::factory()->volunteer()->create();

    expect($organizer->canManageEvent())->toBeTrue()
        ->and($coOrganizer->canManageEvent())->toBeTrue()
        ->and($volunteer->canManageEvent())->toBeFalse();
});

it('enforces unique event and user combination', function () {
    $event = Event::factory()->create();
    $user = User::factory()->create();

    EventMember::factory()->for($event)->for($user)->create();

    // Attempting to create duplicate should fail
    EventMember::factory()->for($event)->for($user)->create();
})->throws(\Illuminate\Database\UniqueConstraintViolationException::class);

it('can access members from event', function () {
    $event = Event::factory()->create();
    $users = User::factory()->count(3)->create();

    foreach ($users as $user) {
        EventMember::factory()->for($event)->for($user)->create();
    }

    expect($event->members)->toHaveCount(3);
});

it('can access member events from user', function () {
    $user = User::factory()->create();
    $events = Event::factory()->count(2)->create();

    foreach ($events as $event) {
        EventMember::factory()->for($event)->for($user)->create();
    }

    expect($user->memberEvents)->toHaveCount(2);
});

it('includes pivot data in relationships', function () {
    $event = Event::factory()->create();
    $user = User::factory()->create();

    EventMember::factory()
        ->for($event)
        ->for($user)
        ->organizer()
        ->create(['joined_at' => now()]);

    $memberFromEvent = $event->members->first();

    expect($memberFromEvent->pivot->role)->toBe('organizer')
        ->and($memberFromEvent->pivot->joined_at)->not->toBeNull();
});
