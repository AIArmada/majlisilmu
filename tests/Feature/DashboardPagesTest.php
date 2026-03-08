<?php

use App\Livewire\Pages\Dashboard\UserDashboard;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventSubmission;
use App\Models\Institution;
use App\Models\Registration;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('requires authentication for user and institution dashboards', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->get('/dashboard/account-settings')->assertRedirect(route('login'));
    $this->get('/dashboard/digest-preferences')->assertRedirect(route('login'));
    $this->get('/dashboard/institutions')->assertRedirect(route('login'));
    $this->get('/dashboard/events/create-advanced')->assertRedirect(route('login'));
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

    $interestedEvent = Event::factory()->for($otherUser)->for($otherInstitution)->create([
        'title' => 'Interested Dashboard Event',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(3),
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
    $user->interestedEvents()->attach($interestedEvent->id);
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
        ->assertSee('Attendee Planner')
        ->assertSee('Jump to section')
        ->assertSee('Overview Calendar')
        ->assertSee('Upcoming Agenda')
        ->assertSee('Account Settings')
        ->assertSee('Submitted Events')
        ->assertSee('Recent Check-ins')
        ->assertDontSee('Submitted + Check-ins')
        ->assertDontSee('Going + Registered')
        ->assertSee('Digest Preferences')
        ->assertSee('Saved Searches')
        ->assertSee('Saved Dashboard Event')
        ->assertSee('Interested Dashboard Event')
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
        ->assertSee('Perancang Kehadiran')
        ->assertSee('Pergi ke bahagian')
        ->assertSee('Majlis Dihantar')
        ->assertSee('Dashboard')
        ->assertSee('Dashboard Institusi')
        ->assertSee('Diluluskan')
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

it('shows the dedicated digest preferences page from the authenticated navigation cluster', function () {
    $user = User::factory()->create();

    $response = $this->withSession(['locale' => 'en'])
        ->actingAs($user)
        ->get(route('dashboard.digest-preferences'));

    $response->assertOk()
        ->assertSee('Digest Preferences')
        ->assertSee('Saved search delivery settings')
        ->assertSee('Saved Searches')
        ->assertSee('Back to Dashboard')
        ->assertSee('Save Preferences');
});

it('hides institution dashboard access for users without institution membership', function () {
    $user = User::factory()->create();

    $dashboardResponse = $this->withSession(['locale' => 'en'])
        ->actingAs($user)
        ->get('/dashboard');

    $dashboardResponse->assertOk()
        ->assertDontSee('Institution Dashboard');

    $this->actingAs($user)
        ->get('/dashboard/institutions')
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
    $user->interestedEvents()->attach($event->id);
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
            'interested',
            'saved',
        ])
        ->and($instance->upcomingAgenda)->toHaveCount(1);
});

it('shows institution profile, events, and registrations for members', function () {
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
        ->get('/dashboard/institutions?institution='.$institution->id);

    $response->assertOk()
        ->assertSee('Masjid Al-Ikhlas')
        ->assertSee('Institution Dashboard Event')
        ->assertSee('Ahmad Registrant')
        ->assertDontSee('Outside Institution Event')
        ->assertDontSee('External Registrant');
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
        ->get('/dashboard/institutions?institution='.$institution->id);

    $response->assertOk()
        ->assertSee('Masjid Pemisahan Scope')
        ->assertSee('Public Institution Event')
        ->assertSee('Internal Institution Event')
        ->assertSee('Public Event Registrant')
        ->assertSee('Internal Event Registrant')
        ->assertSee('Public active: 1')
        ->assertSee('Internal / hidden: 1')
        ->assertSee('Visible on public page')
        ->assertSee('Internal only');
});

it('forbids selecting institutions the user does not belong to', function () {
    $user = User::factory()->create();

    $memberInstitution = Institution::factory()->create();
    $nonMemberInstitution = Institution::factory()->create();

    $user->institutions()->attach($memberInstitution->id);

    $this->actingAs($user)
        ->get('/dashboard/institutions?institution='.$nonMemberInstitution->id)
        ->assertForbidden();
});

it('allows owner to access advanced schedule page and blocks others', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $event = Event::factory()->create([
        'user_id' => $owner->id,
        'submitter_id' => $owner->id,
        'status' => 'draft',
    ]);

    $this->actingAs($owner)
        ->get("/dashboard/events/{$event->id}/schedule")
        ->assertOk();

    $this->actingAs($otherUser)
        ->get("/dashboard/events/{$event->id}/schedule")
        ->assertForbidden();
});
