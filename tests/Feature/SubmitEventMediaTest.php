<?php

use App\Enums\EventAgeGroup;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    fakePrayerTimesApi();
});

/**
 * @return array{event_date: string, domain_tag_ids: array<int, string>, discipline_tag_ids: array<int, string>, speaker_ids: array<int, string>, institution_id: string}
 */
function submitEventMediaFixtures(): array
{
    return [
        'event_date' => now()->addDay()->toDateString(),
        'domain_tag_ids' => Tag::factory()->domain()->count(2)->create()->pluck('id')->all(),
        'discipline_tag_ids' => Tag::factory()->discipline()->count(1)->create()->pluck('id')->all(),
        'speaker_ids' => Speaker::factory()->count(2)->create()->pluck('id')->all(),
        'institution_id' => Institution::factory()->create(['status' => 'verified'])->id,
    ];
}

/**
 * @param  array{event_date: string, domain_tag_ids: array<int, string>, discipline_tag_ids: array<int, string>, speaker_ids: array<int, string>, institution_id: string}  $fixtures
 * @return array<string, mixed>
 */
function submitEventMediaFormData(array $fixtures, array $overrides = []): array
{
    return array_merge([
        'title' => 'Test Event Media Upload',
        'description' => 'Event description',
        'event_date' => $fixtures['event_date'],
        'prayer_time' => EventPrayerTime::SelepasMaghrib->value,
        'event_type' => [EventType::KuliahCeramah->value],
        'gender' => EventGenderRestriction::All->value,
        'age_group' => [EventAgeGroup::AllAges->value],
        'languages' => [101],
        'children_allowed' => true,
        'domain_tags' => $fixtures['domain_tag_ids'],
        'discipline_tags' => $fixtures['discipline_tag_ids'],
        'speakers' => $fixtures['speaker_ids'],
        'organizer_type' => 'institution',
        'organizer_institution_id' => $fixtures['institution_id'],
        'submitter_name' => 'Guest User',
        'submitter_email' => 'guest@example.com',
        'visibility' => EventVisibility::Public->value,
    ], $overrides);
}

it('stores poster and gallery uploads when submitting an event', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $fixtures = submitEventMediaFixtures();

    $component = setSubmitEventFormState(
        Livewire::test('pages.submit-event.create'),
        submitEventMediaFormData($fixtures),
    );

    $component
        ->fillForm([
            'poster' => UploadedFile::fake()->image('poster.jpg', 1200, 800),
            'gallery' => [
                UploadedFile::fake()->image('gallery-1.jpg', 1200, 800),
                UploadedFile::fake()->image('gallery-2.jpg', 1200, 800),
            ],
        ])
        ->call('submit')
        ->assertRedirect(route('submit-event.success'));

    $event = Event::where('title', 'Test Event Media Upload')->firstOrFail();

    expect($event->getMedia('poster'))->toHaveCount(1);
    expect($event->getMedia('gallery'))->toHaveCount(2);
    expect($event->tags)->toHaveCount(3);
});

it('does not require guest details for authenticated users', function () {
    $user = User::factory()->create();

    $fixtures = submitEventMediaFixtures();

    setSubmitEventFormState(
        Livewire::actingAs($user)->test('pages.submit-event.create'),
        submitEventMediaFormData($fixtures, [
            'title' => 'Logged In Event',
            'submitter_name' => null,
            'submitter_email' => null,
        ]),
    )
        ->call('submit')
        ->assertRedirect(route('submit-event.success'));

    expect(Event::where('title', 'Logged In Event')->exists())->toBeTrue();
});
