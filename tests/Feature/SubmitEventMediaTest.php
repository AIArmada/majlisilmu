<?php

use App\Enums\EventAgeGroup;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Models\Event;
use App\Models\EventType;
use App\Models\Institution;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('stores poster and gallery uploads when submitting an event', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $eventDate = now()->addDay()->toDateString();
    $topics = Topic::factory()->count(2)->create();
    $speakers = \App\Models\Speaker::factory()->count(2)->create();
    $eventType = EventType::factory()->create();
    $institution = Institution::factory()->create(['status' => 'verified']);

    Livewire::test('pages.submit-event.create')
        ->set('data.title', 'Test Event Media Upload')
        ->set('data.description', 'Event description')
        ->set('data.event_date', $eventDate)
        ->set('data.prayer_time', EventPrayerTime::SelepasMaghrib->value)
        ->set('data.event_type_id', $eventType->id)
        ->set('data.gender', EventGenderRestriction::All->value)
        ->set('data.age_group', [EventAgeGroup::AllAges->value])
        ->set('data.children_allowed', true)
        ->set('data.topics', $topics->pluck('id')->all())
        ->set('data.speakers', $speakers->pluck('id')->all())
        ->set('data.organizer_type', 'institution')
        ->set('data.organizer_institution_id', $institution->id)
        ->set('data.submitter_name', 'Guest User')
        ->set('data.submitter_email', 'guest@example.com')
        ->set('data.poster', UploadedFile::fake()->image('poster.jpg', 1200, 800))
        ->set('data.gallery', [
            UploadedFile::fake()->image('gallery-1.jpg', 1200, 800),
            UploadedFile::fake()->image('gallery-2.jpg', 1200, 800),
        ])
        ->call('submit')
        ->assertRedirect(route('submit-event.success'));

    $event = Event::where('title', 'Test Event Media Upload')->firstOrFail();

    expect($event->getMedia('poster'))->toHaveCount(1);
    expect($event->getMedia('gallery'))->toHaveCount(2);
    expect($event->topics)->toHaveCount(2);
});

it('does not require guest details for authenticated users', function () {
    $user = User::factory()->create();

    $eventDate = now()->addDay()->toDateString();
    $topics = Topic::factory()->count(2)->create();
    $speakers = \App\Models\Speaker::factory()->count(2)->create();
    $eventType = EventType::factory()->create();
    $institution = Institution::factory()->create(['status' => 'verified']);

    Livewire::actingAs($user)
        ->test('pages.submit-event.create')
        ->set('data.title', 'Logged In Event')
        ->set('data.description', 'Event description')
        ->set('data.event_date', $eventDate)
        ->set('data.prayer_time', EventPrayerTime::SelepasMaghrib->value)
        ->set('data.event_type_id', $eventType->id)
        ->set('data.gender', EventGenderRestriction::All->value)
        ->set('data.age_group', [EventAgeGroup::AllAges->value])
        ->set('data.children_allowed', true)
        ->set('data.topics', $topics->pluck('id')->all())
        ->set('data.speakers', $speakers->pluck('id')->all())
        ->set('data.organizer_type', 'institution')
        ->set('data.organizer_institution_id', $institution->id)
        ->call('submit')
        ->assertRedirect(route('submit-event.success'));

    expect(Event::where('title', 'Logged In Event')->exists())->toBeTrue();
});
