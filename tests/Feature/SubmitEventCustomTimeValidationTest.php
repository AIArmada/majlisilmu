<?php

use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\EventVisibility;
use App\Enums\PrayerReference;
use App\Enums\TimingMode;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\Tag;
use Livewire\Livewire;

it('can submit event with custom prayer time (lain_waktu)', function () {
    // Create necessary models
    $domainTag = Tag::factory()->domain()->create();
    $disciplineTag = Tag::factory()->discipline()->create();
    $institution = Institution::factory()->create(['status' => 'verified']);
    $speaker = Speaker::factory()->create(['status' => 'verified']);

    // Submit with LainWaktu which represents custom time mode
    Livewire::test('pages.submit-event.create')
        ->set('data.title', 'Custom Time Event')
        ->set('data.domain_tags', [$domainTag->id])
        ->set('data.discipline_tags', [$disciplineTag->id])
        ->set('data.event_type', [\App\Enums\EventType::KuliahCeramah->value])
        ->set('data.event_date', now()->addDays(5)->toDateString())
        ->set('data.prayer_time', EventPrayerTime::LainWaktu->value)
        ->set('data.custom_time', '10:00') // Required for LainWaktu
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

    $event = Event::where('title', 'Custom Time Event')->firstOrFail();
    expect($event->timing_mode)->toBe(TimingMode::Absolute);
});

it('saves timing mode as prayer_relative when using prayer time', function () {
    // Create necessary models
    $domainTag = Tag::factory()->domain()->create();
    $disciplineTag = Tag::factory()->discipline()->create();
    $institution = Institution::factory()->create(['status' => 'verified']);
    $speaker = Speaker::factory()->create(['status' => 'verified']);

    // Submit with a standard prayer time
    Livewire::test('pages.submit-event.create')
        ->set('data.title', 'Prayer Time Event')
        ->set('data.domain_tags', [$domainTag->id])
        ->set('data.discipline_tags', [$disciplineTag->id])
        ->set('data.event_type', [\App\Enums\EventType::KuliahCeramah->value])
        ->set('data.event_date', now()->addDays(5)->toDateString())
        ->set('data.prayer_time', EventPrayerTime::SelepasMaghrib->value)
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

    $event = Event::where('title', 'Prayer Time Event')->firstOrFail();
    expect($event->timing_mode)->toBe(TimingMode::PrayerRelative);
    expect($event->prayer_reference)->toBe(PrayerReference::Maghrib);
});

it('can submit event for future dates', function () {
    // Create necessary models
    $domainTag = Tag::factory()->domain()->create();
    $disciplineTag = Tag::factory()->discipline()->create();
    $institution = Institution::factory()->create(['status' => 'verified']);
    $speaker = Speaker::factory()->create(['status' => 'verified']);

    // Submit event for next week
    $futureDate = now()->addWeek()->toDateString();

    Livewire::test('pages.submit-event.create')
        ->set('data.title', 'Future Event')
        ->set('data.domain_tags', [$domainTag->id])
        ->set('data.discipline_tags', [$disciplineTag->id])
        ->set('data.event_type', [\App\Enums\EventType::KuliahCeramah->value])
        ->set('data.event_date', $futureDate)
        ->set('data.prayer_time', EventPrayerTime::SelepasMaghrib->value)
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

    expect(Event::where('title', 'Future Event')->exists())->toBeTrue();
});
