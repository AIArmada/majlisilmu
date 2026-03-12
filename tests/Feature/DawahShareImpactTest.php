<?php

use AIArmada\Affiliates\Models\Affiliate;
use AIArmada\Affiliates\Models\AffiliateAttribution;
use AIArmada\Affiliates\Models\AffiliateConversion;
use AIArmada\Affiliates\Models\AffiliateLink;
use AIArmada\Affiliates\Models\AffiliateTouchpoint;
use AIArmada\Affiliates\States\ApprovedConversion;
use App\Actions\Fortify\CreateNewUser;
use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\RegistrationMode;
use App\Livewire\Pages\Dashboard\DawahImpactIndex;
use App\Models\Event;
use App\Models\EventCheckin;
use App\Models\EventSubmission;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Series;
use App\Models\Speaker;
use App\Models\Tag;
use App\Models\User;
use App\Services\ShareTrackingAnalyticsService;
use App\Services\ShareTrackingService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

beforeEach(function (): void {
    $this->sharer = User::factory()->create();
});

function dawahShareLandingCookie(TestCase $testCase, User $sharer, string $url, ?string $title = null): string
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

    auth()->logout();

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

    return $matches[0] ?? '';
}

/**
 * @return array{domain_tag: Tag, discipline_tag: Tag, institution: Institution, speaker: Speaker}
 */
