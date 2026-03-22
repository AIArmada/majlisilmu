<?php

use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\Tag;
use App\Models\Venue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    config()->set('cache.default', 'array');
    app('cache')->setDefaultDriver('array');
    Cache::flush();

    fakePrayerTimesApi();
});

/**
 * @return list<string>
 */
function primeMajlisListingCache(): array
{
    $keys = [
        'default_events_search',
        'default_events_search_v2',
        'kimi_home_stats',
        'kimi_featured_events',
        'kimi_featured_events_v2',
        'kimi_upcoming_events',
        'kimi_upcoming_events_v2',
        'states_my',
        'states_my_v2',
    ];
    $supportedLocales = array_keys(config('app.supported_locales', []));

    if ($supportedLocales === []) {
        $supportedLocales = [config('app.locale', 'ms')];
    }

    foreach ($supportedLocales as $locale) {
        $keys[] = "events_topics_{$locale}";
        $keys[] = "events_institutions_{$locale}";
        $keys[] = "events_institutions_{$locale}_v2";
        $keys[] = "events_speakers_{$locale}";
        $keys[] = "events_speakers_{$locale}_v2";
        $keys[] = "events_disciplines_{$locale}_v2";
        $keys[] = "events_domains_{$locale}_v2";
        $keys[] = "events_sources_{$locale}_v2";
        $keys[] = "events_issues_{$locale}_v2";
        $keys[] = "events_references_{$locale}_v2";
        $keys[] = "events_venues_{$locale}_v2";
    }

    foreach ($keys as $key) {
        Cache::put($key, 'primed', now()->addMinutes(10));
    }

    return $keys;
}

/**
 * @param  list<string>  $keys
 */
function assertMajlisCacheWasCleared(array $keys): void
{
    foreach ($keys as $key) {
        expect(Cache::has($key))
            ->toBeFalse("Expected cache key [{$key}] to be cleared.");
    }
}

it('clears majlis listing cache when event is submitted from public submit form', function () {
    $domainTag = Tag::factory()->domain()->create();
    $disciplineTag = Tag::factory()->discipline()->create();
    $institution = Institution::factory()->create(['status' => 'verified']);
    $speaker = Speaker::factory()->create(['status' => 'verified']);

    $keys = primeMajlisListingCache();

    setSubmitEventFormState(
        Livewire::test('pages.submit-event.create'),
        [
            'title' => 'Cache Bust Submit '.Str::random(6),
            'description' => 'Cache invalidation check',
            'event_date' => now()->addDays(6)->toDateString(),
            'prayer_time' => EventPrayerTime::SelepasMaghrib->value,
            'event_type' => [EventType::KuliahCeramah->value],
            'event_format' => EventFormat::Physical->value,
            'visibility' => EventVisibility::Public->value,
            'gender' => EventGenderRestriction::All->value,
            'age_group' => [EventAgeGroup::AllAges->value],
            'languages' => [101],
            'organizer_type' => 'institution',
            'organizer_institution_id' => $institution->id,
            'speakers' => [$speaker->id],
            'domain_tags' => [$domainTag->id],
            'discipline_tags' => [$disciplineTag->id],
            'submitter_name' => 'Cache Tester',
            'submitter_email' => 'cache-tester@example.com',
        ],
    )
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('submit-event.success'));

    assertMajlisCacheWasCleared($keys);
});

it('clears majlis listing cache when events are edited or deleted', function () {
    $event = Event::factory()->create();

    $keysAfterPrimeForUpdate = primeMajlisListingCache();
    $event->update(['title' => 'Updated '.Str::random(8)]);
    assertMajlisCacheWasCleared($keysAfterPrimeForUpdate);

    $keysAfterPrimeForDelete = primeMajlisListingCache();
    $event->delete();
    assertMajlisCacheWasCleared($keysAfterPrimeForDelete);
});

it('clears majlis listing cache when admin-managed related records are created', function () {
    $keysAfterInstitutionPrime = primeMajlisListingCache();
    Institution::factory()->create(['status' => 'verified']);
    assertMajlisCacheWasCleared($keysAfterInstitutionPrime);

    $keysAfterSpeakerPrime = primeMajlisListingCache();
    Speaker::factory()->create(['status' => 'verified']);
    assertMajlisCacheWasCleared($keysAfterSpeakerPrime);

    $keysAfterTagPrime = primeMajlisListingCache();
    Tag::factory()->issue()->create();
    assertMajlisCacheWasCleared($keysAfterTagPrime);

    $keysAfterVenuePrime = primeMajlisListingCache();
    Venue::factory()->create(['status' => 'verified']);
    assertMajlisCacheWasCleared($keysAfterVenuePrime);
});
