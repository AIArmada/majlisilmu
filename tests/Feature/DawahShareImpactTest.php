<?php

use App\Actions\Fortify\CreateNewUser;
use App\Livewire\Pages\Dashboard\DawahImpactIndex;
use App\Models\DawahShareAttribution;
use App\Models\DawahShareLink;
use App\Models\DawahShareOutcome;
use App\Models\DawahShareVisit;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventSubmission;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Series;
use App\Models\Speaker;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->sharer = User::factory()->create();
});

function dawahShareLandingCookie(Tests\TestCase $testCase, User $sharer, string $url, ?string $title = null): string
{
    $payload = $testCase
        ->actingAs($sharer)
        ->getJson(route('dawah-share.payload', [
            'url' => $url,
            'text' => 'Share this page',
            'title' => $title ?? 'Shared Page',
        ]))
        ->assertOk()
        ->json();

    $landingResponse = $testCase->get($payload['url']);
    $cookie = $landingResponse->getCookie(config('dawah-share.cookie.name'));

    expect($cookie)->not->toBeNull();

    return (string) $cookie?->getValue();
}

function dawahShareExtractSharedUrlFromWhatsAppRedirect(TestResponse $response): string
{
    $location = (string) $response->headers->get('Location');

    expect($location)->not->toBe('');

    parse_str((string) parse_url($location, PHP_URL_QUERY), $query);

    $payload = (string) ($query['text'] ?? '');

    preg_match('/https?:\/\/\S+/', $payload, $matches);

    expect($matches[0] ?? null)->not->toBeNull();

    return (string) ($matches[0] ?? '');
}

/**
 * @return array{domain_tag: \App\Models\Tag, discipline_tag: \App\Models\Tag, institution: Institution, speaker: Speaker}
 */
function dawahShareSubmitEventFixtures(): array
{
    return [
        'domain_tag' => \App\Models\Tag::factory()->domain()->create(),
        'discipline_tag' => \App\Models\Tag::factory()->discipline()->create(),
        'institution' => Institution::factory()->create(['status' => 'verified']),
        'speaker' => Speaker::factory()->create(['status' => 'verified']),
    ];
}

/**
 * @param  array{domain_tag: \App\Models\Tag, discipline_tag: \App\Models\Tag, institution: Institution, speaker: Speaker}  $fixtures
 * @return array<string, mixed>
 */
function dawahShareSubmitEventFormData(array $fixtures, array $overrides = []): array
{
    return array_merge([
        'title' => 'Attributed Submitted Event',
        'domain_tags' => [$fixtures['domain_tag']->id],
        'discipline_tags' => [$fixtures['discipline_tag']->id],
        'event_type' => [\App\Enums\EventType::KuliahCeramah->value],
        'event_date' => now()->addDays(5)->toDateString(),
        'prayer_time' => \App\Enums\EventPrayerTime::SelepasMaghrib->value,
        'description' => 'Attributed event submission description',
        'event_format' => \App\Enums\EventFormat::Physical->value,
        'visibility' => \App\Enums\EventVisibility::Public->value,
        'gender' => \App\Enums\EventGenderRestriction::All->value,
        'age_group' => [\App\Enums\EventAgeGroup::AllAges->value],
        'languages' => [101],
        'organizer_type' => 'institution',
        'organizer_institution_id' => $fixtures['institution']->id,
        'speakers' => [$fixtures['speaker']->id],
        'submitter_name' => 'Guest Submitter',
        'submitter_email' => 'guest-submitter@example.com',
    ], $overrides);
}

test('viewing a shareable page does not create a share link until payload is requested', function () {
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
    ]);

    $this->actingAs($this->sharer)
        ->get(route('events.show', $event))
        ->assertOk();

    expect(DawahShareLink::count())->toBe(0);

    $response = $this->actingAs($this->sharer)
        ->getJson(route('dawah-share.payload', [
            'url' => route('events.show', $event),
            'text' => 'Share this event',
            'title' => $event->title,
        ]));

    $response->assertOk()
        ->assertJsonPath('url', fn (string $url): bool => str_contains($url, 'mi_share='));

    expect(DawahShareLink::count())->toBe(1);

    $this->actingAs($this->sharer)
        ->getJson(route('dawah-share.payload', [
            'url' => route('events.show', $event),
            'text' => 'Share this event',
            'title' => $event->title,
        ]))
        ->assertOk();

    expect(DawahShareLink::count())->toBe(1);
});

