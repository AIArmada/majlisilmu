<?php

use App\Enums\EventAgeGroup;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\EventSubmission;
use App\Models\Institution;
use App\Models\Series;
use App\Models\Speaker;
use App\Models\Tag;
use Livewire\Livewire;

it('loads public index pages', function () {
    $this->get('/')->assertSuccessful()->assertSee('Majlis Ilmu');
    $this->get('/events')->assertSuccessful()->assertSee('Majlis Ilmu');
    $this->get('/institutions')->assertSuccessful()->assertSee('Majlis Ilmu');
    $this->get('/speakers')->assertSuccessful()->assertSee('Majlis Ilmu');
    $this->get('/submit-event')->assertSuccessful()->assertSee('Hantar Majlis');
    $this->get('/submit-event/success')->assertSuccessful()->assertSee(__('Event Submitted!'));
});

it('loads public detail pages', function () {
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'published_at' => now(),
        'starts_at' => now()->addDay(),
    ]);

    $institution = Institution::factory()->create(['status' => 'verified']);
    $speaker = Speaker::factory()->create(['status' => 'verified']);
    $series = Series::factory()->create([
        'visibility' => 'public',
    ]);

    $this->get(route('events.show', $event))->assertSuccessful()->assertSee($event->title);
    $this->get(route('institutions.show', $institution))->assertSuccessful()->assertSee($institution->name);
    $this->get(route('speakers.show', $speaker))->assertSuccessful()->assertSee($speaker->name);
    $this->get(route('series.show', $series))->assertSuccessful()->assertSee($series->title);
});

it('loads institution detail page with upcoming event type enum collection', function () {
    $institution = Institution::factory()->create(['status' => 'verified']);
    $eventType = \App\Enums\EventType::KuliahCeramah;
    $event = Event::factory()
        ->for($institution)
        ->create([
            'status' => 'approved',
            'visibility' => EventVisibility::Public,
            'starts_at' => now()->addDay(),
            'event_type' => [$eventType],
            'title' => 'Institution Upcoming Event',
        ]);

    $this->get(route('institutions.show', $institution))
        ->assertSuccessful()
        ->assertSee($institution->name)
        ->assertSee($event->title)
        ->assertSee($eventType->getLabel());
});

it('hides unverified speakers and institutions from public pages', function () {
    $institution = Institution::factory()->create(['status' => 'pending']);
    $speaker = Speaker::factory()->create(['status' => 'pending']);

    $this->get(route('institutions.show', $institution))->assertNotFound();
    $this->get(route('speakers.show', $speaker))->assertNotFound();
});

it('updates submit event age group without error', function () {
    Livewire::test('pages.submit-event.create')
        ->set('data.age_group', [EventAgeGroup::Children->value])
        ->assertSet('data.age_group', [EventAgeGroup::Children->value]);
});

it('records guest submissions without a submitter id', function () {
    $title = 'Guest Submission '.uniqid();
    $email = 'guest@example.com';

    $domainTag = Tag::factory()->domain()->create();
    $disciplineTag = Tag::factory()->discipline()->create();
    $speaker = Speaker::factory()->create(['status' => 'verified']);
    $institution = Institution::factory()->create(['status' => 'verified']);
    Livewire::test('pages.submit-event.create')
        ->set('data.title', $title)
        ->set('data.description', 'Test event description')
        ->set('data.event_date', now()->addDay()->toDateString())
        ->set('data.prayer_time', EventPrayerTime::SelepasMaghrib->value)
        ->set('data.event_type', [\App\Enums\EventType::KuliahCeramah->value])
        ->set('data.gender', EventGenderRestriction::All->value)
        ->set('data.age_group', [EventAgeGroup::AllAges->value])
        ->set('data.domain_tags', [$domainTag->id])
        ->set('data.discipline_tags', [$disciplineTag->id])
        ->set('data.speakers', [$speaker->id])
        ->set('data.organizer_type', 'institution')
        ->set('data.organizer_institution_id', $institution->id)
        ->set('data.submitter_name', 'Guest User')
        ->set('data.submitter_email', $email)
        ->set('data.visibility', EventVisibility::Public->value)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('submit-event.success'));

    $event = Event::query()->where('title', $title)->first();

    expect($event)->not->toBeNull();
    expect($event?->submitter_id)->toBeNull();

    $submission = EventSubmission::query()->where('event_id', $event->id)->first();

    expect($submission)->not->toBeNull();
    expect($submission->submitted_by)->toBeNull();
    expect($submission->contacts()->where('category', 'email')->where('value', $email)->exists())->toBeTrue();
});
