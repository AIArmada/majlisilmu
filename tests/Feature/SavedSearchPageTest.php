<?php

use App\Livewire\Pages\SavedSearches\Index as SavedSearchesIndex;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('requires authentication for the saved searches page', function () {
    $response = $this->get(route('saved-searches.index'));

    $response->assertRedirect(route('login'));
});

it('shows only authenticated user saved searches on the page', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    SavedSearch::factory()->create([
        'user_id' => $user->id,
        'name' => 'My Search',
    ]);

    SavedSearch::factory()->create([
        'user_id' => $otherUser->id,
        'name' => 'Other Search',
    ]);

    $this->actingAs($user)
        ->get(route('saved-searches.index'))
        ->assertOk()
        ->assertSee('My Search')
        ->assertDontSee('Other Search');
});

it('allows authenticated users to create and delete saved searches', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::test(SavedSearchesIndex::class)
        ->set('name', 'Kuliah Maghrib KL')
        ->set('query', 'maghrib')
        ->set('notify', 'daily')
        ->set('filters', ['language' => 'english'])
        ->call('save')
        ->assertHasNoErrors();

    $savedSearch = SavedSearch::query()
        ->where('user_id', $user->id)
        ->where('name', 'Kuliah Maghrib KL')
        ->first();

    expect($savedSearch)->not->toBeNull();

    Livewire::test(SavedSearchesIndex::class)
        ->call('delete', $savedSearch->id)
        ->assertHasNoErrors();

    expect(SavedSearch::where('id', $savedSearch->id)->exists())->toBeFalse();
});

it('prefills subdistrict filter from query string when saving searches', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::withQueryParams([
        'search' => 'fiqh',
        'state_id' => '10',
        'district_id' => '20',
        'subdistrict_id' => '30',
    ])->test(SavedSearchesIndex::class)
        ->assertSet('query', 'fiqh')
        ->assertSet('filters.state_id', '10')
        ->assertSet('filters.district_id', '20')
        ->assertSet('filters.subdistrict_id', '30');
});