test('equivalent filtered search urls reuse the same canonical share link', function () {
    $firstUrl = config('app.url').'/majlis?search=fiqh&speaker_ids%5B0%5D=b&speaker_ids%5B1%5D=a';
    $secondUrl = config('app.url').'/majlis?speaker_ids%5B0%5D=a&speaker_ids%5B1%5D=b&search=fiqh';

    $this->actingAs($this->sharer)
        ->getJson(route('dawah-share.payload', [
            'url' => $firstUrl,
            'text' => 'Explore these results',
            'title' => 'Search Results',
        ]))
        ->assertOk();

    $this->actingAs($this->sharer)
        ->getJson(route('dawah-share.payload', [
            'url' => $secondUrl,
            'text' => 'Explore these results',
            'title' => 'Search Results',
        ]))
        ->assertOk();

    expect(DawahShareLink::count())->toBe(1);

    $link = DawahShareLink::query()->first();

    expect($link)->not->toBeNull()
        ->and($link?->subject_type)->toBe('search');
});

test('opening a shared link creates an attribution and landing visit', function () {
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
    ]);

    $payload = $this->actingAs($this->sharer)
        ->getJson(route('dawah-share.payload', [
            'url' => route('events.show', $event),
            'text' => 'Share this event',
            'title' => $event->title,
        ]))
        ->assertOk()
        ->json();

    $response = $this->get($payload['url']);

    $response->assertOk();
    expect($response->getCookie(config('dawah-share.cookie.name')))->not->toBeNull();
    expect(DawahShareAttribution::count())->toBe(1);
    expect(DawahShareVisit::count())->toBe(1);

    $visit = DawahShareVisit::query()->first();

    expect($visit)->not->toBeNull()
        ->and($visit?->visit_kind)->toBe('landing')
        ->and($visit?->subject_type)->toBe('event');
});

test('new signups are attributed after a shared landing', function () {
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
    ]);

    $payload = $this->actingAs($this->sharer)
        ->getJson(route('dawah-share.payload', [
            'url' => route('events.show', $event),
            'text' => 'Share this event',
            'title' => $event->title,
        ]))
        ->assertOk()
        ->json();

    $landingResponse = $this->get($payload['url']);
    $cookie = $landingResponse->getCookie(config('dawah-share.cookie.name'));

    expect($cookie)->not->toBeNull();

    $request = Request::create('/register', 'POST');
    $request->cookies->set(config('dawah-share.cookie.name'), $cookie?->getValue());
    app()->instance('request', $request);

    $newUser = app(CreateNewUser::class)->create([
        'name' => 'Shared Signup User',
        'email' => 'shared-signup@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect($newUser)->toBeInstanceOf(User::class);

    $this->assertDatabaseHas('dawah_share_outcomes', [
        'outcome_type' => 'signup',
        'actor_user_id' => $newUser->id,
        'sharer_user_id' => $this->sharer->id,
    ]);
});

test('event registrations are attributed after a shared landing', function () {
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
    ]);

    $event->settings()?->delete();

    $event->settings()->create([
        'registration_required' => true,
        'capacity' => 50,
        'registration_opens_at' => now()->subDay(),
        'registration_closes_at' => now()->addDay(),
        'registration_mode' => \App\Enums\RegistrationMode::Event->value,
    ]);

    $payload = $this->actingAs($this->sharer)
        ->getJson(route('dawah-share.payload', [
            'url' => route('events.show', $event),
            'text' => 'Share this event',
            'title' => $event->title,
        ]))
        ->assertOk()
        ->json();

    $landingResponse = $this->get($payload['url']);

    $landingResponse->assertOk();

    $cookie = $landingResponse->getCookie(config('dawah-share.cookie.name'));

    expect($cookie)->not->toBeNull();

    $response = $this->from(route('events.show', $event))
        ->withCookie(config('dawah-share.cookie.name'), $cookie?->getValue())
        ->post(route('events.register', $event), [
            'name' => 'Guest Registrant',
            'email' => 'guest-registrant@example.com',
        ]);

    $response->assertRedirect(route('events.show', $event));

    $this->assertDatabaseHas('registrations', [
        'event_id' => $event->id,
        'email' => 'guest-registrant@example.com',
        'status' => 'registered',
    ]);

    $this->assertDatabaseHas('dawah_share_outcomes', [
        'outcome_type' => 'event_registration',
        'sharer_user_id' => $this->sharer->id,
        'subject_id' => $event->id,
    ]);
});

