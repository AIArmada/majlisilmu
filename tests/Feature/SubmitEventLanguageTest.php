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

it('can submit event with single language', function () {
    $domainTag = Tag::factory()->domain()->create();
    $disciplineTag = Tag::factory()->discipline()->create();
    $institution = Institution::factory()->create(['status' => 'verified']);
    $speaker = Speaker::factory()->create(['status' => 'verified']);

    Livewire::test('pages.submit-event.create')
        ->set('data.title', 'Single Language Event')
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
        ->set('data.languages', [101]) // Malay only
        ->set('data.organizer_type', 'institution')
        ->set('data.organizer_institution_id', $institution->id)
        ->set('data.speakers', [$speaker->id])
        ->set('data.submitter_name', 'Test User')
        ->set('data.submitter_email', 'test@example.com')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('submit-event.success'));

    $event = Event::where('title', 'Single Language Event')->firstOrFail();
    expect($event->languages)->toHaveCount(1);
    expect($event->languages->first()->code)->toBe('ms');
});

it('can submit event with multiple languages', function () {
    $domainTag = Tag::factory()->domain()->create();
    $disciplineTag = Tag::factory()->discipline()->create();
    $institution = Institution::factory()->create(['status' => 'verified']);
    $speaker = Speaker::factory()->create(['status' => 'verified']);

    Livewire::test('pages.submit-event.create')
        ->set('data.title', 'Multi Language Event')
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
        ->set('data.languages', [101, 7, 40]) // Malay, Arabic, English
        ->set('data.organizer_type', 'institution')
        ->set('data.organizer_institution_id', $institution->id)
        ->set('data.speakers', [$speaker->id])
        ->set('data.submitter_name', 'Test User')
        ->set('data.submitter_email', 'test@example.com')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('submit-event.success'));

    $event = Event::where('title', 'Multi Language Event')->firstOrFail();
    expect($event->languages)->toHaveCount(3);
    $languageCodes = $event->languages->pluck('code')->toArray();
    expect($languageCodes)->toContain('ms', 'ar', 'en');
});

it('requires at least one language', function () {
    $domainTag = Tag::factory()->domain()->create();
    $disciplineTag = Tag::factory()->discipline()->create();
    $institution = Institution::factory()->create(['status' => 'verified']);
    $speaker = Speaker::factory()->create(['status' => 'verified']);

    Livewire::test('pages.submit-event.create')
        ->set('data.title', 'No Language Event')
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
        ->set('data.languages', []) // Empty - should fail validation
        ->set('data.organizer_type', 'institution')
        ->set('data.organizer_institution_id', $institution->id)
        ->set('data.speakers', [$speaker->id])
        ->set('data.submitter_name', 'Test User')
        ->set('data.submitter_email', 'test@example.com')
        ->call('submit')
        ->assertHasErrors(['data.languages']);
});
