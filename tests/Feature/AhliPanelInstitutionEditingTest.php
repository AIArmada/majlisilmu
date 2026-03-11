<?php

use AIArmada\FilamentAuthz\Facades\Authz;
use AIArmada\FilamentAuthz\Models\Permission;
use App\Filament\Ahli\Resources\Events\EventResource;
use App\Filament\Ahli\Resources\Events\Pages\ViewEvent as AhliViewEvent;
use App\Filament\Ahli\Resources\Institutions\InstitutionResource;
use App\Filament\Ahli\Resources\Institutions\Pages\EditInstitution as AhliEditInstitution;
use App\Filament\Resources\Institutions\RelationManagers\DonationChannelsRelationManager;
use App\Models\Event;
use App\Models\EventSubmission;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Registration;
use App\Models\Series;
use App\Models\Speaker;
use App\Models\User;
use App\Models\Venue;
use App\Support\Authz\MemberRoleScopes;
use App\Support\Authz\ScopedMemberRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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
    $eventViewUrl = EventResource::getUrl('view', ['record' => $event], panel: 'ahli');
    $eventEditUrl = EventResource::getUrl('edit', ['record' => $event], panel: 'ahli');

    $this->actingAs($user)
        ->get($institutionEditUrl)
        ->assertOk();

    $this->actingAs($user)
        ->get($eventViewUrl)
        ->assertOk();

    $this->actingAs($user)
        ->get($eventEditUrl)
        ->assertOk();
});

it('opens the ahli view public page action in a new tab', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create();
    $event = Event::factory()->for($institution)->create([
        'title' => 'Ahli View Public Event',
        'status' => 'approved',
        'visibility' => 'public',
        'organizer_type' => Institution::class,
        'organizer_id' => $institution->id,
    ]);

    $institution->members()->syncWithoutDetaching([$user->id]);

    app(ScopedMemberRoleSeeder::class)->ensureForInstitution();

    $institutionScope = app(MemberRoleScopes::class)->institution();

    Authz::withScope($institutionScope, function () use ($user): void {
        $user->syncRoles(['admin']);
    }, $user);

    $eventEditUrl = EventResource::getUrl('edit', ['record' => $event], panel: 'ahli');
    $publicUrl = route('events.show', $event);

    $this->actingAs($user)
        ->get($eventEditUrl)
        ->assertOk()
        ->assertSee($publicUrl, false)
        ->assertSee('target="_blank"', false);
});

it('renders submitter phone numbers as whatsapp links on the ahli event edit page', function () {
    $member = User::factory()->create();
    $submitter = User::factory()->create([
        'phone' => '60112233445',
    ]);
    $institution = Institution::factory()->create();
    $event = Event::factory()->for($institution)->create([
        'title' => 'Ahli Submitter Contact Event',
        'status' => 'pending',
        'visibility' => 'public',
        'organizer_type' => Institution::class,
        'organizer_id' => $institution->id,
        'submitter_id' => $submitter->id,
    ]);

    EventSubmission::factory()
        ->for($event)
        ->for($submitter, 'submitter')
        ->create([
            'submitter_name' => $submitter->name,
        ]);

    $institution->members()->syncWithoutDetaching([$member->id]);

    app(ScopedMemberRoleSeeder::class)->ensureForInstitution();

    $institutionScope = app(MemberRoleScopes::class)->institution();

    Authz::withScope($institutionScope, function () use ($member): void {
        $member->syncRoles(['admin']);
    }, $member);

    $eventEditUrl = EventResource::getUrl('edit', ['record' => $event], panel: 'ahli');

    $this->actingAs($member)
        ->get($eventEditUrl)
        ->assertOk()
        ->assertSee('https://wa.me/60112233445');
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
        ->assertSee('Create Advanced Program')
        ->assertDontSee($outsideTitle)
        ->assertDontSee('Outside Speaker Scope Event');
});

