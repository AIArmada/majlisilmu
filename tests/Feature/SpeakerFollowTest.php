<?php

use App\Models\Speaker;
use App\Models\User;
use Livewire\Livewire;

it('allows an authenticated user to follow a speaker', function () {
    $user = User::factory()->create();
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $this->actingAs($user);

    $this->get(route('speakers.show', $speaker))
        ->assertSuccessful()
        ->assertSee(__('Ikuti'));

    expect($user->isFollowing($speaker))->toBeFalse();

    Livewire::actingAs($user)
        ->test('pages.speakers.show', ['speaker' => $speaker])
        ->assertSet('isFollowing', false)
        ->call('toggleFollow')
        ->assertSet('isFollowing', true);

    expect($user->isFollowing($speaker))->toBeTrue();
});

it('allows an authenticated user to unfollow a speaker', function () {
    $user = User::factory()->create();
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $user->follow($speaker);
    expect($user->isFollowing($speaker))->toBeTrue();

    Livewire::actingAs($user)
        ->test('pages.speakers.show', ['speaker' => $speaker])
        ->assertSet('isFollowing', true)
        ->call('toggleFollow')
        ->assertSet('isFollowing', false);

    expect($user->isFollowing($speaker))->toBeFalse();
});

it('redirects guest to login when trying to follow', function () {
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    Livewire::test('pages.speakers.show', ['speaker' => $speaker])
        ->call('toggleFollow')
        ->assertRedirect(route('login'));
});

it('returns correct followingSpeakers relationship', function () {
    $user = User::factory()->create();
    $speaker1 = Speaker::factory()->create(['status' => 'verified']);
    $speaker2 = Speaker::factory()->create(['status' => 'verified']);

    $user->follow($speaker1);
    $user->follow($speaker2);

    expect($user->followingSpeakers)->toHaveCount(2);
    expect($user->followingSpeakers->pluck('id')->toArray())->toContain($speaker1->id, $speaker2->id);
});

it('returns correct followers relationship on speaker', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();
    $speaker = Speaker::factory()->create(['status' => 'verified']);

    $user1->follow($speaker);
    $user2->follow($speaker);

    expect($speaker->followers)->toHaveCount(2);
    expect($speaker->isFollowedBy($user1))->toBeTrue();
    expect($speaker->isFollowedBy($user2))->toBeTrue();
    expect($speaker->isFollowedBy(null))->toBeFalse();
});

it('cleans up followings when user is deleted', function () {
    $user = User::factory()->create();
    $speaker = Speaker::factory()->create(['status' => 'verified']);

    $user->follow($speaker);
    expect($user->isFollowing($speaker))->toBeTrue();

    $user->delete();

    expect(
        \Illuminate\Support\Facades\DB::table('followings')
            ->where('user_id', $user->id)
            ->exists()
    )->toBeFalse();
});
