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

beforeEach(function () {
    fakePrayerTimesApi();
});

/**
 * @return array{domain_tag: Tag, discipline_tag: Tag, institution: Institution, speaker: Speaker}
 */
function submitEventEndTimeFixtures(): array
{
    return [
        'domain_tag' => Tag::factory()->domain()->create(),
        'discipline_tag' => Tag::factory()->discipline()->create(),
        'institution' => Institution::factory()->create(['status' => 'verified']),
        'speaker' => Speaker::factory()->create(['status' => 'verified']),
    ];
}

/**
 * @param  array{domain_tag: Tag, discipline_tag: Tag, institution: Institution, speaker: Speaker}  $fixtures
 * @return array<string, mixed>
 */
function submitEventEndTimeFormData(array $fixtures, array $overrides = []): array
{
    return array_merge([
        'title' => 'Submit Event End Time',
        'domain_tags' => [$fixtures['domain_tag']->id],
        'discipline_tags' => [$fixtures['discipline_tag']->id],
        'event_type' => [\App\Enums\EventType::KuliahCeramah->value],
        'event_date' => now()->addDays(5)->toDateString(),
        'prayer_time' => EventPrayerTime::SelepasMaghrib->value,
        'description' => 'Test description',
        'event_format' => EventFormat::Physical->value,
        'visibility' => EventVisibility::Public->value,
        'gender' => EventGenderRestriction::All->value,
        'age_group' => [EventAgeGroup::AllAges->value],
        'languages' => [101],
        'organizer_type' => 'institution',
        'organizer_institution_id' => $fixtures['institution']->id,
        'speakers' => [$fixtures['speaker']->id],
        'submitter_name' => 'Test User',
        'submitter_email' => 'test@example.com',
    ], $overrides);
}

it('can submit event with optional end time', function () {
    $fixtures = submitEventEndTimeFixtures();

    setSubmitEventFormState(
        Livewire::test('pages.submit-event.create'),
        submitEventEndTimeFormData($fixtures, [
            'title' => 'Event With End Time',
            'end_time' => '21:30',
        ]),
    )
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
    $fixtures = submitEventEndTimeFixtures();

    setSubmitEventFormState(
        Livewire::test('pages.submit-event.create'),
        submitEventEndTimeFormData($fixtures, [
            'title' => 'Event Without End Time',
        ]),
    )
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('submit-event.success'));

    $event = Event::where('title', 'Event Without End Time')->firstOrFail();
    expect($event->ends_at)->toBeNull();
});

it('can submit event with custom time and end time', function () {
    $fixtures = submitEventEndTimeFixtures();

    setSubmitEventFormState(
        Livewire::test('pages.submit-event.create'),
        submitEventEndTimeFormData($fixtures, [
            'title' => 'Custom Time With End Time',
            'prayer_time' => EventPrayerTime::LainWaktu->value,
            'custom_time' => '10:00',
            'end_time' => '12:00',
        ]),
    )
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('submit-event.success'));

    $event = Event::where('title', 'Custom Time With End Time')->firstOrFail();
    // Compare in the same timezone used in the form
    expect($event->starts_at->timezone('Asia/Kuala_Lumpur')->format('H:i'))->toBe('10:00');
    expect($event->ends_at->timezone('Asia/Kuala_Lumpur')->format('H:i'))->toBe('12:00');
    expect($event->ends_at->toDateString())->toBe($event->starts_at->toDateString());
});
