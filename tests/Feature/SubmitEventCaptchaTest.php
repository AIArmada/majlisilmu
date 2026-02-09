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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

uses(RefreshDatabase::class);

/**
 * @return array{domain_tag: Tag, discipline_tag: Tag, institution: Institution, speaker: Speaker}
 */
function submitEventCaptchaFixtures(): array
{
    return [
        'domain_tag' => Tag::factory()->domain()->create(['status' => 'verified']),
        'discipline_tag' => Tag::factory()->discipline()->create(['status' => 'verified']),
        'institution' => Institution::factory()->create(['status' => 'verified']),
        'speaker' => Speaker::factory()->create(['status' => 'verified']),
    ];
}

/**
 * @param  array{domain_tag: Tag, discipline_tag: Tag, institution: Institution, speaker: Speaker}  $fixtures
 */
function fillSubmitEventCaptchaForm(mixed $component, array $fixtures, string $title): void
{
    $component
        ->set('data.title', $title)
        ->set('data.domain_tags', [$fixtures['domain_tag']->id])
        ->set('data.discipline_tags', [$fixtures['discipline_tag']->id])
        ->set('data.event_type', [\App\Enums\EventType::KuliahCeramah->value])
        ->set('data.event_date', now()->addDays(5)->toDateString())
        ->set('data.prayer_time', EventPrayerTime::SelepasMaghrib->value)
        ->set('data.description', 'Test description')
        ->set('data.event_format', EventFormat::Physical->value)
        ->set('data.visibility', EventVisibility::Public->value)
        ->set('data.gender', EventGenderRestriction::All->value)
        ->set('data.age_group', [EventAgeGroup::AllAges->value])
        ->set('data.languages', [101])
        ->set('data.organizer_type', 'institution')
        ->set('data.organizer_institution_id', $fixtures['institution']->id)
        ->set('data.speakers', [$fixtures['speaker']->id])
        ->set('data.submitter_name', 'Test User')
        ->set('data.submitter_email', 'test@example.com');
}

it('rejects submission when turnstile verification fails', function () {
    config([
        'services.turnstile.enabled' => true,
        'services.turnstile.site_key' => 'test-site-key',
        'services.turnstile.secret_key' => 'test-secret-key',
    ]);

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => false], 200),
    ]);

    $fixtures = submitEventCaptchaFixtures();

    $component = Livewire::test('pages.submit-event.create');
    fillSubmitEventCaptchaForm($component, $fixtures, 'Event Captcha Failed');

    $component
        ->set('data.captcha_token', 'invalid-token')
        ->call('submit')
        ->assertHasErrors(['data.captcha_token']);

    expect(Event::where('title', 'Event Captcha Failed')->exists())->toBeFalse();
});

it('accepts submission when turnstile verification succeeds', function () {
    config([
        'services.turnstile.enabled' => true,
        'services.turnstile.site_key' => 'test-site-key',
        'services.turnstile.secret_key' => 'test-secret-key',
    ]);

    Http::fake([
        'challenges.cloudflare.com/*' => Http::response(['success' => true], 200),
    ]);

    $fixtures = submitEventCaptchaFixtures();

    $component = Livewire::test('pages.submit-event.create');
    fillSubmitEventCaptchaForm($component, $fixtures, 'Event Captcha Passed');

    $component
        ->set('data.captcha_token', 'valid-token')
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('submit-event.success'));

    expect(Event::where('title', 'Event Captcha Passed')->exists())->toBeTrue();

    Http::assertSentCount(1);
});