test('event saves and interests are attributed through authenticated api actions', function (string $routeName, string $outcomeType) {
    $visitor = User::factory()->create();
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDay(),
    ]);

    $payload = $this->actingAs($this->sharer)
        ->getJson(route('dawah-share.payload', [
            'url' => route('events.show', $event),
            'text' => 'Share this event',
            'title' => $event->title,
        ]))
        ->assertOk()
        ->json();

    $landingResponse = $this->get($payload['url']);
    $cookie = $landingResponse->getCookie(config('dawah-share.cookie.name'));

    expect($cookie)->not->toBeNull();

    Sanctum::actingAs($visitor);

    $this->withCookie(config('dawah-share.cookie.name'), $cookie?->getValue())
        ->postJson(route($routeName), [
            'event_id' => $event->id,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('dawah_share_outcomes', [
        'outcome_type' => $outcomeType,
        'sharer_user_id' => $this->sharer->id,
        'actor_user_id' => $visitor->id,
        'subject_id' => $event->id,
    ]);
})->with([
    'event save' => ['api.event-saves.store', 'event_save'],
    'event interest' => ['api.event-interests.store', 'event_interest'],
]);

test('saved-search creation is attributed after a shared search landing', function () {
    $visitor = User::factory()->create();
    $searchUrl = config('app.url').'/majlis?search=fiqh';

    $payload = $this->actingAs($this->sharer)
        ->getJson(route('dawah-share.payload', [
            'url' => $searchUrl,
            'text' => 'Explore these results',
            'title' => 'Search Results',
        ]))
        ->assertOk()
        ->json();

    $landingResponse = $this->get($payload['url']);
    $cookie = $landingResponse->getCookie(config('dawah-share.cookie.name'));

    expect($cookie)->not->toBeNull();

    Sanctum::actingAs($visitor);

    $response = $this->withCookie(config('dawah-share.cookie.name'), $cookie?->getValue())
        ->postJson(route('api.saved-searches.store'), [
            'name' => 'Fiqh Alerts',
            'query' => 'fiqh',
            'notify' => 'daily',
        ]);

    $response->assertCreated();

    $this->assertDatabaseHas('dawah_share_outcomes', [
        'outcome_type' => 'saved_search_created',
        'sharer_user_id' => $this->sharer->id,
        'actor_user_id' => $visitor->id,
        'subject_type' => 'search',
    ]);
});

test('event check-ins are attributed after a shared landing', function () {
    $visitor = User::factory()->create();
    $startsAt = now('Asia/Kuala_Lumpur')->addHour()->utc();

    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'published_at' => now()->subDay(),
        'starts_at' => $startsAt,
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $event->settings()?->delete();

    $cookie = dawahShareLandingCookie($this, $this->sharer, route('events.show', $event), $event->title);

    Livewire::withCookie(config('dawah-share.cookie.name'), $cookie)
        ->actingAs($visitor)
        ->test('pages.events.show', ['event' => $event])
        ->call('checkIn')
        ->assertSet('isCheckedIn', true);

    $checkin = EventCheckin::query()
        ->where('event_id', $event->id)
        ->where('user_id', $visitor->id)
        ->latest('checked_in_at')
        ->firstOrFail();

    $this->assertDatabaseHas('dawah_share_outcomes', [
        'outcome_type' => 'event_checkin',
        'sharer_user_id' => $this->sharer->id,
        'actor_user_id' => $visitor->id,
        'subject_id' => $event->id,
        'outcome_key' => 'event_checkin:checkin:'.$checkin->id,
    ]);
});

