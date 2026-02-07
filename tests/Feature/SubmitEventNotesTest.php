<?php

use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Models\Event;
use App\Models\EventSubmission;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\Tag;
use Livewire\Livewire;

it('saves notes to event submission when provided', function () {
    $domainTag = Tag::factory()->domain()->create();
    $disciplineTag = Tag::factory()->discipline()->create();
    $institution = Institution::factory()->create(['status' => 'verified']);
    $speaker = Speaker::factory()->create(['status' => 'verified']);

    $notes = 'This event requires special audio equipment and accessibility ramps.';

    Livewire::test('pages.submit-event.create')
        ->set('data.title', 'Event With Notes')
        ->set('data.domain_tags', [$domainTag->id])
        ->set('data.discipline_tags', [$disciplineTag->id])
        ->set('data.event_type', [\App\Enums\EventType::KuliahCeramah->value])
        ->set('data.event_date', now()->addDays(5)->toDateString())
        ->set('data.prayer_time', EventPrayerTime::SelepasMaghrib->value)
        ->set('data.description', 'Test description')
        ->set('data.event_format', EventFormat::Physical->value)
        ->set('data.gender', EventGenderRestriction::All->value)
        ->set('data.age_group', [EventAgeGroup::AllAges->value])
        ->set('data.organizer_type', 'institution')
        ->set('data.organizer_institution_id', $institution->id)
        ->set('data.speakers', [$speaker->id])
        ->set('data.submitter_name', 'Test User')
        ->set('data.submitter_email', 'test@example.com')
        ->set('data.notes', $notes)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('submit-event.success'));

    $event = Event::where('title', 'Event With Notes')->firstOrFail();
    $submission = EventSubmission::where('event_id', $event->id)->firstOrFail();

    expect($submission->notes)->toBe($notes);
});

it('allows submitting event without notes', function () {
    $domainTag = Tag::factory()->domain()->create();
    $disciplineTag = Tag::factory()->discipline()->create();
    $institution = Institution::factory()->create(['status' => 'verified']);
    $speaker = Speaker::factory()->create(['status' => 'verified']);

    Livewire::test('pages.submit-event.create')
        ->set('data.title', 'Event Without Notes')
        ->set('data.domain_tags', [$domainTag->id])
        ->set('data.discipline_tags', [$disciplineTag->id])
        ->set('data.event_type', [\App\Enums\EventType::KuliahCeramah->value])
        ->set('data.event_date', now()->addDays(5)->toDateString())
        ->set('data.prayer_time', EventPrayerTime::SelepasMaghrib->value)
        ->set('data.description', 'Test description')
        ->set('data.event_format', EventFormat::Physical->value)
        ->set('data.gender', EventGenderRestriction::All->value)
        ->set('data.age_group', [EventAgeGroup::AllAges->value])
        ->set('data.organizer_type', 'institution')
        ->set('data.organizer_institution_id', $institution->id)
        ->set('data.speakers', [$speaker->id])
        ->set('data.submitter_name', 'Test User')
        ->set('data.submitter_email', 'test@example.com')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('submit-event.success'));

    $event = Event::where('title', 'Event Without Notes')->firstOrFail();
    $submission = EventSubmission::where('event_id', $event->id)->firstOrFail();

    expect($submission->notes)->toBeNull();
});
