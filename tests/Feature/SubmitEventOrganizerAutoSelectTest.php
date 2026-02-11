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

beforeEach(function () {
    fakePrayerTimesApi();
});

/**
 * @return array{domain_tag: Tag, discipline_tag: Tag, speaker: Speaker, venue: Venue}
 */
function submitEventOrganizerFixtures(): array
{
    return [
        'speaker' => Speaker::factory()->create(['status' => 'verified']),
        'domain_tag' => Tag::factory()->domain()->create(),
        'discipline_tag' => Tag::factory()->discipline()->create(),
        'venue' => Venue::factory()->create(['status' => 'verified']),
    ];
}

/**
 * @param  array{domain_tag: Tag, discipline_tag: Tag, speaker: Speaker, venue: Venue}  $fixtures
 * @return array<string, mixed>
 */
function submitEventOrganizerFormData(array $fixtures, array $overrides = []): array
{
    return array_merge([
        'organizer_type' => 'speaker',
        'organizer_speaker_id' => $fixtures['speaker']->id,
        'speakers' => [$fixtures['speaker']->id],
        'title' => 'Auto Select Speaker Event',
        'event_date' => now()->addDay()->toDateString(),
        'prayer_time' => EventPrayerTime::SelepasMaghrib->value,
        'event_type' => [\App\Enums\EventType::KuliahCeramah->value],
        'gender' => EventGenderRestriction::All->value,
        'age_group' => [EventAgeGroup::AllAges->value],
        'languages' => [101],
        'description' => 'Test description',
        'domain_tags' => [$fixtures['domain_tag']->id],
        'discipline_tags' => [$fixtures['discipline_tag']->id],
        'submitter_name' => 'Test User',
        'submitter_email' => 'test@example.com',
        'location_type' => 'venue',
        'location_venue_id' => $fixtures['venue']->id,
        'visibility' => EventVisibility::Public->value,
    ], $overrides);
}

it('assigns the speaker as event speaker when speaker is the organizer', function () {
    $fixtures = submitEventOrganizerFixtures();

    setSubmitEventFormState(
        Livewire::test('pages.submit-event.create'),
        submitEventOrganizerFormData($fixtures),
    )
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('submit-event.success'));

    $event = Event::where('title', 'Auto Select Speaker Event')->firstOrFail();
    expect($event->speakers)->toHaveCount(1);
    expect($event->speakers->first()->id)->toBe($fixtures['speaker']->id);
    expect($event->organizer_type)->toBe(Speaker::class);
    expect($event->organizer_id)->toBe($fixtures['speaker']->id);
});