test('event submissions are attributed after a shared landing', function () {
    fakePrayerTimesApi();

    $fixtures = dawahShareSubmitEventFixtures();
    $cookie = dawahShareLandingCookie($this, $this->sharer, route('submit-event.create'), 'Submit Event');
    $title = 'Attributed Guest Submission '.uniqid();

    setSubmitEventFormState(
        Livewire::withCookie(config('dawah-share.cookie.name'), $cookie)
            ->test('pages.submit-event.create'),
        dawahShareSubmitEventFormData($fixtures, [
            'title' => $title,
            'submitter_email' => 'submitted-'.uniqid().'@example.com',
        ]),
    )
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('submit-event.success'));

    $event = Event::query()->where('title', $title)->firstOrFail();
    $submission = EventSubmission::query()->where('event_id', $event->id)->firstOrFail();

    $this->assertDatabaseHas('dawah_share_outcomes', [
        'outcome_type' => 'event_submission',
        'sharer_user_id' => $this->sharer->id,
        'actor_user_id' => null,
        'subject_id' => $event->id,
        'outcome_key' => 'event_submission:submission:'.$submission->id,
    ]);
});

test('follow actions are attributed across supported public followable pages', function (string $component, string $routeName, string $parameter, mixed $record, string $outcomeType, string $subjectKey) {
    $visitor = User::factory()->create();
    $cookie = dawahShareLandingCookie($this, $this->sharer, route($routeName, $record), data_get($record, 'title', data_get($record, 'name', 'Shared Page')));

    Livewire::withCookie(config('dawah-share.cookie.name'), $cookie)
        ->actingAs($visitor)
        ->test($component, [$parameter => $record])
        ->assertSet('isFollowing', false)
        ->call('toggleFollow')
        ->assertSet('isFollowing', true);

    $this->assertDatabaseHas('dawah_share_outcomes', [
        'outcome_type' => $outcomeType,
        'sharer_user_id' => $this->sharer->id,
        'actor_user_id' => $visitor->id,
        'subject_id' => $record->id,
        'outcome_key' => $outcomeType.':user:'.$visitor->id.':'.$subjectKey.':'.$record->id,
    ]);
})->with(function (): array {
    $institution = Institution::factory()->create([
        'status' => 'verified',
    ]);

    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $series = Series::factory()->create([
        'visibility' => 'public',
    ]);

    $reference = Reference::factory()->create([
        'is_active' => true,
    ]);

    return [
        'institution follow' => ['pages.institutions.show', 'institutions.show', 'institution', $institution, 'institution_follow', 'institution'],
        'speaker follow' => ['pages.speakers.show', 'speakers.show', 'speaker', $speaker, 'speaker_follow', 'speaker'],
        'series follow' => ['pages.series.show', 'series.show', 'series', $series, 'series_follow', 'series'],
        'reference follow' => ['pages.references.show', 'references.show', 'reference', $reference, 'reference_follow', 'reference'],
    ];
});

test('impact dashboard highlights event check-ins and submissions', function () {
    fakePrayerTimesApi();

    $visitor = User::factory()->create();
    $startsAt = now('Asia/Kuala_Lumpur')->addHour()->utc();
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'published_at' => now()->subDay(),
        'starts_at' => $startsAt,
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $event->settings()?->delete();

    $eventCookie = dawahShareLandingCookie($this, $this->sharer, route('events.show', $event), $event->title);

    Livewire::withCookie(config('dawah-share.cookie.name'), $eventCookie)
        ->actingAs($visitor)
        ->test('pages.events.show', ['event' => $event])
        ->call('checkIn')
        ->assertSet('isCheckedIn', true);

    $submissionFixtures = dawahShareSubmitEventFixtures();
    $submissionCookie = dawahShareLandingCookie($this, $this->sharer, route('submit-event.create'), 'Submit Event');
    $submissionTitle = 'Dashboard Submission '.uniqid();

    setSubmitEventFormState(
        Livewire::withCookie(config('dawah-share.cookie.name'), $submissionCookie)
            ->test('pages.submit-event.create'),
        dawahShareSubmitEventFormData($submissionFixtures, [
            'title' => $submissionTitle,
            'submitter_email' => 'dashboard-submission-'.uniqid().'@example.com',
        ]),
    )
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect(route('submit-event.success'));

    $eventLink = DawahShareLink::query()
        ->where('user_id', $this->sharer->id)
        ->where('destination_url', route('events.show', $event))
        ->firstOrFail();

    $submissionLink = DawahShareLink::query()
        ->where('user_id', $this->sharer->id)
        ->where('destination_url', route('submit-event.create'))
        ->firstOrFail();

    $this->actingAs($this->sharer)
        ->get(route('dashboard.dawah-impact'))
        ->assertOk()
        ->assertSee('Event Check-ins')
        ->assertSee('Event Submissions');

    $this->actingAs($this->sharer)
        ->get(route('dashboard.dawah-impact.links.show', $eventLink))
        ->assertOk()
        ->assertSee('Event Check-ins');

    $this->actingAs($this->sharer)
        ->get(route('dashboard.dawah-impact.links.show', $submissionLink))
        ->assertOk()
        ->assertSee('Event Submissions');
});

