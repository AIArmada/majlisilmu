<?php

use App\Enums\EventAgeGroup;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\Topic;
use App\Models\Venue;
use Livewire\Livewire;

it('auto-selects the speaker as an event speaker when selected as organizer', function () {
    $speaker = Speaker::factory()->create(['status' => 'verified']);
    $eventType = EventType::factory()->create(['slug' => 'kuliah']);
    $topic = Topic::factory()->create();
    $venue = Venue::factory()->create(['status' => 'verified']);

    Livewire::test('pages.submit-event.create')
        ->set('data.organizer_type', 'speaker')
        ->set('data.organizer_speaker_id', $speaker->id)
        ->assertSet('data.speakers', [$speaker->id])
        ->set('data.title', 'Auto Select Speaker Event')
        ->set('data.event_date', now()->addDay()->toDateString())
        ->set('data.prayer_time', EventPrayerTime::SelepasMaghrib->value)
        ->set('data.event_type_id', $eventType->id)
        ->set('data.gender', EventGenderRestriction::All->value)
        ->set('data.age_group', [EventAgeGroup::AllAges->value])
        ->set('data.description', 'Test description')
        ->set('data.topics', [$topic->id])
        ->set('data.submitter_name', 'Test User')
        ->set('data.submitter_email', 'test@example.com')
        ->set('data.location_id', 'venue:'.$venue->id)
        ->call('submit')
        ->assertHasNoErrors();

    $event = Event::where('title', 'Auto Select Speaker Event')->firstOrFail();
    expect($event->speakers)->toHaveCount(1);
    expect($event->speakers->first()->id)->toBe($speaker->id);
});
