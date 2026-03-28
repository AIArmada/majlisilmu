<?php

use AIArmada\FilamentAuthz\Facades\Authz;
use AIArmada\FilamentAuthz\Models\Role;
use App\Enums\EventStructure;
use App\Filament\Ahli\Resources\Events\EventResource;
use App\Livewire\Pages\Dashboard\InstitutionDashboard;
use App\Livewire\Pages\Dashboard\UserDashboard;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventSubmission;
use App\Models\Institution;
use App\Models\Registration;
use App\Models\SavedSearch;
use App\Models\User;
use App\Support\Authz\MemberRoleScopes;
use App\Support\Authz\ScopedMemberRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('requires authentication for user and institution dashboards', function () {
    expect(route('dashboard'))->toEndWith('/dashboard');
    expect(route('dashboard.account-settings'))->toEndWith('/tetapan-akaun');
    expect(route('dashboard.institutions'))->toEndWith('/dashboard/institusi');

    $this->get('/papan-pemuka')->assertRedirect(route('login'));
    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->get('/dashboard/notifications')->assertRedirect(route('login'));
    $this->get('/tetapan-akaun')->assertRedirect(route('login'));
    $this->get('/dashboard/institusi')->assertRedirect(route('login'));
    $this->get('/dashboard/events/create-advanced')->assertRedirect(route('login'));
});

it('redirects the legacy papan pemuka URL to the canonical dashboard URL for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/papan-pemuka')
        ->assertRedirect('/dashboard');
});

it('renders the attendee-first planner dashboard without saved search or digest panels', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $institution = Institution::factory()->create(['name' => 'Masjid Al-Hidayah']);
    $otherInstitution = Institution::factory()->create(['name' => 'Masjid Al-Furqan']);

    $user->institutions()->attach($institution->id);

    $savedEvent = Event::factory()->for($otherUser)->for($institution)->create([
        'title' => 'Saved Dashboard Event',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(2),
    ]);

    $goingEvent = Event::factory()->for($otherUser)->for($institution)->create([
        'title' => 'Going Dashboard Event',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(4),
    ]);

    $registeredEvent = Event::factory()->for($otherUser)->for($institution)->create([
        'title' => 'Registered Dashboard Event',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(5),
    ]);

    $submittedEvent = Event::factory()->for($user)->for($institution)->create([
        'title' => 'Submitted Dashboard Event',
        'submitter_id' => $user->id,
        'status' => 'pending',
        'visibility' => 'public',
        'starts_at' => now()->addDays(6),
    ]);

    EventSubmission::factory()->for($submittedEvent)->for($user, 'submitter')->create();

    $checkedInEvent = Event::factory()->for($otherUser)->for($institution)->create([
        'title' => 'Checked In Dashboard Event',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->subDays(1),
    ]);

    $managedOnlyEvent = Event::factory()->for($otherUser)->for($institution)->create([
        'title' => 'Institution Managed Event',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(8),
    ]);

    Event::factory()->for($otherUser)->for($otherInstitution)->create([
        'title' => 'External Event',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(9),
    ]);

    Registration::factory()->for($registeredEvent)->for($user)->create([
        'status' => 'registered',
    ]);

    $user->savedEvents()->attach($savedEvent->id);
    $user->goingEvents()->attach($goingEvent->id);

    EventCheckin::factory()->for($checkedInEvent)->for($user)->create([
        'checked_in_at' => now()->subDay(),
        'method' => 'registered_self_checkin',
    ]);

    SavedSearch::factory()->for($user)->create([
        'name' => 'Kuliah KL Daily',
    ]);

    SavedSearch::factory()->for($otherUser)->create([
        'name' => 'Other User Search',
    ]);

    $response = $this->withSession(['locale' => 'en'])
        ->actingAs($user)
        ->get('/dashboard');

    $response->assertOk()
        ->assertSee('Dashboard')
        ->assertDontSee('Attendee Planner')
        ->assertSee('Jump to section')
        ->assertSee('Overview Calendar')
        ->assertSee('Upcoming Agenda')
        ->assertDontSee('What needs your attention next')
        ->assertDontSee('Find more')
        ->assertSee('Account Settings')
        ->assertSee('Submitted Events')
        ->assertSee('Recent Check-ins')
        ->assertDontSee('Submitted + Check-ins')
        ->assertDontSee('Going + Registered')
        ->assertSee('Saved Searches')
        ->assertSee('Saved Dashboard Event')
        ->assertSee('Going Dashboard Event')
        ->assertSee('Registered Dashboard Event')
        ->assertSee('Submitted Dashboard Event')
        ->assertSee('Checked In Dashboard Event')
        ->assertDontSee('My Saved Searches')
        ->assertDontSee('Notification Preferences')
        ->assertDontSee('Kuliah KL Daily')
        ->assertDontSee('Institution Managed Event')
        ->assertDontSee('External Event');

    $response->assertDontSee('Other User Search');
    $response->assertDontSee('x-show="cell.entries.length > 0"', false);
    $response->assertDontSee("entry.role_badges.map(badge => badge.label).join(' • ')", false);
    $response->assertDontSee('bg-emerald-50/80', false);
});