test('impact dashboard exposes provider channel performance', function () {
    $visitor = User::factory()->create();
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'published_at' => now(),
        'starts_at' => now()->addDay(),
    ]);

    $redirectResponse = $this->actingAs($this->sharer)
        ->get(route('dawah-share.redirect', [
            'provider' => 'whatsapp',
            'url' => route('events.show', $event),
            'text' => 'Share this event',
            'title' => $event->title,
        ]))
        ->assertRedirect();

    $sharedUrl = dawahShareExtractSharedUrlFromWhatsAppRedirect($redirectResponse);

    $landingResponse = $this->get($sharedUrl);
    $cookie = $landingResponse->getCookie(config('dawah-share.cookie.name'));

    expect($cookie)->not->toBeNull();

    Sanctum::actingAs($visitor);

    $this->withCookie(config('dawah-share.cookie.name'), $cookie?->getValue())
        ->postJson(route('api.event-saves.store'), [
            'event_id' => $event->id,
        ])
        ->assertCreated();

    $attribution = DawahShareAttribution::query()->latest('created_at')->firstOrFail();
    $link = DawahShareLink::query()->where('user_id', $this->sharer->id)->firstOrFail();

    expect(data_get($attribution->metadata, 'share_provider'))->toBe('whatsapp');

    $component = Livewire::actingAs($this->sharer)
        ->test(DawahImpactIndex::class);

    /** @var DawahImpactIndex $instance */
    $instance = $component->instance();
    $providerBreakdown = $instance->providerBreakdown;

    expect($providerBreakdown)->toHaveCount(1)
        ->and($providerBreakdown->first()['provider'])->toBe('whatsapp')
        ->and($providerBreakdown->first()['outbound_shares'])->toBe(1)
        ->and($providerBreakdown->first()['visits'])->toBe(1)
        ->and($providerBreakdown->first()['outcomes'])->toBe(1);

    $this->actingAs($this->sharer)
        ->get(route('dashboard.dawah-impact'))
        ->assertOk()
        ->assertSee('Channel Impact')
        ->assertSee('WhatsApp');

    $this->actingAs($this->sharer)
        ->get(route('dashboard.dawah-impact.links.show', $link))
        ->assertOk()
        ->assertSee('Share Channels')
        ->assertSee('WhatsApp');
});

