<?php

use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\Tag;
use App\Models\User;
use App\Notifications\EventSubmittedNotification;
use App\States\EventStatus\Pending;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Notification::fake();
});

it('notifies moderators when a guest submits an event', function () {
    $moderator = User::factory()->create();
    $moderator->assignRole('moderator');

    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');

    // Regular user should NOT receive the notification
    $regularUser = User::factory()->create();

    $domainTag = Tag::factory()->domain()->create();
    $disciplineTag = Tag::factory()->discipline()->create();
    $institution = Institution::factory()->create(['status' => 'verified']);
    $speaker = Speaker::factory()->create(['status' => 'verified']);

    Livewire::test('pages.submit-event.create')
        ->set('data.title', 'Notification Test Event')
        ->set('data.domain_tags', [$domainTag->id])
        ->set('data.discipline_tags', [$disciplineTag->id])
        ->set('data.event_type', [\App\Enums\EventType::KuliahCeramah->value])
        ->set('data.event_date', now()->addDays(5)->toDateString())
        ->set('data.prayer_time', EventPrayerTime::SelepasMaghrib->value)
        ->set('data.description', 'Test description for notification')
        ->set('data.event_format', EventFormat::Physical->value)
        ->set('data.visibility', EventVisibility::Public->value)
        ->set('data.gender', EventGenderRestriction::All->value)
        ->set('data.age_group', [EventAgeGroup::AllAges->value])
        ->set('data.languages', [101])
        ->set('data.organizer_type', 'institution')
        ->set('data.organizer_institution_id', $institution->id)
        ->set('data.speakers', [$speaker->id])
        ->set('data.submitter_name', 'Ahmad bin Abdullah')
        ->set('data.submitter_email', 'ahmad@example.com')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('submit-event.success'));

    // Verify the event was created with pending status via the transition
    $event = Event::where('title', 'Notification Test Event')->firstOrFail();
    expect($event->status)->toBeInstanceOf(Pending::class);

    // Verify moderator and super_admin received the notification
    Notification::assertSentTo($moderator, EventSubmittedNotification::class);
    Notification::assertSentTo($superAdmin, EventSubmittedNotification::class);

    // Verify regular user did NOT receive it
    Notification::assertNotSentTo($regularUser, EventSubmittedNotification::class);
});

it('transitions event from draft to pending on submission', function () {
    $domainTag = Tag::factory()->domain()->create();
    $disciplineTag = Tag::factory()->discipline()->create();
    $institution = Institution::factory()->create(['status' => 'verified']);
    $speaker = Speaker::factory()->create(['status' => 'verified']);

    Livewire::test('pages.submit-event.create')
        ->set('data.title', 'Draft to Pending Event')
        ->set('data.domain_tags', [$domainTag->id])
        ->set('data.discipline_tags', [$disciplineTag->id])
        ->set('data.event_type', [\App\Enums\EventType::KuliahCeramah->value])
        ->set('data.event_date', now()->addDays(5)->toDateString())
        ->set('data.prayer_time', EventPrayerTime::SelepasMaghrib->value)
        ->set('data.description', 'Testing state transition')
        ->set('data.event_format', EventFormat::Physical->value)
        ->set('data.visibility', EventVisibility::Public->value)
        ->set('data.gender', EventGenderRestriction::All->value)
        ->set('data.age_group', [EventAgeGroup::AllAges->value])
        ->set('data.languages', [101])
        ->set('data.organizer_type', 'institution')
        ->set('data.organizer_institution_id', $institution->id)
        ->set('data.speakers', [$speaker->id])
        ->set('data.submitter_name', 'Test User')
        ->set('data.submitter_email', 'test@example.com')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('submit-event.success'));

    $event = Event::where('title', 'Draft to Pending Event')->firstOrFail();

    // Event should be in Pending state (transitioned from Draft)
    expect($event->status)->toBeInstanceOf(Pending::class);

    // published_at should still be null (only set on approval)
    expect($event->published_at)->toBeNull();
});