it('renders a valid event management link on the user dashboard for manageable events', function () {
    $user = User::factory()->create();

    $event = Event::factory()->for($user)->create([
        'submitter_id' => $user->id,
        'status' => 'approved',
        'visibility' => 'public',
        'is_active' => true,
        'starts_at' => now()->addDays(3),
    ]);

    $expectedManagementUrl = EventResource::getUrl('view', ['record' => $event], panel: 'ahli');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee($expectedManagementUrl, false)
        ->assertDontSee('dashboard.events.schedule');
});

it('shows a featured next event without repeating it inside an otherwise empty agenda list', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create();

    $event = Event::factory()->for($institution)->create([
        'title' => 'Single Upcoming Planner Event',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(2),
    ]);

    $user->goingEvents()->attach($event->id);

    $response = $this->withSession(['locale' => 'en'])
        ->actingAs($user)
        ->get('/dashboard');

    $response->assertOk()
        ->assertSee('Single Upcoming Planner Event')
        ->assertSee('Your next event is already featured above.')
        ->assertDontSee('No events are on your immediate agenda.');
});

it('translates the attendee dashboard in Malay and only shows approved workflow status for submitted events', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create();
    $otherUser = User::factory()->create();

    $user->institutions()->attach($institution->id);

    $goingEvent = Event::factory()->for($otherUser)->for($institution)->create([
        'title' => 'Majlis Akan Hadir',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(2),
    ]);

    $submittedEvent = Event::factory()->for($user)->for($institution)->create([
        'title' => 'Majlis Dihantar Sendiri',
        'submitter_id' => $user->id,
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(3),
    ]);

    $user->goingEvents()->attach($goingEvent->id);

    EventSubmission::factory()->for($submittedEvent)->for($user, 'submitter')->create();

    $response = $this->withSession(['locale' => 'ms'])
        ->actingAs($user)
        ->get('/dashboard');

    $response->assertOk()
        ->assertSee('Dashboard')
        ->assertDontSee('Perancang Kehadiran')
        ->assertSee('Pergi ke bahagian')
        ->assertSee('Majlis Dihantar')
        ->assertSee('Dashboard Institusi')
        ->assertSee('Diluluskan')
        ->assertDontSee('Yang perlu anda urus selepas ini')
        ->assertDontSee('Lihat lagi')
        ->assertDontSee('Approved');

    $html = $response->getContent();

    expect($html)->toBeString();

    $html = (string) $html;
    $goingSectionText = str($html)
        ->after('id="planner-going"')
        ->before('id="planner-registered"')
        ->toString();
    $submittedSectionText = str($html)
        ->after('id="planner-submitted"')
        ->before('id="planner-checkins"')
        ->toString();
    preg_match('/href="'.preg_quote(route('dashboard'), '/').'".*?>(.*?)<\/a>/s', $html, $dashboardMenuMatch);
    preg_match('/href="'.preg_quote(route('dashboard.institutions'), '/').'".*?>(.*?)<\/a>/s', $html, $institutionDashboardMenuMatch);

    $dashboardMenuText = trim(strip_tags($dashboardMenuMatch[1] ?? ''));
    $institutionDashboardMenuText = trim(strip_tags($institutionDashboardMenuMatch[1] ?? ''));

    expect($goingSectionText)->not->toContain('Diluluskan')
        ->and($submittedSectionText)->toContain('Diluluskan')
        ->and($dashboardMenuText)->toBe('Dashboard')
        ->and($institutionDashboardMenuText)->toBe('Dashboard Institusi');
});

