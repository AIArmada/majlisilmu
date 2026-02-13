<?php

use App\Enums\EventVisibility;
use App\Livewire\Pages\Events\Show;
use App\Models\Event;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->otherUser = User::factory()->create();
});

// Public Event Tests
describe('public events', function () {
    it('allows anyone to view public events', function () {
        $event = Event::factory()->create([
            'visibility' => EventVisibility::Public,
            'is_active' => true,
            'status' => 'approved',
        ]);

        // Guest user
        Livewire::test(Show::class, ['event' => $event])
            ->assertSuccessful();
    });

    it('allows owner to view their public event', function () {
        $event = Event::factory()->create([
            'user_id' => $this->owner->id,
            'visibility' => EventVisibility::Public,
            'is_active' => true,
            'status' => 'approved',
        ]);

        Livewire::actingAs($this->owner)
            ->test(Show::class, ['event' => $event])
            ->assertSuccessful();
    });
});

// Unlisted Event Tests
describe('unlisted events', function () {
    it('allows anyone to view unlisted events via direct link', function () {
        $event = Event::factory()->create([
            'visibility' => EventVisibility::Unlisted,
            'is_active' => true,
            'status' => 'approved',
        ]);

        // Guest user
        Livewire::test(Show::class, ['event' => $event])
            ->assertSuccessful();
    });

    it('allows owner to view their unlisted event', function () {
        $event = Event::factory()->create([
            'submitter_id' => $this->owner->id,
            'visibility' => EventVisibility::Unlisted,
            'is_active' => true,
            'status' => 'approved',
        ]);

        Livewire::actingAs($this->owner)
            ->test(Show::class, ['event' => $event])
            ->assertSuccessful();
    });

    it('returns 404 for unlisted events when inactive', function () {
        $event = Event::factory()->create([
            'visibility' => EventVisibility::Unlisted,
            'is_active' => false,
            'status' => 'approved',
        ]);

        Livewire::test(Show::class, ['event' => $event])
            ->assertStatus(404);
    });
});

// Private Event Tests
describe('private events', function () {
    it('allows owner to view their private event', function () {
        $event = Event::factory()->create([
            'user_id' => $this->owner->id,
            'visibility' => EventVisibility::Private,
            'is_active' => true,
            'status' => 'approved',
        ]);

        Livewire::actingAs($this->owner)
            ->test(Show::class, ['event' => $event])
            ->assertSuccessful();
    });

    it('allows submitter to view their private event', function () {
        $event = Event::factory()->create([
            'submitter_id' => $this->owner->id,
            'visibility' => EventVisibility::Private,
            'is_active' => true,
            'status' => 'approved',
        ]);

        Livewire::actingAs($this->owner)
            ->test(Show::class, ['event' => $event])
            ->assertSuccessful();
    });

    it('returns 404 for other users viewing private event', function () {
        $event = Event::factory()->create([
            'user_id' => $this->owner->id,
            'visibility' => EventVisibility::Private,
            'is_active' => true,
            'status' => 'approved',
        ]);

        Livewire::actingAs($this->otherUser)
            ->test(Show::class, ['event' => $event])
            ->assertStatus(404);
    });

    it('returns 404 for guests viewing private event', function () {
        $event = Event::factory()->create([
            'visibility' => EventVisibility::Private,
            'is_active' => true,
            'status' => 'approved',
        ]);

        Livewire::test(Show::class, ['event' => $event])
            ->assertStatus(404);
    });

    it('returns 404 for private events when inactive even for owner', function () {
        $event = Event::factory()->create([
            'user_id' => $this->owner->id,
            'visibility' => EventVisibility::Private,
            'is_active' => false,
            'status' => 'approved',
        ]);

        Livewire::actingAs($this->owner)
            ->test(Show::class, ['event' => $event])
            ->assertStatus(404);
    });
});

// Common Tests
describe('inactive or draft events', function () {
    it('returns 404 for inactive public events', function () {
        $event = Event::factory()->create([
            'visibility' => EventVisibility::Public,
            'is_active' => false,
            'status' => 'approved',
        ]);

        Livewire::test(Show::class, ['event' => $event])
            ->assertStatus(404);
    });

    it('returns 404 for draft status events', function () {
        $event = Event::factory()->create([
            'visibility' => EventVisibility::Public,
            'is_active' => true,
            'status' => 'draft',
        ]);

        Livewire::test(Show::class, ['event' => $event])
            ->assertStatus(404);
    });
});