function dawahShareSubmitEventFixtures(): array
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
function dawahShareSubmitEventFormData(array $fixtures, array $overrides = []): array
{
    return array_merge([
        'title' => 'Attributed Submitted Event',
        'domain_tags' => [$fixtures['domain_tag']->id],
        'discipline_tags' => [$fixtures['discipline_tag']->id],
        'event_type' => [EventType::KuliahCeramah->value],
        'event_date' => now()->addDays(5)->toDateString(),
        'prayer_time' => EventPrayerTime::SelepasMaghrib->value,
        'description' => 'Attributed event submission description',
        'event_format' => EventFormat::Physical->value,
        'visibility' => EventVisibility::Public->value,
        'gender' => EventGenderRestriction::All->value,
        'age_group' => [EventAgeGroup::AllAges->value],
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

    expect(AffiliateLink::count())->toBe(0);

    $response = $this->actingAs($this->sharer)
        ->getJson(route('dawah-share.payload', [
            'url' => route('events.show', $event),
            'text' => 'Share this event',
            'title' => $event->title,
        ]));

    $response->assertOk()
        ->assertJsonPath('url', fn (string $url): bool => str_contains($url, 'mi_share='));

    expect(AffiliateLink::count())->toBe(1);

    $this->actingAs($this->sharer)
        ->getJson(route('dawah-share.payload', [
            'url' => route('events.show', $event),
            'text' => 'Share this event',
            'title' => $event->title,
        ]))
        ->assertOk();

    expect(AffiliateLink::count())->toBe(1);
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

    expect(AffiliateLink::count())->toBe(1);

    $link = AffiliateLink::query()->first();

    expect($link)->not->toBeNull()
        ->and($link?->subject_type)->toBe('search')
        ->and($link?->subject_identifier)->toContain('search:');
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
    expect(AffiliateAttribution::count())->toBe(1);
    expect(AffiliateTouchpoint::query()->where('metadata->event_type', 'visit')->count())->toBe(1);

    $visit = AffiliateTouchpoint::query()->where('metadata->event_type', 'visit')->first();

    expect($visit)->not->toBeNull()
        ->and(data_get($visit?->metadata, 'visit_kind'))->toBe('landing')
        ->and(data_get($visit?->metadata, 'subject_type'))->toBe('event');
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

    AffiliateAttribution::query()->latest('first_seen_at')->firstOrFail()->forceFill([
        'subject_type' => 'event',
        'subject_identifier' => 'event:canonical-signup-subject',
        'cart_identifier' => (string) $event->id,
        'subject_title_snapshot' => $event->title,
        'metadata' => [
            'tracking_mode' => 'landing',
            'link_id' => AffiliateLink::query()->value('id'),
            'share_provider' => 'direct',
            'sharer_user_id' => $this->sharer->id,
            'visitor_key' => 'signup-subject-visitor',
            'subject_type' => 'page',
            'subject_id' => 'legacy-subject-id',
            'subject_key' => 'page:legacy-subject',
            'title_snapshot' => 'Legacy Signup Title',
        ],
    ])->save();

    $newUser = app(CreateNewUser::class)->create([
        'name' => 'Shared Signup User',
        'email' => 'shared-signup@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    expect($newUser)->toBeInstanceOf(User::class);

    $this->assertDatabaseHas('affiliate_conversions', [
        'conversion_type' => 'signup',
        'subject_type' => 'event',
        'subject_identifier' => 'event:canonical-signup-subject',
        'cart_identifier' => $event->id,
        'subject_title_snapshot' => $event->title,
        'external_reference' => 'signup:user:'.$newUser->id,
        'metadata->actor_user_id' => $newUser->id,
        'metadata->sharer_user_id' => $this->sharer->id,
        'metadata->subject_type' => 'event',
        'metadata->subject_id' => $event->id,
        'metadata->subject_key' => 'event:canonical-signup-subject',
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
        'registration_mode' => RegistrationMode::Event->value,
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

    $this->assertDatabaseHas('affiliate_conversions', [
        'conversion_type' => 'event_registration',
        'metadata->sharer_user_id' => $this->sharer->id,
        'metadata->subject_id' => $event->id,
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

    $this->withCredentials()
        ->withCookie(config('dawah-share.cookie.name'), $cookie?->getValue())
        ->postJson(route($routeName), [
            'event_id' => $event->id,
        ])
        ->assertCreated();

    $this->assertDatabaseHas('affiliate_conversions', [
        'conversion_type' => $outcomeType,
        'metadata->sharer_user_id' => $this->sharer->id,
        'metadata->actor_user_id' => $visitor->id,
        'metadata->subject_id' => $event->id,
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

    Livewire::withCookie(config('dawah-share.cookie.name'), $cookie?->getValue())
        ->actingAs($visitor)
        ->test('pages.saved-searches.index')
        ->set('name', 'Fiqh Alerts')
        ->set('query', 'fiqh')
        ->set('notify', 'daily')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('affiliate_conversions', [
        'conversion_type' => 'saved_search_created',
        'metadata->sharer_user_id' => $this->sharer->id,
        'metadata->actor_user_id' => $visitor->id,
        'metadata->subject_type' => 'search',
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

    $this->assertDatabaseHas('affiliate_conversions', [
        'conversion_type' => 'event_checkin',
        'external_reference' => 'event_checkin:checkin:'.$checkin->id,
        'metadata->sharer_user_id' => $this->sharer->id,
        'metadata->actor_user_id' => $visitor->id,
        'metadata->subject_id' => $event->id,
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

    $this->assertDatabaseHas('affiliate_conversions', [
        'conversion_type' => 'event_submission',
        'external_reference' => 'event_submission:submission:'.$submission->id,
        'metadata->sharer_user_id' => $this->sharer->id,
        'metadata->subject_id' => $event->id,
    ]);
});

test('follow actions are attributed across supported public followable pages', function (string $component, string $routeName, string $parameter, callable $recordFactory, string $outcomeType, string $subjectKey) {
    $visitor = User::factory()->create();
    $record = $recordFactory();

    $cookie = dawahShareLandingCookie($this, $this->sharer, route($routeName, $record), data_get($record, 'title', data_get($record, 'name', 'Shared Page')));

    Livewire::withCookie(config('dawah-share.cookie.name'), $cookie)
        ->actingAs($visitor)
        ->test($component, [$parameter => $record])
        ->assertSet('isFollowing', false)
        ->call('toggleFollow')
        ->assertSet('isFollowing', true);

    $this->assertDatabaseHas('affiliate_conversions', [
        'conversion_type' => $outcomeType,
        'external_reference' => $outcomeType.':user:'.$visitor->id.':'.$subjectKey.':'.$record->id,
        'metadata->sharer_user_id' => $this->sharer->id,
        'metadata->actor_user_id' => $visitor->id,
        'metadata->subject_id' => $record->id,
    ]);
})->with(fn (): array => [
    'institution follow' => ['pages.institutions.show', 'institutions.show', 'institution', fn () => Institution::factory()->create([
        'status' => 'verified',
    ]), 'institution_follow', 'institution'],
    'speaker follow' => ['pages.speakers.show', 'speakers.show', 'speaker', fn () => Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]), 'speaker_follow', 'speaker'],
    'series follow' => ['pages.series.show', 'series.show', 'series', fn () => Series::factory()->create([
        'visibility' => 'public',
    ]), 'series_follow', 'series'],
    'reference follow' => ['pages.references.show', 'references.show', 'reference', fn () => Reference::factory()->create([
        'is_active' => true,
    ]), 'reference_follow', 'reference'],
]);

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

    $eventLink = AffiliateLink::query()
        ->whereHas('affiliate', fn ($query) => $query
            ->where('metadata->majlis_user_id', $this->sharer->getKey()))
        ->where('destination_url', route('events.show', $event))
        ->firstOrFail();

    $submissionLink = AffiliateLink::query()
        ->whereHas('affiliate', fn ($query) => $query
            ->where('metadata->majlis_user_id', $this->sharer->getKey()))
        ->where('destination_url', route('submit-event.create'))
        ->firstOrFail();

    $this->actingAs($this->sharer)
        ->get(route('dashboard.dawah-impact'))
        ->assertOk()
        ->assertSee('Event Check-ins')
        ->assertSee('Event Submissions');

    $this->actingAs($this->sharer)
        ->get(route('dashboard.dawah-impact.links.show', ['link' => $eventLink->id]))
        ->assertOk()
        ->assertSee('Event Check-ins');

    $this->actingAs($this->sharer)
        ->get(route('dashboard.dawah-impact.links.show', ['link' => $submissionLink->id]))
        ->assertOk()
        ->assertSee('Event Submissions');
});

test('impact dashboard top subjects use canonical affiliate subject fields', function () {
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'published_at' => now(),
        'starts_at' => now()->addDay(),
    ]);

    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $this->actingAs($this->sharer)
        ->getJson(route('dawah-share.payload', [
            'url' => route('events.show', $event),
            'text' => 'Share this event',
            'title' => $event->title,
        ]))
        ->assertOk();

    $this->actingAs($this->sharer)
        ->getJson(route('dawah-share.payload', [
            'url' => route('speakers.show', $speaker),
            'text' => 'Share this speaker',
            'title' => $speaker->formatted_name,
        ]))
        ->assertOk();

    $eventLink = AffiliateLink::query()->where('destination_url', route('events.show', $event))->firstOrFail();
    $speakerLink = AffiliateLink::query()->where('destination_url', route('speakers.show', $speaker))->firstOrFail();
    $affiliate = Affiliate::query()->findOrFail($eventLink->affiliate_id);

    $eventAttribution = AffiliateAttribution::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'subject_type' => 'event',
        'subject_identifier' => $eventLink->subject_identifier,
        'subject_instance' => 'share_tracking_link',
        'subject_title_snapshot' => $event->title,
        'cookie_value' => 'event-top-subject-cookie',
        'landing_url' => $eventLink->destination_url,
        'metadata' => [
            'tracking_mode' => 'landing',
            'link_id' => $eventLink->id,
            'sharer_user_id' => $this->sharer->id,
        ],
        'first_seen_at' => now()->subHour(),
        'last_seen_at' => now()->subHour(),
    ]);

    AffiliateTouchpoint::query()->create([
        'affiliate_attribution_id' => $eventAttribution->id,
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'subject_type' => 'event',
        'subject_identifier' => $eventLink->subject_identifier,
        'subject_instance' => 'share_tracking_link',
        'subject_title_snapshot' => $event->title,
        'metadata' => [
            'event_type' => 'visit',
            'link_id' => $eventLink->id,
            'visited_url' => $eventLink->destination_url,
            'visitor_key' => 'event-top-subject-visitor',
            'visit_kind' => 'landing',
            'subject_id' => 'legacy-event-visit-id',
        ],
        'touched_at' => now()->subMinutes(50),
    ]);

    AffiliateTouchpoint::query()->create([
        'affiliate_attribution_id' => $eventAttribution->id,
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'subject_type' => 'event',
        'subject_identifier' => $eventLink->subject_identifier,
        'subject_instance' => 'share_tracking_link',
        'subject_title_snapshot' => $event->title,
        'metadata' => [
            'event_type' => 'visit',
            'link_id' => $eventLink->id,
            'visited_url' => $eventLink->destination_url,
            'visitor_key' => 'event-top-subject-visitor-2',
            'visit_kind' => 'landing',
            'subject_id' => 'legacy-event-visit-id-2',
        ],
        'touched_at' => now()->subMinutes(45),
    ]);

    AffiliateConversion::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'affiliate_attribution_id' => $eventAttribution->id,
        'subject_type' => 'event',
        'subject_identifier' => $eventLink->subject_identifier,
        'subject_instance' => 'share_tracking_link',
        'cart_identifier' => (string) $event->id,
        'subject_title_snapshot' => $event->title,
        'conversion_type' => 'event_registration',
        'external_reference' => 'event_registration:top-subject:1',
        'value_minor' => 0,
        'subtotal_minor' => 0,
        'total_minor' => 0,
        'commission_minor' => 0,
        'commission_currency' => 'MYR',
        'status' => ApprovedConversion::class,
        'occurred_at' => now()->subMinutes(40),
        'metadata' => [
            'link_id' => $eventLink->id,
            'link_title_snapshot' => $event->title,
            'sharer_user_id' => $this->sharer->id,
            'subject_id' => 'legacy-event-conversion-id',
        ],
    ]);

    $speakerAttribution = AffiliateAttribution::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'subject_type' => 'speaker',
        'subject_identifier' => $speakerLink->subject_identifier,
        'subject_instance' => 'share_tracking_link',
        'subject_title_snapshot' => $speaker->formatted_name,
        'cookie_value' => 'speaker-top-subject-cookie',
        'landing_url' => $speakerLink->destination_url,
        'metadata' => [
            'tracking_mode' => 'landing',
            'link_id' => $speakerLink->id,
            'sharer_user_id' => $this->sharer->id,
        ],
        'first_seen_at' => now()->subMinutes(30),
        'last_seen_at' => now()->subMinutes(30),
    ]);

    AffiliateTouchpoint::query()->create([
        'affiliate_attribution_id' => $speakerAttribution->id,
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'subject_type' => 'speaker',
        'subject_identifier' => $speakerLink->subject_identifier,
        'subject_instance' => 'share_tracking_link',
        'subject_title_snapshot' => $speaker->formatted_name,
        'metadata' => [
            'event_type' => 'visit',
            'link_id' => $speakerLink->id,
            'visited_url' => $speakerLink->destination_url,
            'visitor_key' => 'speaker-top-subject-visitor',
            'visit_kind' => 'landing',
        ],
        'touched_at' => now()->subMinutes(25),
    ]);

    $eventLinkData = app(ShareTrackingAnalyticsService::class)->findLinkForUser($this->sharer, $eventLink->id);

    expect($eventLinkData)->not->toBeNull();

    $recentVisits = app(ShareTrackingAnalyticsService::class)->recentVisitsForLink($eventLinkData);
    $recentResponses = app(ShareTrackingAnalyticsService::class)->recentOutcomesForUser($this->sharer);

    expect($recentVisits->first())->not->toBeNull()
        ->and($recentVisits->first()?->subjectType)->toBe('event')
        ->and($recentVisits->first()?->subjectKey)->toBe($eventLink->subject_identifier);

    expect($recentResponses->first())->not->toBeNull()
        ->and($recentResponses->first()?->subjectType)->toBe('event')
        ->and($recentResponses->first()?->subjectId)->toBe((string) $event->id)
        ->and($recentResponses->first()?->subjectKey)->toBe($eventLink->subject_identifier);

    $component = Livewire::actingAs($this->sharer)
        ->test(DawahImpactIndex::class);

    /** @var DawahImpactIndex $instance */
    $instance = $component->instance();

    expect($instance->topSubjects->first())->toMatchArray([
        'subject_type' => 'event',
        'subject_key' => $eventLink->subject_identifier,
        'title_snapshot' => $event->title,
        'links' => 1,
        'visits' => 2,
        'event_registrations' => 1,
        'total_outcomes' => 1,
    ]);

    $this->actingAs($this->sharer)
        ->get(route('dashboard.dawah-impact'))
        ->assertOk()
        ->assertSee('Top Shared Subjects')
        ->assertSee($event->title);

    $this->actingAs($this->sharer)
        ->get(route('dashboard.dawah-impact.links.show', ['link' => $eventLink->id]))
        ->assertOk()
        ->assertSee('Subject')
        ->assertSee($eventLink->subject_identifier);
});

test('resolved active attribution prefers canonical affiliate subject fields', function () {
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'published_at' => now(),
        'starts_at' => now()->addDay(),
    ]);

    $cookie = dawahShareLandingCookie($this, $this->sharer, route('events.show', $event), $event->title);

    $attribution = AffiliateAttribution::query()
        ->where('metadata->tracking_mode', 'landing')
        ->latest('first_seen_at')
        ->firstOrFail();

    $attribution->forceFill([
        'subject_type' => 'event',
        'subject_identifier' => 'event:canonical-subject',
        'cart_identifier' => (string) $event->id,
        'subject_title_snapshot' => $event->title,
        'metadata' => array_merge($attribution->metadata ?? [], [
            'subject_type' => 'page',
            'subject_id' => 'legacy-subject-id',
            'subject_key' => 'page:legacy-subject',
            'title_snapshot' => 'Legacy Title Snapshot',
        ]),
    ])->save();

    $request = Request::create(route('events.show', $event));
    $request->cookies->set((string) config('dawah-share.cookie.name'), $cookie);

    $resolved = app(ShareTrackingService::class)->resolveActiveAttribution($request);

    expect($resolved)->not->toBeNull()
        ->and($resolved?->subjectType)->toBe('event')
        ->and($resolved?->subjectId)->toBe((string) $event->id)
        ->and($resolved?->subjectKey)->toBe('event:canonical-subject')
        ->and($resolved?->titleSnapshot)->toBe($event->title);
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

    $this->withCredentials()
        ->withCookie(config('dawah-share.cookie.name'), $cookie?->getValue())
        ->postJson(route('api.event-saves.store'), [
            'event_id' => $event->id,
        ])
        ->assertCreated();

    $attribution = AffiliateAttribution::query()->latest('created_at')->firstOrFail();
    $link = AffiliateLink::query()
        ->whereHas('affiliate', fn ($query) => $query
            ->where('metadata->majlis_user_id', $this->sharer->getKey()))
        ->firstOrFail();

    expect(AffiliateTouchpoint::query()->where('metadata->provider', 'whatsapp')->exists())->toBeTrue();

    $providerBreakdown = app(ShareTrackingAnalyticsService::class)
        ->providerBreakdownForUser($this->sharer);

    expect($providerBreakdown)->toHaveCount(1)
        ->and($providerBreakdown->first()['provider'])->toBe('whatsapp')
        ->and($providerBreakdown->first()['outbound_shares'])->toBe(1)
        ->and($providerBreakdown->first()['visits'])->toBe(1)
        ->and($providerBreakdown->first()['unique_visitors'])->toBe(1)
        ->and($providerBreakdown->first()['outcomes'])->toBe(1);

    $this->actingAs($this->sharer)
        ->get(route('dashboard.dawah-impact'))
        ->assertOk()
        ->assertSee('Channel Impact');

    $this->actingAs($this->sharer)
        ->get(route('dashboard.dawah-impact.links.show', ['link' => $link->id]))
        ->assertOk()
        ->assertSee('Share Channels');
});

