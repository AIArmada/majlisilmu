<?php

use App\Enums\EventAgeGroup;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\Speaker;
use App\Models\Tag;
use App\Models\Venue;
use Livewire\Livewire;

it('assigns the speaker as event speaker when speaker is the organizer', function () {
    $speaker = Speaker::factory()->create(['status' => 'verified']);
    $domainTag = Tag::factory()->domain()->create();
    $disciplineTag = Tag::factory()->discipline()->create();
    $venue = Venue::factory()->create(['status' => 'verified']);

    Livewire::test('pages.submit-event.create')
        ->set('data.organizer_type', 'speaker')
        ->set('data.organizer_speaker_id', $speaker->id)
        ->set('data.speakers', [$speaker->id]) // In real UI, this is auto-set by JS; we simulate it here
        ->set('data.title', 'Auto Select Speaker Event')
        ->set('data.event_date', now()->addDay()->toDateString())
        ->set('data.prayer_time', EventPrayerTime::SelepasMaghrib->value)
        ->set('data.event_type', [\App\Enums\EventType::KuliahCeramah->value])
        ->set('data.gender', EventGenderRestriction::All->value)
        ->set('data.age_group', [EventAgeGroup::AllAges->value])
        ->set('data.languages', [101])
        ->set('data.description', 'Test description')
        ->set('data.domain_tags', [$domainTag->id])
        ->set('data.discipline_tags', [$disciplineTag->id])
        ->set('data.submitter_name', 'Test User')
        ->set('data.submitter_email', 'test@example.com')
        ->set('data.location_type', 'venue')
        ->set('data.location_venue_id', $venue->id)
        ->set('data.visibility', EventVisibility::Public->value)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('submit-event.success'));

    $event = Event::where('title', 'Auto Select Speaker Event')->firstOrFail();
    expect($event->speakers)->toHaveCount(1);
    expect($event->speakers->first()->id)->toBe($speaker->id);
    expect($event->organizer_type)->toBe(Speaker::class);
    expect($event->organizer_id)->toBe($speaker->id);
});
