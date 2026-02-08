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
use Livewire\Livewire;

it('can submit event with optional end time', function () {
    // Create necessary models
    $domainTag = Tag::factory()->domain()->create();
    $disciplineTag = Tag::factory()->discipline()->create();
    $institution = Institution::factory()->create(['status' => 'verified']);
    $speaker = Speaker::factory()->create(['status' => 'verified']);

    Livewire::test('pages.submit-event.create')
        ->set('data.title', 'Event With End Time')
        ->set('data.domain_tags', [$domainTag->id])
        ->set('data.discipline_tags', [$disciplineTag->id])
        ->set('data.event_type', [\App\Enums\EventType::KuliahCeramah->value])
        ->set('data.event_date', now()->addDays(5)->toDateString())
        ->set('data.prayer_time', EventPrayerTime::SelepasMaghrib->value)
        ->set('data.end_time', '21:30') // Optional end time
        ->set('data.description', 'Test description')
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

    $event = Event::where('title', 'Event With End Time')->firstOrFail();
    expect($event->ends_at)->not->toBeNull();
    // Compare in the same timezone used in the form
    expect($event->ends_at->timezone('Asia/Kuala_Lumpur')->format('H:i'))->toBe('21:30');
    // Verify ends_at has same date as starts_at
    expect($event->ends_at->toDateString())->toBe($event->starts_at->toDateString());
});

it('can submit event without end time (optional)', function () {
    // Create necessary models
    $domainTag = Tag::factory()->domain()->create();
    $disciplineTag = Tag::factory()->discipline()->create();
    $institution = Institution::factory()->create(['status' => 'verified']);
    $speaker = Speaker::factory()->create(['status' => 'verified']);

    Livewire::test('pages.submit-event.create')
        ->set('data.title', 'Event Without End Time')
        ->set('data.domain_tags', [$domainTag->id])
        ->set('data.discipline_tags', [$disciplineTag->id])
        ->set('data.event_type', [\App\Enums\EventType::KuliahCeramah->value])
        ->set('data.event_date', now()->addDays(5)->toDateString())
        ->set('data.prayer_time', EventPrayerTime::SelepasMaghrib->value)
        // No end_time set - should still submit successfully
        ->set('data.description', 'Test description')
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

    $event = Event::where('title', 'Event Without End Time')->firstOrFail();
    expect($event->ends_at)->toBeNull();
});

it('can submit event with custom time and end time', function () {
    // Create necessary models
    $domainTag = Tag::factory()->domain()->create();
    $disciplineTag = Tag::factory()->discipline()->create();
    $institution = Institution::factory()->create(['status' => 'verified']);
    $speaker = Speaker::factory()->create(['status' => 'verified']);

    Livewire::test('pages.submit-event.create')
        ->set('data.title', 'Custom Time With End Time')
        ->set('data.domain_tags', [$domainTag->id])
        ->set('data.discipline_tags', [$disciplineTag->id])
        ->set('data.event_type', [\App\Enums\EventType::KuliahCeramah->value])
        ->set('data.event_date', now()->addDays(5)->toDateString())
        ->set('data.prayer_time', EventPrayerTime::LainWaktu->value)
        ->set('data.custom_time', '10:00') // Custom start time
        ->set('data.end_time', '12:00') // End time
        ->set('data.description', 'Test description')
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

    $event = Event::where('title', 'Custom Time With End Time')->firstOrFail();
    // Compare in the same timezone used in the form
    expect($event->starts_at->timezone('Asia/Kuala_Lumpur')->format('H:i'))->toBe('10:00');
    expect($event->ends_at->timezone('Asia/Kuala_Lumpur')->format('H:i'))->toBe('12:00');
    expect($event->ends_at->toDateString())->toBe($event->starts_at->toDateString());
});
