<?php

use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\TagType;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\Tag;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    Http::fake();
    $this->user = User::factory()->create();

    $this->domainTag = Tag::factory()->create(['type' => TagType::Domain->value]);
    $this->disciplineTag = Tag::factory()->create(['type' => TagType::Discipline->value]);
});

it('can submit an event as a speaker with an institution location', function () {
    $speaker = Speaker::factory()->create(['status' => 'verified']);
    $institution = Institution::factory()->create(['status' => 'verified']);

    Livewire::actingAs($this->user)
        ->test('pages.submit-event.create')
        ->set('data.title', 'Speaker at Institution')
        ->set('data.description', 'Test description.')
        ->set('data.event_date', now()->addDays(7)->format('Y-m-d'))
        ->set('data.prayer_time', 'selepas_maghrib')
        ->set('data.event_type', [\App\Enums\EventType::KuliahCeramah->value])
        ->set('data.organizer_type', 'speaker')
        ->set('data.organizer_speaker_id', $speaker->id)
        ->set('data.speakers', [$speaker->id])
        ->set('data.location_type', 'institution')
        ->set('data.location_institution_id', $institution->id)
        ->set('data.event_format', EventFormat::Physical->value)
        ->set('data.gender', EventGenderRestriction::All->value)
        ->set('data.age_group', [EventAgeGroup::AllAges->value])
        ->set('data.domain_tags', [$this->domainTag->id])
        ->set('data.discipline_tags', [$this->disciplineTag->id])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas(Event::class, [
        'title' => 'Speaker at Institution',
        'institution_id' => $institution->id,
        'venue_id' => null,
    ]);
});

it('can submit an event as a speaker with a venue location', function () {
    $speaker = Speaker::factory()->create(['status' => 'verified']);
    $venue = Venue::factory()->create(['status' => 'verified']);

    Livewire::actingAs($this->user)
        ->test('pages.submit-event.create')
        ->set('data.title', 'Speaker at Venue')
        ->set('data.description', 'Test description.')
        ->set('data.event_date', now()->addDays(7)->format('Y-m-d'))
        ->set('data.prayer_time', 'selepas_maghrib')
        ->set('data.event_type', [\App\Enums\EventType::KuliahCeramah->value])
        ->set('data.organizer_type', 'speaker')
        ->set('data.organizer_speaker_id', $speaker->id)
        ->set('data.speakers', [$speaker->id])
        ->set('data.location_type', 'venue')
        ->set('data.location_venue_id', $venue->id)
        ->set('data.event_format', EventFormat::Physical->value)
        ->set('data.gender', EventGenderRestriction::All->value)
        ->set('data.age_group', [EventAgeGroup::AllAges->value])
        ->set('data.domain_tags', [$this->domainTag->id])
        ->set('data.discipline_tags', [$this->disciplineTag->id])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas(Event::class, [
        'title' => 'Speaker at Venue',
        'institution_id' => null,
        'venue_id' => $venue->id,
    ]);
});

it('automatically sets location to institution when organizer is an institution', function () {
    $institution = Institution::factory()->create(['status' => 'verified']);

    $domainTag = Tag::factory()->create(['type' => TagType::Domain->value]);
    $disciplineTag = Tag::factory()->create(['type' => TagType::Discipline->value]);

    $speaker = Speaker::factory()->create(['status' => 'verified']);

    Livewire::actingAs($this->user)
        ->test('pages.submit-event.create')
        ->set('data.title', 'Institution Event')
        ->set('data.description', 'Test description.')
        ->set('data.event_date', now()->addDays(7)->format('Y-m-d'))
        ->set('data.prayer_time', 'selepas_maghrib')
        ->set('data.event_type', [\App\Enums\EventType::KuliahCeramah->value])
        ->set('data.organizer_type', 'institution')
        ->set('data.organizer_institution_id', $institution->id)
        ->set('data.speakers', [$speaker->id])
        ->set('data.event_format', EventFormat::Physical->value)

        ->set('data.gender', EventGenderRestriction::All->value)
        ->set('data.age_group', [EventAgeGroup::AllAges->value])
        ->set('data.domain_tags', [$this->domainTag->id])
        ->set('data.discipline_tags', [$this->disciplineTag->id])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas(Event::class, [
        'title' => 'Institution Event',
        'institution_id' => $institution->id,
        'venue_id' => null,
    ]);
});

it('requires location type when organizer is speaker', function () {
    $speaker = Speaker::factory()->create(['status' => 'verified']);

    Livewire::actingAs($this->user)
        ->test('pages.submit-event.create')
        ->set('data.organizer_type', 'speaker')
        ->set('data.location_type', null)
        ->call('submit')
        ->assertHasErrors(['data.location_type' => 'required']);
});

it('allows institution organizer to choose a different location', function () {
    $organizerInstitution = Institution::factory()->create(['status' => 'verified']);
    $otherVenue = Venue::factory()->create([
        'status' => 'verified',
        'institution_id' => null,
    ]);
    $speaker = Speaker::factory()->create(['status' => 'verified']);

    Livewire::actingAs($this->user)
        ->test('pages.submit-event.create')
        ->set('data.title', 'Institution at Other Venue')
        ->set('data.description', 'Test description.')
        ->set('data.event_date', now()->addDays(7)->format('Y-m-d'))
        ->set('data.prayer_time', 'selepas_maghrib')
        ->set('data.event_type', [\App\Enums\EventType::KuliahCeramah->value])
        ->set('data.organizer_type', 'institution')
        ->set('data.organizer_institution_id', $organizerInstitution->id)
        ->set('data.location_same_as_institution', false)
        ->set('data.location_type', 'venue')
        ->set('data.location_venue_id', $otherVenue->id)
        ->set('data.speakers', [$speaker->id])
        ->set('data.event_format', EventFormat::Physical->value)
        ->set('data.gender', EventGenderRestriction::All->value)
        ->set('data.age_group', [EventAgeGroup::AllAges->value])
        ->set('data.domain_tags', [$this->domainTag->id])
        ->set('data.discipline_tags', [$this->disciplineTag->id])
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertDatabaseHas(Event::class, [
        'title' => 'Institution at Other Venue',
        'institution_id' => null, // Since it's a venue
        'venue_id' => $otherVenue->id,
    ]);
});