test('provider visitor counts fall back to visit metadata when attribution provider is missing', function () {
    $payload = $this->actingAs($this->sharer)
        ->getJson(route('dawah-share.payload', [
            'url' => route('events.index', ['search' => 'telegram-provider-fallback']),
            'text' => 'Telegram provider fallback',
            'title' => 'Telegram provider fallback',
        ]))
        ->assertOk()
        ->json();

    $link = AffiliateLink::query()
        ->where('destination_url', route('events.index', ['search' => 'telegram-provider-fallback']))
        ->firstOrFail();

    $affiliate = Affiliate::query()->findOrFail($link->affiliate_id);

    $attribution = AffiliateAttribution::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'subject_identifier' => $link->id,
        'subject_instance' => 'share_tracking_link',
        'cookie_value' => 'provider-fallback-cookie',
        'landing_url' => $payload['url'],
        'metadata' => [
            'tracking_mode' => 'landing',
            'link_id' => $link->id,
            'subject_type' => 'search',
            'subject_key' => 'search:telegram-provider-fallback',
            'visitor_key' => 'visitor-fallback-key',
            'share_provider' => null,
        ],
        'first_seen_at' => now()->subMinute(),
        'last_seen_at' => now()->subMinute(),
    ]);

    AffiliateTouchpoint::query()->create([
        'affiliate_attribution_id' => $attribution->id,
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'metadata' => [
            'event_type' => 'visit',
            'link_id' => $link->id,
            'visited_url' => route('events.index', ['search' => 'telegram-provider-fallback']),
            'visitor_key' => 'visitor-fallback-key',
            'visit_kind' => 'landing',
            'subject_type' => 'search',
            'subject_key' => 'search:telegram-provider-fallback',
            'share_provider' => 'telegram',
        ],
        'touched_at' => now(),
    ]);

    $providerBreakdown = app(ShareTrackingAnalyticsService::class)
        ->providerBreakdownForUser($this->sharer);

    expect($providerBreakdown)->toHaveCount(1)
        ->and($providerBreakdown->first()['provider'])->toBe('telegram')
        ->and($providerBreakdown->first()['visits'])->toBe(1)
        ->and($providerBreakdown->first()['unique_visitors'])->toBe(1);
});

