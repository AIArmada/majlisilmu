<?php

use App\Models\Event;
use App\Models\User;
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
            ->assertSee(__('Akan Hadir?')); // Header text that appears for both guests and logged-in users
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
            ->assertDontSee(__('Saya Akan Hadir'));
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
            ->assertSee('5')
            ->assertSee(__('akan hadir'));
    });
});