it('hides the advanced create action for ahli users who only have event membership scope', function () {
    $user = User::factory()->create();
    $memberEvent = Event::factory()->create([
        'title' => 'Scoped Event Membership Event Only',
        'status' => 'draft',
        'visibility' => 'private',
    ]);

    $user->memberEvents()->syncWithoutDetaching([$memberEvent->id => ['joined_at' => now()]]);

    $eventsIndexUrl = EventResource::getUrl('index', panel: 'ahli');

    $this->actingAs($user)
        ->get($eventsIndexUrl)
        ->assertOk()
        ->assertDontSee('Create Advanced Program');
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
    $registrant = User::factory()->create();
    $institution = Institution::factory()->create(['name' => 'Masjid Link Ahli']);
    $event = Event::factory()->for($institution)->create([
        'title' => 'Event Link Ahli',
        'status' => 'draft',
        'visibility' => 'private',
    ]);
    $event->settings()->updateOrCreate(
        ['event_id' => $event->id],
        ['registration_required' => true],
    );
    Registration::factory()->for($event)->for($registrant)->create([
        'name' => 'Registrations Table User',
        'email' => 'registrations-table@example.test',
        'status' => 'registered',
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
    $eventRegistrationsUrl = EventResource::getUrl('view', ['record' => $event, 'relation' => 'registrations'], panel: 'ahli');

    $this->actingAs($adminUser)
        ->get(route('dashboard.institutions', ['institution' => $institution->id]))
        ->assertOk()
        ->assertSee(__('Edit Institution'))
        ->assertSee(__('Edit'))
        ->assertDontSee(__('Edit in Ahli Panel'))
        ->assertSee($institutionEditUrl, false)
        ->assertSee($eventEditUrl, false)
        ->assertSee($eventRegistrationsUrl, false);

    $this->actingAs($adminUser)
        ->get($eventRegistrationsUrl)
        ->assertOk();

    Livewire::withQueryParams(['relation' => 'registrations'])
        ->test(AhliViewEvent::class, ['record' => $event->id])
        ->assertSet('activeRelationManager', 'registrations');

    $this->actingAs($viewerUser)
        ->get(route('dashboard.institutions', ['institution' => $institution->id]))
        ->assertOk()
        ->assertDontSee(__('Edit Institution'))
        ->assertDontSee(__('Edit'))
        ->assertDontSee(__('Edit in Ahli Panel'))
        ->assertDontSee($institutionEditUrl, false)
        ->assertDontSee($eventEditUrl, false)
        ->assertDontSee($eventRegistrationsUrl, false);
});

it('shows the donation channels relation manager on the ahli institution edit page for institution admins', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create();

    $institution->members()->syncWithoutDetaching([$user->id]);

    app(ScopedMemberRoleSeeder::class)->ensureForInstitution();

    $institutionScope = app(MemberRoleScopes::class)->institution();

    Authz::withScope($institutionScope, function () use ($user): void {
        $user->syncRoles(['admin']);
    }, $user);

    $component = Livewire::actingAs($user)
        ->test(AhliEditInstitution::class, ['record' => $institution->id]);

    $relationManagers = $component->instance()->getRelationManagers();

    expect($relationManagers)->toContain(DonationChannelsRelationManager::class);
});

it('shows review instead of edit for pending institution events on the dashboard', function () {
    $adminUser = User::factory()->create();
    $institution = Institution::factory()->create(['name' => 'Masjid Review Label']);
    $event = Event::factory()->for($institution)->create([
        'title' => 'Pending Review Label Event',
        'status' => 'pending',
        'visibility' => 'public',
    ]);

    $institution->members()->syncWithoutDetaching([$adminUser->id]);

    app(ScopedMemberRoleSeeder::class)->ensureForInstitution();

    $institutionScope = app(MemberRoleScopes::class)->institution();

    Authz::withScope($institutionScope, function () use ($adminUser): void {
        $adminUser->syncRoles(['admin']);
    }, $adminUser);

    $this->actingAs($adminUser)
        ->get(route('dashboard.institutions', ['institution' => $institution->id]))
        ->assertOk()
        ->assertSee('Pending Review Label Event')
        ->assertSee(__('Review'))
        ->assertDontSee(__('Edit in Ahli Panel'));
});

it('renders the ahli event view page when related resources do not exist in the ahli panel', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create();
    $speaker = Speaker::factory()->create();
    $series = Series::factory()->create();
    $reference = Reference::factory()->create();
    $venue = Venue::factory()->create();

    $event = Event::factory()->for($institution)->for($venue)->create([
        'title' => 'Ahli View Safe Related Links Event',
        'status' => 'draft',
        'visibility' => 'private',
        'organizer_type' => Speaker::class,
        'organizer_id' => $speaker->id,
    ]);

    $event->speakers()->attach($speaker->id);
    $event->series()->attach($series->id);
    $event->references()->attach($reference->id);

    $institution->members()->syncWithoutDetaching([$user->id]);

    app(ScopedMemberRoleSeeder::class)->ensureForInstitution();

    $institutionScope = app(MemberRoleScopes::class)->institution();

    Authz::withScope($institutionScope, function () use ($user): void {
        $user->syncRoles(['admin']);
    }, $user);

    $eventViewUrl = EventResource::getUrl('view', ['record' => $event], panel: 'ahli');

    $this->actingAs($user)
        ->get($eventViewUrl)
        ->assertOk()
        ->assertSee('Ahli View Safe Related Links Event')
        ->assertSee($institution->name)
        ->assertSee($speaker->name)
        ->assertSee($venue->name)
        ->assertSee($series->title)
        ->assertSee($reference->title);
});
