<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\DawahShareAttribution;
use App\Models\DawahShareLink;
use App\Models\DawahShareOutcome;
use App\Models\DawahShareVisit;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\Request;

beforeEach(function (): void {
    $this->sharer = User::factory()->create();
});

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

test('share redirect route creates a link without recording a visit', function () {
    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
    ]);

    $this->actingAs($this->sharer)
        ->get(route('dawah-share.redirect', [
            'provider' => 'whatsapp',
            'url' => route('events.show', $event),
            'text' => 'Share this event',
            'title' => $event->title,
        ]))
        ->assertRedirect();

    expect(DawahShareLink::count())->toBe(1);
    expect(DawahShareVisit::count())->toBe(0);
});
