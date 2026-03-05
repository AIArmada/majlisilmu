<?php

use App\Models\Event;
use App\Models\Institution;
use App\Models\Registration;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires authentication for user and institution dashboards', function () {
    $this->get('/dashboard')->assertRedirect(route('login'));
    $this->get('/dashboard/institutions')->assertRedirect(route('login'));
    $this->get('/dashboard/events/create-advanced')->assertRedirect(route('login'));
});

it('shows profile, events, registrations, saved events, and saved searches on the user dashboard', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $institution = Institution::factory()->create(['name' => 'Masjid Al-Hidayah']);
    $otherInstitution = Institution::factory()->create(['name' => 'Masjid Al-Furqan']);

    $user->institutions()->attach($institution->id);

    $ownedEvent = Event::factory()->for($user)->for($institution)->create([
        'title' => 'Owned Dashboard Event',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(2),
    ]);

    Event::factory()->for($otherUser)->for($institution)->create([
        'title' => 'Institution Managed Event',
        'status' => 'pending',
        'visibility' => 'public',
        'starts_at' => now()->addDays(3),
    ]);

    Event::factory()->for($otherUser)->for($otherInstitution)->create([
        'title' => 'External Event',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(4),
    ]);

    $savedEvent = Event::factory()->for($otherUser)->for($institution)->create([
        'title' => 'Saved Dashboard Event',
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(7),
    ]);

    Registration::factory()->for($ownedEvent)->for($user)->create([
        'status' => 'registered',
    ]);

    $user->savedEvents()->attach($savedEvent->id);

    SavedSearch::factory()->for($user)->create([
        'name' => 'Kuliah KL Daily',
    ]);

    SavedSearch::factory()->for($otherUser)->create([
        'name' => 'Other User Search',
    ]);

    $response = $this->actingAs($user)->get('/dashboard');

    $response->assertOk()
        ->assertSee($user->email)
        ->assertSee('Owned Dashboard Event')
        ->assertSee('Institution Managed Event')
        ->assertSee('Registered')
        ->assertSee('My Saved Events')
        ->assertSee('Saved Dashboard Event')
        ->assertSee('My Saved Searches')
        ->assertSee('Kuliah KL Daily')
        ->assertDontSee('External Event');

    $response->assertDontSee('Other User Search');
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

    $response = $this->actingAs($user)->get('/dashboard/institutions?institution='.$institution->id);

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

    $response = $this->actingAs($user)->get('/dashboard/institutions?institution='.$institution->id);

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
