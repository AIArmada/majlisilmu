<?php

use App\Enums\EventAgeGroup;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\EventVisibility;
use App\Enums\TagType;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\Tag;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    fakePrayerTimesApi();

    $this->domainTag = Tag::factory()->create(['type' => TagType::Domain->value]);
    $this->disciplineTag = Tag::factory()->create(['type' => TagType::Discipline->value]);
});

/**
 * @return array<string, mixed>
 */
function submitEventEntityAccessPayload(Tag $domainTag, Tag $disciplineTag, array $overrides = []): array
{
    return array_merge([
        'title' => 'Entity Access Submission',
        'description' => 'Entity access enforcement test.',
        'event_date' => now()->addDays(5)->format('Y-m-d'),
        'prayer_time' => EventPrayerTime::SelepasMaghrib->value,
        'event_type' => [\App\Enums\EventType::KuliahCeramah->value],
        'event_format' => \App\Enums\EventFormat::Physical->value,
        'visibility' => EventVisibility::Public->value,
        'gender' => EventGenderRestriction::All->value,
        'age_group' => [EventAgeGroup::AllAges->value],
        'languages' => [101],
        'domain_tags' => [$domainTag->id],
        'discipline_tags' => [$disciplineTag->id],
        'submitter_name' => 'Guest Submitter',
        'submitter_email' => 'guest@example.com',
    ], $overrides);
}

it('rejects guest submission when organizer institution is locked to members', function () {
    $lockedInstitution = Institution::factory()->create([
        'allow_public_event_submission' => false,
        'status' => 'verified',
        'is_active' => true,
    ]);

    $publicSpeaker = Speaker::factory()->create([
        'allow_public_event_submission' => true,
        'status' => 'verified',
        'is_active' => true,
    ]);

    setSubmitEventFormState(
        Livewire::test('pages.submit-event.create'),
        submitEventEntityAccessPayload($this->domainTag, $this->disciplineTag, [
            'organizer_type' => 'institution',
            'organizer_institution_id' => $lockedInstitution->id,
            'speakers' => [$publicSpeaker->id],
        ]),
    )
        ->call('submit')
        ->assertHasErrors(['data.organizer_institution_id']);
});

it('rejects guest submission when selected speakers include locked speaker', function () {
    $publicInstitution = Institution::factory()->create([
        'allow_public_event_submission' => true,
        'status' => 'verified',
        'is_active' => true,
    ]);

    $lockedSpeaker = Speaker::factory()->create([
        'allow_public_event_submission' => false,
        'status' => 'verified',
        'is_active' => true,
    ]);

    setSubmitEventFormState(
        Livewire::test('pages.submit-event.create'),
        submitEventEntityAccessPayload($this->domainTag, $this->disciplineTag, [
            'organizer_type' => 'institution',
            'organizer_institution_id' => $publicInstitution->id,
            'speakers' => [$lockedSpeaker->id],
        ]),
    )
        ->call('submit')
        ->assertHasErrors(['data.speakers']);
});

it('allows authenticated members to submit locked institution and speaker entities', function () {
    $user = User::factory()->create();

    $lockedInstitution = Institution::factory()->create([
        'allow_public_event_submission' => false,
        'status' => 'verified',
        'is_active' => true,
    ]);

    $lockedSpeaker = Speaker::factory()->create([
        'allow_public_event_submission' => false,
        'status' => 'verified',
        'is_active' => true,
    ]);

    $lockedInstitution->members()->syncWithoutDetaching([$user->id]);
    $lockedSpeaker->members()->syncWithoutDetaching([$user->id]);

    setSubmitEventFormState(
        Livewire::actingAs($user)->test('pages.submit-event.create'),
        submitEventEntityAccessPayload($this->domainTag, $this->disciplineTag, [
            'title' => 'Member Locked Access Event',
            'organizer_type' => 'speaker',
            'organizer_speaker_id' => $lockedSpeaker->id,
            'speakers' => [$lockedSpeaker->id],
            'location_type' => 'institution',
            'location_institution_id' => $lockedInstitution->id,
        ]),
    )
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('submit-event.success'));

    $event = Event::query()->where('title', 'Member Locked Access Event')->first();

    expect($event)->not->toBeNull();
    expect($event?->organizer_id)->toBe($lockedSpeaker->id);
    expect($event?->institution_id)->toBe($lockedInstitution->id);
});
