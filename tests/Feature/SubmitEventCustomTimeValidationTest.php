<?php

use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Models\EventType;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\Topic;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\assertDatabaseMissing;

it('prevents selecting past custom time for today events', function () {
    // Set current time to 07:30 PM (19:30) on 2026-02-01
    Carbon::setTestNow('2026-02-01 19:30:00', 'Asia/Kuala_Lumpur');

    // Create necessary models
    $topic = Topic::factory()->create();
    $institution = Institution::factory()->create();
    $speaker = Speaker::factory()->create();
    $eventType = EventType::factory()->create();

    // Try to submit with a past time (07:00, which is 12.5 hours ago)
    Livewire::test('pages.submit-event.create')
        ->set('data.event_title', 'Test Event')
        ->set('data.topic_id', $topic->id)
        ->set('data.event_type_id', $eventType->id)
        ->set('data.event_date', '2026-02-01')
        ->set('data.prayer_time', EventPrayerTime::LainWaktu->value)
        ->set('data.custom_time', '07:00:00') // Past time
        ->set('data.description', 'Test description')
        ->set('data.event_format', EventFormat::Physical->value)
        ->set('data.gender_restriction', EventGenderRestriction::All->value)
        ->set('data.age_groups', [EventAgeGroup::AllAges->value])
        ->set('data.organizer_type', 'institution')
        ->set('data.organizer_institution_id', $institution->id)
        ->set('data.speakers', [$speaker->id])
        ->set('data.submitter_name', 'Test User')
        ->set('data.submitter_email', 'test@example.com')
        ->set('data.submitter_phone', '601234567890')
        ->call('submit')
        ->assertHasErrors(['data.custom_time'])
        ->assertSee('Masa yang dipilih tidak boleh pada masa lalu untuk majlis hari ini.');

    // Verify no event was created
    assertDatabaseMissing('events', [
        'title' => 'Test Event',
    ]);
});

it('allows selecting custom time for today events if time is in future', function () {
    // Set current time to 07:30 PM (19:30) on 2026-02-01
    Carbon::setTestNow('2026-02-01 19:30:00', 'Asia/Kuala_Lumpur');

    // Create necessary models
    $topic = Topic::factory()->create();
    $institution = Institution::factory()->create();
    $speaker = Speaker::factory()->create();
    $eventType = EventType::factory()->create();

    // Submit with a future time (21:00, which is 1.5 hours from now)
    Livewire::test('pages.submit-event.create')
        ->set('data.event_title', 'Future Event')
        ->set('data.topic_id', $topic->id)
        ->set('data.event_type_id', $eventType->id)
        ->set('data.event_date', '2026-02-01')
        ->set('data.prayer_time', EventPrayerTime::LainWaktu->value)
        ->set('data.custom_time', '21:00:00') // Future time
        ->set('data.description', 'Test description')
        ->set('data.event_format', EventFormat::Physical->value)
        ->set('data.gender_restriction', EventGenderRestriction::All->value)
        ->set('data.age_groups', [EventAgeGroup::AllAges->value])
        ->set('data.organizer_type', 'institution')
        ->set('data.organizer_institution_id', $institution->id)
        ->set('data.speakers', [$speaker->id])
        ->set('data.submitter_name', 'Test User')
        ->set('data.submitter_email', 'test@example.com')
        ->set('data.submitter_phone', '601234567890')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('event-submitted');
});

it('allows any custom time for future dates', function () {
    // Set current time to 07:30 PM (19:30) on 2026-02-01
    Carbon::setTestNow('2026-02-01 19:30:00', 'Asia/Kuala_Lumpur');

    // Create necessary models
    $topic = Topic::factory()->create();
    $institution = Institution::factory()->create();
    $speaker = Speaker::factory()->create();
    $eventType = EventType::factory()->create();

    // Submit with any time for tomorrow (even 07:00 is allowed for future dates)
    Livewire::test('pages.submit-event.create')
        ->set('data.event_title', 'Tomorrow Event')
        ->set('data.topic_id', $topic->id)
        ->set('data.event_type_id', $eventType->id)
        ->set('data.event_date', '2026-02-02') // Tomorrow
        ->set('data.prayer_time', EventPrayerTime::LainWaktu->value)
        ->set('data.custom_time', '07:00:00') // Any time is OK for future dates
        ->set('data.description', 'Test description')
        ->set('data.event_format', EventFormat::Physical->value)
        ->set('data.gender_restriction', EventGenderRestriction::All->value)
        ->set('data.age_groups', [EventAgeGroup::AllAges->value])
        ->set('data.organizer_type', 'institution')
        ->set('data.organizer_institution_id', $institution->id)
        ->set('data.speakers', [$speaker->id])
        ->set('data.submitter_name', 'Test User')
        ->set('data.submitter_email', 'test@example.com')
        ->set('data.submitter_phone', '601234567890')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertDispatched('event-submitted');
});