test('impact dashboard can sort by check-ins and filter by response type', function () {
    $checkinPayload = $this->actingAs($this->sharer)
        ->getJson(route('dawah-share.payload', [
            'url' => route('events.index', ['search' => 'checkin-heavy']),
            'text' => 'Check-in Heavy Link',
            'title' => 'Check-in Heavy Link',
        ]))
        ->assertOk()
        ->json();

    $submissionPayload = $this->actingAs($this->sharer)
        ->getJson(route('dawah-share.payload', [
            'url' => route('submit-event.create'),
            'text' => 'Submission Link',
            'title' => 'Submission Link',
        ]))
        ->assertOk()
        ->json();

    $checkinLink = AffiliateLink::query()->where('destination_url', route('events.index', ['search' => 'checkin-heavy']))->firstOrFail();
    $submissionLink = AffiliateLink::query()->where('destination_url', route('submit-event.create'))->firstOrFail();
    $affiliate = Affiliate::query()->findOrFail($checkinLink->affiliate_id);

    $sharedAttribution = AffiliateAttribution::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'subject_identifier' => $checkinLink->id,
        'subject_instance' => 'share_tracking_link',
        'cookie_value' => 'checkin-cookie',
        'landing_url' => $checkinLink->destination_url,
        'user_id' => User::factory()->create()->id,
        'metadata' => [
            'link_id' => $checkinLink->id,
            'subject_type' => 'search',
            'subject_key' => 'search:checkin-heavy',
        ],
        'first_seen_at' => now()->subDay(),
        'last_seen_at' => now()->subDay(),
    ]);

    AffiliateConversion::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'affiliate_attribution_id' => $sharedAttribution->id,
        'conversion_type' => 'event_checkin',
        'external_reference' => 'event_checkin:seed:1',
        'value_minor' => 0,
        'subtotal_minor' => 0,
        'total_minor' => 0,
        'commission_minor' => 0,
        'commission_currency' => 'MYR',
        'status' => ApprovedConversion::class,
        'occurred_at' => now()->subHours(6),
        'metadata' => [
            'link_id' => $checkinLink->id,
            'link_title_snapshot' => 'Check-in Heavy Link',
            'sharer_user_id' => $this->sharer->id,
            'actor_user_id' => User::factory()->create()->id,
            'subject_type' => 'search',
            'subject_key' => 'search:checkin-heavy',
        ],
    ]);

    AffiliateConversion::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'affiliate_attribution_id' => $sharedAttribution->id,
        'conversion_type' => 'event_checkin',
        'external_reference' => 'event_checkin:seed:2',
        'value_minor' => 0,
        'subtotal_minor' => 0,
        'total_minor' => 0,
        'commission_minor' => 0,
        'commission_currency' => 'MYR',
        'status' => ApprovedConversion::class,
        'occurred_at' => now()->subHours(5),
        'metadata' => [
            'link_id' => $checkinLink->id,
            'link_title_snapshot' => 'Check-in Heavy Link',
            'sharer_user_id' => $this->sharer->id,
            'actor_user_id' => User::factory()->create()->id,
            'subject_type' => 'search',
            'subject_key' => 'search:checkin-heavy',
        ],
    ]);

    $submissionAttribution = AffiliateAttribution::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'subject_identifier' => $submissionLink->id,
        'subject_instance' => 'share_tracking_link',
        'cookie_value' => 'submission-cookie',
        'landing_url' => $submissionLink->destination_url,
        'metadata' => [
            'link_id' => $submissionLink->id,
            'subject_type' => 'page',
            'subject_key' => 'page:submit-event',
        ],
        'first_seen_at' => now()->subHours(4),
        'last_seen_at' => now()->subHours(4),
    ]);

    AffiliateConversion::query()->create([
        'affiliate_id' => $affiliate->id,
        'affiliate_code' => $affiliate->code,
        'affiliate_attribution_id' => $submissionAttribution->id,
        'conversion_type' => 'event_submission',
        'external_reference' => 'event_submission:seed:1',
        'value_minor' => 0,
        'subtotal_minor' => 0,
        'total_minor' => 0,
        'commission_minor' => 0,
        'commission_currency' => 'MYR',
        'status' => ApprovedConversion::class,
        'occurred_at' => now()->subHours(3),
        'metadata' => [
            'link_id' => $submissionLink->id,
            'link_title_snapshot' => 'Submission Link',
            'sharer_user_id' => $this->sharer->id,
            'subject_type' => 'page',
            'subject_key' => 'page:submit-event',
        ],
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
        'id' => (string) Str::uuid(),
        'order_column' => 1,
    ]);

    $reference = Reference::factory()->create([
        'is_active' => true,
    ]);

    $this->get(route('events.index', ['search' => 'fiqh']))
        ->assertSuccessful()
        ->assertSee('payloadEndpoint', false)
        ->assertSee('shareResults()', false)
        ->assertSee('copyShareLink()', false);

    foreach ([
        route('events.show', $event),
        route('institutions.show', $institution),
        route('speakers.show', $speaker),
    ] as $url) {
        $this->get($url)
            ->assertSuccessful()
            ->assertSee('payloadEndpoint', false)
            ->assertSee('storage/social-media-icons/telegram.svg', false);
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

    $link = AffiliateLink::query()->firstOrFail();

    $this->actingAs($this->sharer)
        ->get(route('dashboard.dawah-impact'))
        ->assertOk()
        ->assertSee('My shared links');

    $this->actingAs($this->sharer)
        ->get(route('dashboard.dawah-impact.links.show', ['link' => $link->id]))
        ->assertOk()
        ->assertSee($event->title);

    $this->actingAs($otherUser)
        ->get(route('dashboard.dawah-impact.links.show', ['link' => $link->id]))
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

    $link = AffiliateLink::query()->firstOrFail();

    expect(AffiliateLink::count())->toBe(1);
    expect(AffiliateTouchpoint::query()->where('metadata->event_type', 'visit')->count())->toBe(0);
    expect(rawurldecode((string) $response->headers->get('Location')))->toContain('mi_channel=whatsapp');

    $this->assertDatabaseHas('affiliate_touchpoints', [
        'affiliate_id' => $link->affiliate_id,
        'metadata->link_id' => $link->id,
        'metadata->provider' => 'whatsapp',
        'metadata->event_type' => 'outbound_share',
    ]);
});