test('impact dashboard can sort by check-ins and filter by response type', function () {
    $checkinLink = DawahShareLink::factory()->for($this->sharer)->create([
        'title_snapshot' => 'Check-in Heavy Link',
        'destination_url' => route('events.index', ['search' => 'checkin-heavy']),
        'canonical_url' => route('events.index', ['search' => 'checkin-heavy']),
        'subject_type' => 'search',
        'subject_key' => 'search:checkin-heavy',
    ]);

    $submissionLink = DawahShareLink::factory()->for($this->sharer)->create([
        'title_snapshot' => 'Submission Link',
        'destination_url' => route('submit-event.create'),
        'canonical_url' => route('submit-event.create'),
        'subject_type' => 'page',
        'subject_key' => 'page:submit-event',
    ]);

    $sharedAttribution = DawahShareAttribution::factory()->create([
        'link_id' => $checkinLink->id,
        'user_id' => $this->sharer->id,
    ]);

    DawahShareOutcome::factory()->count(2)->create([
        'link_id' => $checkinLink->id,
        'attribution_id' => $sharedAttribution->id,
        'sharer_user_id' => $this->sharer->id,
        'actor_user_id' => User::factory(),
        'outcome_type' => 'event_checkin',
        'subject_type' => 'search',
        'subject_id' => null,
        'subject_key' => 'search:checkin-heavy',
    ]);

    $submissionAttribution = DawahShareAttribution::factory()->create([
        'link_id' => $submissionLink->id,
        'user_id' => $this->sharer->id,
    ]);

    DawahShareOutcome::factory()->create([
        'link_id' => $submissionLink->id,
        'attribution_id' => $submissionAttribution->id,
        'sharer_user_id' => $this->sharer->id,
        'actor_user_id' => null,
        'outcome_type' => 'event_submission',
        'subject_type' => 'page',
        'subject_id' => null,
        'subject_key' => 'page:submit-event',
    ]);

    $component = Livewire::actingAs($this->sharer)
        ->test(DawahImpactIndex::class)
        ->set('sort', 'checkins');

    /** @var DawahImpactIndex $instance */
    $instance = $component->instance();
    $sortedLinkIds = collect($instance->links->items())->pluck('id')->all();

    expect($sortedLinkIds[0] ?? null)->toBe($checkinLink->id);

    $filteredComponent = Livewire::actingAs($this->sharer)
        ->test(DawahImpactIndex::class)
        ->set('outcomeType', 'event_submission');

    /** @var DawahImpactIndex $filteredInstance */
    $filteredInstance = $filteredComponent->instance();
    $filteredLinkIds = collect($filteredInstance->links->items())->pluck('id')->all();

    expect($filteredLinkIds)->toBe([$submissionLink->id]);
});

test('tracked share ui renders across supported public surfaces', function () {
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'published_at' => now(),
        'starts_at' => now()->addDay(),
    ]);

    $institution = Institution::factory()->create([
        'status' => 'verified',
    ]);

    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $series = Series::factory()->create([
        'visibility' => 'public',
    ]);
    $series->events()->attach($event->id, [
        'id' => (string) \Illuminate\Support\Str::uuid(),
        'order_column' => 1,
    ]);

    $reference = Reference::factory()->create([
        'is_active' => true,
    ]);

    $pages = [
        route('events.index', ['search' => 'fiqh']),
        route('events.show', $event),
        route('institutions.show', $institution),
        route('speakers.show', $speaker),
        route('series.show', $series),
        route('references.show', $reference),
    ];

    foreach ($pages as $url) {
        $this->get($url)
            ->assertSuccessful()
            ->assertSee('payloadEndpoint', false)
            ->assertSee('kongsi\\/payload', false);
    }
});

test('impact dashboard pages are only available to the owning sharer', function () {
    $otherUser = User::factory()->create();
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
    ]);

    $this->actingAs($this->sharer)
        ->getJson(route('dawah-share.payload', [
            'url' => route('events.show', $event),
            'text' => 'Share this event',
            'title' => $event->title,
        ]))
        ->assertOk();

    $link = DawahShareLink::query()->firstOrFail();

    $this->actingAs($this->sharer)
        ->get(route('dashboard.dawah-impact'))
        ->assertOk()
        ->assertSee('My shared links');

    $this->actingAs($this->sharer)
        ->get(route('dashboard.dawah-impact.links.show', $link))
        ->assertOk()
        ->assertSee($event->title);

    $this->actingAs($otherUser)
        ->get(route('dashboard.dawah-impact.links.show', $link))
        ->assertNotFound();
});

test('share redirect route records outbound provider clicks without visitor visits', function () {
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
    ]);

    $response = $this->actingAs($this->sharer)
        ->get(route('dawah-share.redirect', [
            'provider' => 'whatsapp',
            'url' => route('events.show', $event),
            'text' => 'Share this event',
            'title' => $event->title,
        ]))
        ->assertRedirect();

    $link = DawahShareLink::query()->firstOrFail();

    expect(DawahShareLink::count())->toBe(1);
    expect(DawahShareVisit::count())->toBe(0);
    expect(rawurldecode((string) $response->headers->get('Location')))->toContain('mi_channel=whatsapp');

    $this->assertDatabaseHas('dawah_share_share_events', [
        'link_id' => $link->id,
        'user_id' => $this->sharer->id,
        'provider' => 'whatsapp',
        'event_type' => 'outbound_click',
    ]);
});
