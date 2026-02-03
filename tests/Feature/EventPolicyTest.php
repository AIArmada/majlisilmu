<?php

use App\Models\Event;
use App\Models\EventUser;
use App\Models\Institution;
use App\Models\User;
use App\States\EventStatus\Approved;
use App\States\EventStatus\Draft;
use App\States\EventStatus\Pending;

beforeEach(function () {
    // Disable teams to simplify role lookup for these tests
    config(['permission.teams' => false]);
    app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

    // Get the configured Role class
    $roleClass = app(\Spatie\Permission\PermissionRegistrar::class)->getRoleClass();

    // Create roles using the correct model
    if (! $roleClass::where('name', 'super_admin')->exists()) {
        $roleClass::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }
    if (! $roleClass::where('name', 'moderator')->exists()) {
        $roleClass::create(['name' => 'moderator', 'guard_name' => 'web']);
    }
});

describe('viewAny', function () {
    it('allows anyone to view event list', function () {
        $user = User::factory()->create();

        expect($user->can('viewAny', Event::class))->toBeTrue();
    });

    it('allows guests to view event list', function () {
        expect(\Illuminate\Support\Facades\Gate::forUser(null)->allows('viewAny', Event::class))->toBeTrue();
    });
});

describe('view', function () {
    it('allows anyone to view public approved events', function () {
        $event = Event::factory()->create([
            'visibility' => 'public',
            'status' => Approved::class,
        ]);

        expect(\Illuminate\Support\Facades\Gate::forUser(null)->allows('view', $event))->toBeTrue();
    });

    it('allows anyone to view unlisted events', function () {
        $event = Event::factory()->create([
            'visibility' => 'unlisted',
        ]);

        expect(\Illuminate\Support\Facades\Gate::forUser(null)->allows('view', $event))->toBeTrue();
    });

    it('denies guests from viewing private events', function () {
        $event = Event::factory()->create([
            'visibility' => 'private',
        ]);

        // Use Gate facade to test as guest (null user)
        expect(\Illuminate\Support\Facades\Gate::forUser(null)->allows('view', $event))->toBeFalse();
    });

    it('allows event members to view private events', function () {
        $event = Event::factory()->create([
            'visibility' => 'private',
        ]);
        $user = User::factory()->create();

        EventUser::factory()
            ->for($event)
            ->for($user)
            ->volunteer()
            ->create();

        expect($user->can('view', $event))->toBeTrue();
    });

    it('allows submitter to view their own submissions', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'visibility' => 'private',
            'submitter_id' => $user->id,
        ]);

        expect($user->can('view', $event))->toBeTrue();
    });
});

describe('create', function () {
    it('allows any authenticated user to create events', function () {
        $user = User::factory()->create();

        expect($user->can('create', Event::class))->toBeTrue();
    });
});

describe('update', function () {
    it('allows super_admin to update any event', function () {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $event = Event::factory()->create();

        expect($admin->can('update', $event))->toBeTrue();
    });

    it('allows moderator to update any event', function () {
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        $event = Event::factory()->create();

        expect($moderator->can('update', $event))->toBeTrue();
    });

    it('allows submitter to update their draft submissions', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'submitter_id' => $user->id,
            'status' => Draft::class,
        ]);

        expect($user->can('update', $event))->toBeTrue();
    });

    it('allows submitter to update their pending submissions', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'submitter_id' => $user->id,
            'status' => Pending::class,
        ]);

        expect($user->can('update', $event))->toBeTrue();
    });

    it('denies submitter from updating approved events', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'submitter_id' => $user->id,
            'status' => Approved::class,
        ]);

        expect($user->can('update', $event))->toBeFalse();
    });

    it('allows event organizer to update event', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'status' => Approved::class,
        ]);

        EventUser::factory()
            ->for($event)
            ->for($user)
            ->organizer()
            ->create();

        expect($user->can('update', $event))->toBeTrue();
    });

    it('allows event co-organizer to update event', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'status' => Approved::class,
        ]);

        EventUser::factory()
            ->for($event)
            ->for($user)
            ->coOrganizer()
            ->create();

        expect($user->can('update', $event))->toBeTrue();
    });

    it('denies volunteer from updating event', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'status' => Approved::class,
        ]);

        EventUser::factory()
            ->for($event)
            ->for($user)
            ->volunteer()
            ->create();

        expect($user->can('update', $event))->toBeFalse();
    });

    it('denies random user from updating event', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'status' => Approved::class,
        ]);

        expect($user->can('update', $event))->toBeFalse();
    });
});

describe('delete', function () {
    it('allows super_admin to delete any event', function () {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $event = Event::factory()->create([
            'status' => Approved::class,
        ]);

        expect($admin->can('delete', $event))->toBeTrue();
    });

    it('denies moderator from deleting approved events', function () {
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        $event = Event::factory()->create([
            'status' => Approved::class,
        ]);

        expect($moderator->can('delete', $event))->toBeFalse();
    });

    it('allows event organizer to delete draft events', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'status' => Draft::class,
        ]);

        EventUser::factory()
            ->for($event)
            ->for($user)
            ->organizer()
            ->create();

        expect($user->can('delete', $event))->toBeTrue();
    });

    it('denies co-organizer from deleting draft events', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'status' => Draft::class,
        ]);

        EventUser::factory()
            ->for($event)
            ->for($user)
            ->coOrganizer()
            ->create();

        expect($user->can('delete', $event))->toBeFalse();
    });

    it('denies organizer from deleting approved events', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'status' => Approved::class,
        ]);

        EventUser::factory()
            ->for($event)
            ->for($user)
            ->organizer()
            ->create();

        expect($user->can('delete', $event))->toBeFalse();
    });
});

describe('moderate', function () {
    it('allows super_admin to moderate events', function () {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $event = Event::factory()->create();

        expect($admin->can('moderate', $event))->toBeTrue();
    });

    it('allows moderator to moderate events', function () {
        $moderator = User::factory()->create();
        $moderator->assignRole('moderator');
        $event = Event::factory()->create();

        expect($moderator->can('moderate', $event))->toBeTrue();
    });

    it('denies regular users from moderating events', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        expect($user->can('moderate', $event))->toBeFalse();
    });
});

describe('manageMembers', function () {
    it('allows super_admin to manage event members', function () {
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');
        $event = Event::factory()->create();

        expect($admin->can('manageMembers', $event))->toBeTrue();
    });

    it('allows event organizer to manage members', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        EventUser::factory()
            ->for($event)
            ->for($user)
            ->organizer()
            ->create();

        expect($user->can('manageMembers', $event))->toBeTrue();
    });

    it('denies co-organizer from managing members', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        EventUser::factory()
            ->for($event)
            ->for($user)
            ->coOrganizer()
            ->create();

        expect($user->can('manageMembers', $event))->toBeFalse();
    });
});

describe('userCanManage helper', function () {
    it('returns true for organizer member', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        EventUser::factory()
            ->for($event)
            ->for($user)
            ->organizer()
            ->create();

        expect($event->userCanManage($user))->toBeTrue();
    });

    it('returns true for co-organizer member', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        EventUser::factory()
            ->for($event)
            ->for($user)
            ->coOrganizer()
            ->create();

        expect($event->userCanManage($user))->toBeTrue();
    });

    it('returns false for volunteer member', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        EventUser::factory()
            ->for($event)
            ->for($user)
            ->volunteer()
            ->create();

        expect($event->userCanManage($user))->toBeFalse();
    });

    it('returns false for non-member', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        expect($event->userCanManage($user))->toBeFalse();
    });
});
