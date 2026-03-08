<?php

use AIArmada\FilamentAuthz\Facades\Authz;
use AIArmada\FilamentAuthz\Models\Permission;
use App\Filament\Ahli\Resources\Events\EventResource;
use App\Filament\Ahli\Resources\Institutions\InstitutionResource;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\User;
use App\Support\Authz\MemberRoleScopes;
use App\Support\Authz\ScopedMemberRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('allows institution admins to open ahli edit pages for their institution and events', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create();
    $event = Event::factory()->for($institution)->create([
        'title' => 'Ahli Managed Event',
        'status' => 'draft',
        'visibility' => 'private',
        'organizer_type' => Institution::class,
        'organizer_id' => $institution->id,
    ]);

    $institution->members()->syncWithoutDetaching([$user->id]);

    app(ScopedMemberRoleSeeder::class)->ensureForInstitution();

    $institutionScope = app(MemberRoleScopes::class)->institution();

    Authz::withScope($institutionScope, function () use ($user): void {
        $user->syncRoles(['admin']);
    }, $user);

    $institutionEditUrl = InstitutionResource::getUrl('edit', ['record' => $institution], panel: 'ahli');
    $eventEditUrl = EventResource::getUrl('edit', ['record' => $event], panel: 'ahli');

    $this->actingAs($user)
        ->get($institutionEditUrl)
        ->assertOk();

    $this->actingAs($user)
        ->get($eventEditUrl)
        ->assertOk();
});

it('allows institution admins to open ahli edit page for speaker-organized events linked to their institution', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create();
    $speaker = Speaker::factory()->create();
    $event = Event::factory()->for($institution)->create([
        'title' => 'Institution Scoped Speaker Event',
        'status' => 'draft',
        'visibility' => 'private',
        'organizer_type' => Speaker::class,
        'organizer_id' => $speaker->id,
    ]);

    $institution->members()->syncWithoutDetaching([$user->id]);

    app(ScopedMemberRoleSeeder::class)->ensureForInstitution();

    $institutionScope = app(MemberRoleScopes::class)->institution();

    Authz::withScope($institutionScope, function () use ($user): void {
        $user->syncRoles(['admin']);
    }, $user);

    $eventEditUrl = EventResource::getUrl('edit', ['record' => $event], panel: 'ahli');

    $this->actingAs($user)
        ->get($eventEditUrl)
        ->assertOk();
});

it('forbids non-member submitters from opening ahli edit page for their own submitted event', function () {
    $user = User::factory()->create();
    $foreignInstitution = Institution::factory()->create();
    $event = Event::factory()->for($foreignInstitution)->create([
        'title' => 'Submitter Managed Event',
        'status' => 'draft',
        'visibility' => 'private',
        'submitter_id' => $user->id,
    ]);

    $eventEditUrl = EventResource::getUrl('edit', ['record' => $event], panel: 'ahli');

    $this->actingAs($user)
        ->get($eventEditUrl)
        ->assertForbidden();
});

it('allows speaker members to open ahli edit page for speaker-organized events', function () {
    $user = User::factory()->create();
    $speaker = Speaker::factory()->create();
    $event = Event::factory()->create([
        'title' => 'Speaker Organized Event',
        'status' => 'draft',
        'visibility' => 'private',
        'organizer_type' => Speaker::class,
        'organizer_id' => $speaker->id,
    ]);

    $speaker->members()->syncWithoutDetaching([$user->id]);

    app(ScopedMemberRoleSeeder::class)->ensureForSpeaker();

    $speakerScope = app(MemberRoleScopes::class)->speaker();

    Authz::withScope($speakerScope, function () use ($user): void {
        Permission::findOrCreate('event.update', 'web');
        $user->givePermissionTo('event.update');
    }, $user);

    $eventEditUrl = EventResource::getUrl('edit', ['record' => $event], panel: 'ahli');

    $this->actingAs($user)
        ->get($eventEditUrl)
        ->assertOk();
});

it('allows event members to open ahli edit page for their scoped event membership', function () {
    $user = User::factory()->create();
    $event = Event::factory()->create([
        'title' => 'Event Member Scoped Event',
        'status' => 'draft',
        'visibility' => 'private',
    ]);

    $event->members()->syncWithoutDetaching([$user->id => ['joined_at' => now()]]);

    app(ScopedMemberRoleSeeder::class)->ensureForEvent();

    $eventScope = app(MemberRoleScopes::class)->event();

    Authz::withScope($eventScope, function () use ($user): void {
        $user->syncRoles(['organizer']);
    }, $user);

    $eventEditUrl = EventResource::getUrl('edit', ['record' => $event], panel: 'ahli');

    $this->actingAs($user)
        ->get($eventEditUrl)
        ->assertOk();
});