it('paginates agenda, planner buckets, submitted events, and check-in history when counts exceed the preview size', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $institution = Institution::factory()->create();

    foreach (range(1, 8) as $index) {
        $event = Event::factory()->for($otherUser)->for($institution)->create([
            'title' => 'Going Page Event '.$index,
            'status' => 'approved',
            'visibility' => 'public',
            'starts_at' => now()->addDays($index),
        ]);

        $user->goingEvents()->attach($event->id);
    }

    foreach (range(1, 5) as $index) {
        $event = Event::factory()->for($user)->for($institution)->create([
            'title' => 'Submitted Page Event '.$index,
            'submitter_id' => $user->id,
            'status' => 'approved',
            'visibility' => 'public',
            'starts_at' => now()->addDays(20 + $index),
        ]);

        EventSubmission::factory()->for($event)->for($user, 'submitter')->create([
            'created_at' => now()->subMinutes($index),
        ]);
    }

    foreach (range(1, 7) as $index) {
        $event = Event::factory()->for($otherUser)->for($institution)->create([
            'title' => 'Check-in History Event '.$index,
            'status' => 'approved',
            'visibility' => 'public',
            'starts_at' => now()->subDays($index),
        ]);

        EventCheckin::factory()->for($event)->for($user)->create([
            'checked_in_at' => now()->subDays($index),
        ]);
    }

    $response = $this->withSession(['locale' => 'en'])
        ->actingAs($user)
        ->get('/dashboard?agenda_page=2&going_page=2&submitted_page=2&checkins_page=2');

    $response->assertOk();

    $html = (string) $response->getContent();

    $agendaSectionText = str($html)
        ->after('id="planner-agenda"')
        ->before('id="planner-going"')
        ->toString();
    $goingSectionText = str($html)
        ->after('id="planner-going"')
        ->before('id="planner-registered"')
        ->toString();
    $submittedSectionText = str($html)
        ->after('id="planner-submitted"')
        ->before('id="planner-checkins"')
        ->toString();
    $checkinsSectionText = str($html)
        ->after('id="planner-checkins"')
        ->toString();

    expect($agendaSectionText)->toContain('Going Page Event 8')
        ->not->toContain('Going Page Event 2');
    expect($goingSectionText)->toContain('Going Page Event 4')
        ->toContain('Going Page Event 6')
        ->not->toContain('Going Page Event 1')
        ->not->toContain('Going Page Event 7');
    expect($submittedSectionText)->toContain('Submitted Page Event 5')
        ->not->toContain('Submitted Page Event 1');
    expect($checkinsSectionText)->toContain('Check-in History Event 7')
        ->not->toContain('Check-in History Event 1');
    expect($html)->toContain('window.scrollTo({ top: targetTop, left: 0, behavior: &#039;auto&#039; })')
        ->not->toContain('scrollIntoView()');
});

it('does not expose the removed legacy account settings urls', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/papan-pemuka/tetapan-akaun')
        ->assertNotFound();

    $this->actingAs($user)
        ->get('/dashboard/account-settings')
        ->assertNotFound();

    $this->actingAs($user)
        ->get('/papan-pemuka/pilihan-digest')
        ->assertNotFound();

    $this->actingAs($user)
        ->get('/dashboard/digest-preferences')
        ->assertNotFound();

    $followedResponse = $this->withSession(['locale' => 'en'])
        ->actingAs($user)
        ->get(route('dashboard.account-settings', ['tab' => 'notifications']));

    $followedResponse->assertOk()
        ->assertSee('Account Settings')
        ->assertSee('Manage your account and notifications from one place.')
        ->assertSee('Open inbox')
        ->assertSee('Save Notification Settings');
});

