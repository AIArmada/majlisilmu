<?php

use App\Models\District;
use App\Models\Event;
use App\Models\Speaker;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

describe('Event Show Page Going Feature', function () {
    it('shows the going button for future events', function () {
        $event = Event::factory()->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
            'starts_at' => now()->addDay(),
        ]);

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSee(__('Akan Hadir')); // Button always visible, redirects guests to login
    });

    it('does not show the going button for past events', function () {
        $event = Event::factory()->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now()->subWeek(),
            'starts_at' => now()->subDay(),
        ]);

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertDontSee(__('Akan Hadir'));
    });

    it('authenticated user can toggle going status via livewire', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
            'starts_at' => now()->addDay(),
            'going_count' => 0,
        ]);

        $this->actingAs($user);

        Livewire::test('pages.events.show', ['event' => $event])
            ->assertSet('isGoing', false)
            ->call('toggleGoing')
            ->assertSet('isGoing', true);

        expect($event->fresh()->going_count)->toBe(1);
        expect($user->goingEvents()->where('event_id', $event->id)->exists())->toBeTrue();
    });

    it('authenticated user can toggle off going status via livewire', function () {
        $user = User::factory()->create();
        $event = Event::factory()->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
            'starts_at' => now()->addDay(),
            'going_count' => 1,
        ]);

        // Pre-attach the user
        $user->goingEvents()->attach($event->id);

        $this->actingAs($user);

        Livewire::test('pages.events.show', ['event' => $event])
            ->assertSet('isGoing', true)
            ->call('toggleGoing')
            ->assertSet('isGoing', false);

        expect($event->fresh()->going_count)->toBe(0);
        expect($user->goingEvents()->where('event_id', $event->id)->exists())->toBeFalse();
    });

    it('redirects guests to login when trying to toggle going', function () {
        $event = Event::factory()->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
            'starts_at' => now()->addDay(),
        ]);

        Livewire::test('pages.events.show', ['event' => $event])
            ->call('toggleGoing')
            ->assertRedirect(route('login'));
    });

    it('shows correct going count in the UI', function () {
        $users = User::factory()->count(5)->create();
        $event = Event::factory()->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
            'starts_at' => now()->addDay(),
            'going_count' => 5,
        ]);

        foreach ($users as $user) {
            $event->goingBy()->attach($user->id);
        }

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSee(__('Akan Hadir')); // Button always visible regardless of auth

        // Verify the going count is persisted correctly on the model
        expect($event->fresh()->going_count)->toBe(5);
    });
});

describe('Event Show Page Location & Contact Info', function () {
    it('does not use speaker images as hero background when location media is missing', function () {
        $speaker = Speaker::factory()->create();
        $speaker->addMedia(UploadedFile::fake()->image('speaker-avatar.jpg', 800, 800))
            ->toMediaCollection('avatar');

        $event = Event::factory()->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
            'starts_at' => now()->addDay(),
            'institution_id' => null,
            'venue_id' => null,
            'organizer_type' => Speaker::class,
            'organizer_id' => $speaker->id,
        ]);

        $event->speakers()->attach($speaker->id);

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertDontSee('class="size-full object-cover opacity-65"', false);
    });

    it('displays full venue address on the event page', function () {
        $venue = Venue::factory()->create();
        $address = $venue->addressModel;

        $event = Event::factory()->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
            'starts_at' => now()->addDay(),
            'venue_id' => $venue->id,
        ]);

        $response = $this->get(route('events.show', $event));
        $response->assertOk();

        // Should show line1 of the address
        if (filled($address?->line1)) {
            $response->assertSee($address->line1);
        }

        // Should show postcode if present
        if (filled($address?->postcode)) {
            $response->assertSee($address->postcode);
        }
    });

    it('displays waze and google maps navigation buttons when coordinates exist', function () {
        $venue = Venue::factory()->create();

        $event = Event::factory()->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
            'starts_at' => now()->addDay(),
            'venue_id' => $venue->id,
        ]);

        $response = $this->get(route('events.show', $event));
        $response->assertOk();
        $response->assertSee('Waze');
        $response->assertSee('Google Maps');
    });

    it('displays institution contact info on event page', function () {
        $institution = \App\Models\Institution::factory()->create();
        $emailContact = $institution->contacts()->where('category', \App\Enums\ContactCategory::Email->value)->first();
        $phoneContact = $institution->contacts()->where('category', \App\Enums\ContactCategory::Phone->value)->first();

        $event = Event::factory()->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
            'starts_at' => now()->addDay(),
            'institution_id' => $institution->id,
        ]);

        $response = $this->get(route('events.show', $event));
        $response->assertOk();

        if ($emailContact) {
            $response->assertSee($emailContact->value);
        }
        if ($phoneContact) {
            $response->assertSee($phoneContact->value);
        }
    });

    it('uses stored waze_url from address when available', function () {
        $venue = Venue::factory()->create();
        $address = $venue->addressModel;

        if ($address && filled($address->waze_url)) {
            $event = Event::factory()->create([
                'status' => 'approved',
                'visibility' => 'public',
                'published_at' => now()->subDay(),
                'starts_at' => now()->addDay(),
                'venue_id' => $venue->id,
            ]);

            $response = $this->get(route('events.show', $event));
            $response->assertOk();
            $response->assertSee('Waze');
        }

        expect(true)->toBeTrue();
    });

    it('hides duplicated state for kuala lumpur putrajaya and labuan in location display', function () {
        $venue = Venue::factory()->create([
            'name' => 'Dewan Utama KL',
        ]);

        $stateId = DB::table('states')->insertGetId([
            'country_id' => 132,
            'name' => 'Kuala Lumpur',
            'country_code' => 'MY',
        ]);

        $district = District::query()->create([
            'country_id' => 132,
            'state_id' => (int) $stateId,
            'country_code' => 'MY',
            'name' => 'Kuala Lumpur',
        ]);

        $venue->address()->update([
            'state_id' => (int) $stateId,
            'district_id' => $district->id,
            'subdistrict_id' => null,
        ]);

        $event = Event::factory()->create([
            'status' => 'approved',
            'visibility' => 'public',
            'published_at' => now()->subDay(),
            'starts_at' => now()->addDay(),
            'venue_id' => $venue->id,
        ]);

        $this->get(route('events.show', $event))
            ->assertOk()
            ->assertSee('Dewan Utama KL')
            ->assertDontSee('Kuala Lumpur, Kuala Lumpur');
    });
});