it('lists only scoped events on ahli events index', function () {
    $user = User::factory()->create();
    $memberInstitution = Institution::factory()->create();
    $otherInstitution = Institution::factory()->create();
    $memberSpeaker = Speaker::factory()->create();
    $otherSpeaker = Speaker::factory()->create();
    $memberEvent = Event::factory()->create([
        'title' => 'Scoped Event Membership Event',
        'status' => 'draft',
        'visibility' => 'private',
    ]);

    $user->institutions()->syncWithoutDetaching([$memberInstitution->id]);
    $user->speakers()->syncWithoutDetaching([$memberSpeaker->id]);
    $user->memberEvents()->syncWithoutDetaching([$memberEvent->id => ['joined_at' => now()]]);

    $submittedTitle = 'Scoped Submitted Event';
    $institutionTitle = 'Scoped Institution Organizer Event';
    $speakerTitle = 'Scoped Speaker Organizer Event';
    $institutionLinkedSpeakerTitle = 'Institution Linked Speaker Event';
    $eventMemberTitle = 'Scoped Event Membership Event';
    $outsideTitle = 'Outside Scope Event';

    Event::factory()->create([
        'title' => $submittedTitle,
        'status' => 'draft',
        'visibility' => 'private',
        'submitter_id' => $user->id,
    ]);

    Event::factory()->create([
        'title' => $institutionTitle,
        'status' => 'draft',
        'visibility' => 'private',
        'organizer_type' => Institution::class,
        'organizer_id' => $memberInstitution->id,
    ]);

    Event::factory()->create([
        'title' => $speakerTitle,
        'status' => 'draft',
        'visibility' => 'private',
        'organizer_type' => Speaker::class,
        'organizer_id' => $memberSpeaker->id,
    ]);

    Event::factory()->for($memberInstitution)->create([
        'title' => $institutionLinkedSpeakerTitle,
        'status' => 'draft',
        'visibility' => 'private',
        'organizer_type' => Speaker::class,
        'organizer_id' => $otherSpeaker->id,
    ]);

    Event::factory()->create([
        'title' => $outsideTitle,
        'status' => 'draft',
        'visibility' => 'private',
        'organizer_type' => Institution::class,
        'organizer_id' => $otherInstitution->id,
    ]);

    Event::factory()->create([
        'title' => 'Outside Speaker Scope Event',
        'status' => 'draft',
        'visibility' => 'private',
        'organizer_type' => Speaker::class,
        'organizer_id' => $otherSpeaker->id,
    ]);

    $eventsIndexUrl = EventResource::getUrl('index', panel: 'ahli');

    $this->actingAs($user)
        ->get($eventsIndexUrl)
        ->assertOk()
        ->assertSee($submittedTitle)
        ->assertSee($institutionTitle)
        ->assertSee($speakerTitle)
        ->assertSee($institutionLinkedSpeakerTitle)
        ->assertSee($eventMemberTitle)
        ->assertDontSee($outsideTitle)
        ->assertDontSee('Outside Speaker Scope Event');
});

it('does not allow editing institutions outside user membership in ahli panel', function () {
    $user = User::factory()->create();
    $memberInstitution = Institution::factory()->create();
    $otherInstitution = Institution::factory()->create();
    $otherEvent = Event::factory()->for($otherInstitution)->create([
        'title' => 'External Event',
        'status' => 'draft',
        'visibility' => 'private',
        'organizer_type' => Institution::class,
        'organizer_id' => $otherInstitution->id,
    ]);

    $memberInstitution->members()->syncWithoutDetaching([$user->id]);

    app(ScopedMemberRoleSeeder::class)->ensureForInstitution();

    $institutionScope = app(MemberRoleScopes::class)->institution();

    Authz::withScope($institutionScope, function () use ($user): void {
        $user->syncRoles(['admin']);
    }, $user);

    $otherInstitutionEditUrl = InstitutionResource::getUrl('edit', ['record' => $otherInstitution], panel: 'ahli');
    $otherEventEditUrl = EventResource::getUrl('edit', ['record' => $otherEvent], panel: 'ahli');

    $this->actingAs($user)
        ->get($otherInstitutionEditUrl)
        ->assertNotFound();

    $this->actingAs($user)
        ->get($otherEventEditUrl)
        ->assertNotFound();
});

it('shows ahli edit links on institution dashboard only when user can update', function () {
    $adminUser = User::factory()->create();
    $viewerUser = User::factory()->create();
    $institution = Institution::factory()->create(['name' => 'Masjid Link Ahli']);
    $event = Event::factory()->for($institution)->create([
        'title' => 'Event Link Ahli',
        'status' => 'draft',
        'visibility' => 'private',
    ]);

    $institution->members()->syncWithoutDetaching([$adminUser->id, $viewerUser->id]);

    app(ScopedMemberRoleSeeder::class)->ensureForInstitution();

    $institutionScope = app(MemberRoleScopes::class)->institution();

    Authz::withScope($institutionScope, function () use ($adminUser): void {
        $adminUser->syncRoles(['admin']);
    }, $adminUser);

    Authz::withScope($institutionScope, function () use ($viewerUser): void {
        $viewerUser->syncRoles(['viewer']);
    }, $viewerUser);

    $institutionEditUrl = InstitutionResource::getUrl('edit', ['record' => $institution], panel: 'ahli');
    $eventEditUrl = EventResource::getUrl('edit', ['record' => $event], panel: 'ahli');

    $this->actingAs($adminUser)
        ->get('/dashboard/institutions?institution='.$institution->id)
        ->assertOk()
        ->assertSee('Edit Institution')
        ->assertSee('Edit in Ahli Panel')
        ->assertSee($institutionEditUrl, false)
        ->assertSee($eventEditUrl, false);

    $this->actingAs($viewerUser)
        ->get('/dashboard/institutions?institution='.$institution->id)
        ->assertOk()
        ->assertDontSee('Edit Institution')
        ->assertDontSee('Edit in Ahli Panel')
        ->assertDontSee($institutionEditUrl, false)
        ->assertDontSee($eventEditUrl, false);
});