it('hides institution dashboard access for users without institution membership', function () {
    $user = User::factory()->create();

    $dashboardResponse = $this->withSession(['locale' => 'en'])
        ->actingAs($user)
        ->get('/dashboard');

    $dashboardResponse->assertOk()
        ->assertDontSee('Institution Dashboard');

    $this->actingAs($user)
        ->get(route('dashboard.institutions'))
        ->assertForbidden();
});

it('merges overlapping planner relationships into one calendar entry', function () {
    $user = User::factory()->create();

    $event = Event::factory()->create([
        'title' => 'Merged Planner Event',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(2),
    ]);

    $user->savedEvents()->attach($event->id);
    $user->goingEvents()->attach($event->id);

    Registration::factory()->for($event)->for($user)->create([
        'status' => 'registered',
    ]);

    $component = Livewire::actingAs($user)->test(UserDashboard::class);

    /** @var UserDashboard $instance */
    $instance = $component->instance();

    expect($instance->calendarEntries)->toHaveCount(1)
        ->and($instance->calendarEntries[0]['roles'])->toBe([
            'going',
            'registered',
            'saved',
        ])
        ->and($instance->upcomingAgenda)->toHaveCount(1);
});

it('shows institution profile and events for members without a separate registrations section', function () {
    $user = User::factory()->create();
    $attendee = User::factory()->create();

    $institution = Institution::factory()->create(['name' => 'Masjid Al-Ikhlas']);
    $otherInstitution = Institution::factory()->create(['name' => 'Masjid Al-Istiqamah']);

    $user->institutions()->attach($institution->id);

    $eventInInstitution = Event::factory()->for($institution)->create([
        'title' => 'Institution Dashboard Event',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(5),
    ]);

    $parentProgram = Event::factory()->for($institution)->create([
        'title' => 'Institution Parent Program',
        'event_structure' => EventStructure::ParentProgram->value,
        'status' => 'draft',
        'visibility' => 'public',
        'starts_at' => now()->addDays(7),
    ]);

    $eventOutsideInstitution = Event::factory()->for($otherInstitution)->create([
        'title' => 'Outside Institution Event',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(6),
    ]);

    Registration::factory()->for($eventInInstitution)->for($attendee)->create([
        'name' => 'Ahmad Registrant',
        'email' => 'ahmad@example.com',
        'status' => 'registered',
    ]);

    Registration::factory()->for($eventOutsideInstitution)->create([
        'name' => 'External Registrant',
        'status' => 'registered',
    ]);

    $response = $this->withSession(['locale' => 'en'])
        ->actingAs($user)
        ->get(route('dashboard.institutions', ['institution' => $institution->id]));

    $response->assertOk()
        ->assertSee('Masjid Al-Ikhlas')
        ->assertSee('Event List')
        ->assertSee('Submit Event')
        ->assertSee('Advanced parent-program builder is temporarily unavailable on this dashboard.')
        ->assertDontSee('Create Advanced Program')
        ->assertSee('Search by event title or venue')
        ->assertSee('Members & Roles')
        ->assertSee('Institution Dashboard Event')
        ->assertSee('Institution Parent Program')
        ->assertSee('Add Child Event')
        ->assertDontSee('Event Registrations')
        ->assertDontSee('Registrations (All)')
        ->assertDontSee('Ahmad Registrant')
        ->assertDontSee('Outside Institution Event')
        ->assertDontSee('External Registrant');

    $response->assertSee(route('dashboard.institutions.submit-event', ['institution' => $institution->id]), false);
    $response->assertSee(route('submit-event.create', ['parent' => $parentProgram->id]), false);
});

it('highlights institution events that are waiting approval', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create(['name' => 'Masjid Menunggu Kelulusan']);

    $user->institutions()->attach($institution->id);

    Event::factory()->for($institution)->create([
        'title' => 'Pending Institution Dashboard Event',
        'status' => 'pending',
        'visibility' => 'public',
        'starts_at' => now()->addDays(3),
    ]);

    $response = $this->withSession(['locale' => 'en'])
        ->actingAs($user)
        ->get(route('dashboard.institutions', ['institution' => $institution->id]));

    $response->assertOk()
        ->assertSee('Pending Institution Dashboard Event')
        ->assertSee('Pending Approval')
        ->assertSee('data-event-status="pending-attention"', false);
});

it('filters and sorts institution events on the dashboard', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create(['name' => 'Masjid Tapis Majlis']);
    $otherInstitution = Institution::factory()->create();

    $user->institutions()->attach($institution->id);

    Event::factory()->for($institution)->create([
        'title' => 'Zulu Public Event',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(3),
    ]);

    Event::factory()->for($institution)->create([
        'title' => 'Alpha Hidden Pending Event',
        'status' => 'pending',
        'visibility' => 'private',
        'starts_at' => now()->addDays(1),
    ]);

    Event::factory()->for($institution)->create([
        'title' => 'Beta Unlisted Event',
        'status' => 'approved',
        'visibility' => 'unlisted',
        'starts_at' => now()->addDays(2),
    ]);

    Event::factory()->for($otherInstitution)->create([
        'title' => 'Outside Event',
        'status' => 'pending',
        'visibility' => 'private',
        'starts_at' => now()->addDays(4),
    ]);

    $filteredResponse = $this->withSession(['locale' => 'en'])
        ->actingAs($user)
        ->get(route('dashboard.institutions', [
            'institution' => $institution->id,
            'event_search' => 'alpha',
            'event_status' => 'pending',
            'event_visibility' => 'private',
            'event_sort' => 'title_asc',
        ]));

    $filteredResponse->assertOk()
        ->assertSee('Alpha Hidden Pending Event')
        ->assertDontSee('Zulu Public Event')
        ->assertDontSee('Beta Unlisted Event')
        ->assertDontSee('Outside Event');

    $sortedResponse = $this->withSession(['locale' => 'en'])
        ->actingAs($user)
        ->get(route('dashboard.institutions', [
            'institution' => $institution->id,
            'event_sort' => 'title_asc',
        ]));

    $sortedResponse->assertOk()
        ->assertSeeInOrder([
            'Alpha Hidden Pending Event',
            'Beta Unlisted Event',
            'Zulu Public Event',
        ]);
});

it('paginates institution events on the dashboard', function () {
    $user = User::factory()->create();
    $institution = Institution::factory()->create(['name' => 'Masjid Paginate']);

    $user->institutions()->attach($institution->id);

    foreach (range(1, 9) as $index) {
        Event::factory()->for($institution)->create([
            'title' => sprintf('Paged Institution Event %02d', $index),
            'status' => 'approved',
            'visibility' => 'public',
            'starts_at' => now()->addDays($index),
        ]);
    }

    $pageOne = $this->actingAs($user)
        ->get(route('dashboard.institutions', [
            'institution' => $institution->id,
            'event_sort' => 'title_asc',
            'event_per_page' => 8,
        ]));

    $pageOne->assertOk()
        ->assertSee('Paged Institution Event 01')
        ->assertSee('Paged Institution Event 08')
        ->assertDontSee('Paged Institution Event 09');

    $pageTwo = $this->actingAs($user)
        ->get(route('dashboard.institutions', [
            'institution' => $institution->id,
            'event_sort' => 'title_asc',
            'event_per_page' => 8,
            'institution_events_page' => 2,
        ]));

    $pageTwo->assertOk()
        ->assertSee('Paged Institution Event 09')
        ->assertDontSee('Paged Institution Event 01');
});

it('clearly distinguishes public and internal institution data for members', function () {
    $user = User::factory()->create();
    $attendee = User::factory()->create();

    $institution = Institution::factory()->create(['name' => 'Masjid Pemisahan Scope']);
    $user->institutions()->attach($institution->id);

    $publicEvent = Event::factory()->for($institution)->create([
        'title' => 'Public Institution Event',
        'status' => 'approved',
        'visibility' => 'public',
        'is_active' => true,
        'starts_at' => now()->addDays(2),
    ]);

    $internalEvent = Event::factory()->for($institution)->create([
        'title' => 'Internal Institution Event',
        'status' => 'draft',
        'visibility' => 'private',
        'is_active' => true,
        'starts_at' => now()->addDays(4),
    ]);

    Registration::factory()->for($publicEvent)->for($attendee)->create([
        'name' => 'Public Event Registrant',
        'status' => 'registered',
    ]);

    Registration::factory()->for($internalEvent)->for($attendee)->create([
        'name' => 'Internal Event Registrant',
        'status' => 'registered',
    ]);

    $response = $this->withSession(['locale' => 'en'])
        ->actingAs($user)
        ->get(route('dashboard.institutions', ['institution' => $institution->id]));

    $response->assertOk()
        ->assertSee('Masjid Pemisahan Scope')
        ->assertSee('Public Institution Event')
        ->assertSee('Internal Institution Event')
        ->assertDontSee('Public Event Registrant')
        ->assertDontSee('Internal Event Registrant')
        ->assertSee('Public active: 1')
        ->assertSee('Internal / hidden: 1')
        ->assertSee('Hidden')
        ->assertDontSee('Private')
        ->assertSee('Visible on public page')
        ->assertSee('Internal only');
});

it('lets institution owners and admins add members and manage scoped roles from the dashboard', function () {
    $adminUser = User::factory()->create();
    $memberUser = User::factory()->create([
        'email' => 'new-member@example.test',
    ]);
    $institution = Institution::factory()->create(['name' => 'Masjid Ahli Dashboard']);

    $institution->members()->syncWithoutDetaching([$adminUser->id]);

    app(ScopedMemberRoleSeeder::class)->ensureForInstitution();

    $institutionScope = app(MemberRoleScopes::class)->institution();
    $teamsKey = app(PermissionRegistrar::class)->teamsKey;

    Authz::withScope($institutionScope, function () use ($adminUser): void {
        $adminUser->syncRoles(['admin']);
    }, $adminUser);

    $roleIds = Authz::withScope($institutionScope, fn (): array => Role::query()
        ->where($teamsKey, getPermissionsTeamId())
        ->pluck('id', 'name')
        ->all());

    Livewire::withQueryParams(['institution' => $institution->id])
        ->actingAs($adminUser)
        ->test(InstitutionDashboard::class)
        ->set('newMemberEmail', $memberUser->email)
        ->set('newMemberRoleId', $roleIds['viewer'])
        ->call('addMember')
        ->assertHasNoErrors()
        ->assertSet('newMemberEmail', '')
        ->assertSet('newMemberRoleId', '');

    expect($institution->fresh()->members()->whereKey($memberUser->id)->exists())->toBeTrue();

    $memberRoleNames = Authz::withScope($institutionScope, fn (): array => $memberUser->fresh()->getRoleNames()->values()->all(), $memberUser);

    expect($memberRoleNames)->toBe(['viewer']);

    Livewire::withQueryParams(['institution' => $institution->id])
        ->actingAs($adminUser)
        ->test(InstitutionDashboard::class)
        ->call('startEditingMemberRoles', $memberUser->id)
        ->set('editingMemberRoleId', $roleIds['editor'])
        ->call('saveMemberRoles')
        ->assertHasNoErrors()
        ->assertSet('editingMemberId', null)
        ->assertSet('editingMemberRoleId', '');

    $updatedRoleNames = Authz::withScope($institutionScope, fn (): array => $memberUser->fresh()->getRoleNames()->values()->all(), $memberUser);

    expect($updatedRoleNames)->toBe(['editor']);

    Livewire::withQueryParams(['institution' => $institution->id])
        ->actingAs($adminUser)
        ->test(InstitutionDashboard::class)
        ->call('removeMember', $memberUser->id)
        ->assertHasNoErrors();

    expect($institution->fresh()->members()->whereKey($memberUser->id)->exists())->toBeFalse()
        ->and(Authz::withScope($institutionScope, fn (): array => $memberUser->fresh()->getRoleNames()->values()->all(), $memberUser))->toBe([]);
});

it('only lets institution owners and admins manage members and never removes owners', function () {
    $ownerUser = User::factory()->create();
    $adminUser = User::factory()->create();
    $viewerUser = User::factory()->create();
    $newMember = User::factory()->create([
        'email' => 'candidate-member@example.test',
    ]);
    $institution = Institution::factory()->create(['name' => 'Masjid Role Rules']);

    $institution->members()->syncWithoutDetaching([
        $ownerUser->id,
        $adminUser->id,
        $viewerUser->id,
    ]);

    app(ScopedMemberRoleSeeder::class)->ensureForInstitution();

    $institutionScope = app(MemberRoleScopes::class)->institution();
    $teamsKey = app(PermissionRegistrar::class)->teamsKey;

    Authz::withScope($institutionScope, function () use ($ownerUser, $adminUser, $viewerUser): void {
        $ownerUser->syncRoles(['owner']);
        $adminUser->syncRoles(['admin']);
        $viewerUser->syncRoles(['viewer']);
    }, $ownerUser);

    $roleIds = Authz::withScope($institutionScope, fn (): array => Role::query()
        ->where($teamsKey, getPermissionsTeamId())
        ->pluck('id', 'name')
        ->all());

    $response = $this->withSession(['locale' => 'en'])
        ->actingAs($viewerUser)
        ->get(route('dashboard.institutions', ['institution' => $institution->id]));

    $response->assertOk()
        ->assertSee('Only institution owners and admins can add, remove, or update member roles from this dashboard.')
        ->assertDontSee('Add Member')
        ->assertDontSee('Remove')
        ->assertDontSee('Owner cannot be removed');

    Livewire::withQueryParams(['institution' => $institution->id])
        ->actingAs($viewerUser)
        ->test(InstitutionDashboard::class)
        ->set('newMemberEmail', $newMember->email)
        ->set('newMemberRoleId', $roleIds['viewer'])
        ->call('addMember');

    expect($institution->fresh()->members()->whereKey($newMember->id)->exists())->toBeFalse();

    Livewire::withQueryParams(['institution' => $institution->id])
        ->actingAs($adminUser)
        ->test(InstitutionDashboard::class)
        ->call('startEditingMemberRoles', $ownerUser->id)
        ->assertDispatched('app-toast')
        ->assertSet('editingMemberId', null);

    Livewire::withQueryParams(['institution' => $institution->id])
        ->actingAs($adminUser)
        ->test(InstitutionDashboard::class)
        ->call('removeMember', $ownerUser->id)
        ->assertDispatched('app-toast');

    expect($institution->fresh()->members()->whereKey($ownerUser->id)->exists())->toBeTrue()
        ->and(Authz::withScope($institutionScope, fn (): array => $ownerUser->fresh()->getRoleNames()->values()->all(), $ownerUser))->toBe(['owner']);
});

it('forbids selecting institutions the user does not belong to', function () {
    $user = User::factory()->create();

    $memberInstitution = Institution::factory()->create();
    $nonMemberInstitution = Institution::factory()->create();

    $user->institutions()->attach($memberInstitution->id);

    $this->actingAs($user)
        ->get(route('dashboard.institutions', ['institution' => $nonMemberInstitution->id]))
        ->assertForbidden();
});

it('does not expose removed institution dashboard legacy urls', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/papan-pemuka/institusi')
        ->assertNotFound();

    $this->actingAs($user)
        ->get('/dashboard/institutions')
        ->assertNotFound();
});

it('does not expose removed advanced schedule urls', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $event = Event::factory()->create([
        'user_id' => $owner->id,
        'submitter_id' => $owner->id,
        'status' => 'draft',
    ]);

    $this->actingAs($owner)
        ->get("/dashboard/events/{$event->id}/schedule")
        ->assertNotFound();

    $this->actingAs($otherUser)
        ->get("/dashboard/events/{$event->id}/schedule")
        ->assertNotFound();
});
