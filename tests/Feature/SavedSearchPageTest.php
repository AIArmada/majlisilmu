<?php

use App\Enums\EventParticipantRole;
use App\Livewire\Pages\SavedSearches\Index as SavedSearchesIndex;
use App\Models\SavedSearch;
use App\Models\Speaker;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

it('prefills domain kategori filters from query string when saving searches', function () {
    $user = User::factory()->create();
    $domainTag = Tag::factory()->domain()->create([
        'name' => ['en' => 'Aqidah', 'ms' => 'Aqidah'],
    ]);

    $this->actingAs($user);

    Livewire::withQueryParams([
        'domain_tag_ids' => [$domainTag->id],
    ])->test(SavedSearchesIndex::class)
        ->assertSet('filters.domain_tag_ids.0', $domainTag->id);
});

it('renders domain kategori chip using human-readable tag name', function () {
    $user = User::factory()->create();
    $domainTag = Tag::factory()->domain()->create([
        'name' => ['en' => 'Aqidah', 'ms' => 'Aqidah'],
    ]);

    $this->actingAs($user)
        ->get(route('saved-searches.index', [
            'domain_tag_ids' => [$domainTag->id],
        ]))
        ->assertOk()
        ->assertSee('Kategori: Aqidah');
});

it('renders source issue and reference chips using human-readable values', function () {
    $user = User::factory()->create();
    $sourceTag = Tag::factory()->source()->create([
        'name' => ['en' => 'Quran', 'ms' => 'Quran'],
    ]);
    $issueTag = Tag::factory()->issue()->create([
        'name' => ['en' => 'Keluarga', 'ms' => 'Keluarga'],
    ]);
    $reference = \App\Models\Reference::factory()->create([
        'title' => 'Riyadhus Solihin',
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->get(route('saved-searches.index', [
            'source_tag_ids' => [$sourceTag->id],
            'issue_tag_ids' => [$issueTag->id],
            'reference_ids' => [$reference->id],
        ]))
        ->assertOk()
        ->assertSee('Sumber Rujukan Utama: Quran')
        ->assertSee('Tema / Isu: Keluarga')
        ->assertSee('Rujukan Kitab/Buku: Riyadhus Solihin');
});

it('renders participant role and linked profile chips using human-readable values', function () {
    $user = User::factory()->create();
    $imamSpeaker = Speaker::factory()->create(['name' => 'Ustaz Role Imam']);

    $this->actingAs($user)
        ->get(route('saved-searches.index', [
            'participant_roles' => [EventParticipantRole::PersonInCharge->value],
            'imam_ids' => [$imamSpeaker->id],
        ]))
        ->assertOk()
        ->assertSee('Participant Roles: PIC / Penyelaras')
        ->assertSee('Imam: Ustaz Role Imam');
});

it('does not show manual latitude/longitude inputs on the saved search form', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('saved-searches.index'))
        ->assertOk()
        ->assertDontSee('Latitude')
        ->assertDontSee('Longitude')
        ->assertDontSee('Radius (km)');
});

it('ignores standalone radius query prefill when no coordinates exist', function () {
    $user = User::factory()->create();

    $this->actingAs($user);

    Livewire::withQueryParams([
        'radius_km' => '50',
    ])->test(SavedSearchesIndex::class)
        ->assertSet('lat', null)
        ->assertSet('lng', null)
        ->assertSet('radius_km', null);
});

it('renders state filter chips using human-readable state names', function () {
    $user = User::factory()->create();

    $countryId = DB::table('countries')->insertGetId([
        'iso2' => 'MY',
        'name' => 'Malaysia',
        'status' => 1,
        'phone_code' => '60',
        'iso3' => 'MYS',
        'region' => 'Asia',
        'subregion' => 'South-Eastern Asia',
    ]);

    DB::table('states')->insert([
        'id' => 2489,
        'country_id' => $countryId,
        'name' => 'Selangor',
        'country_code' => 'MY',
    ]);

    SavedSearch::factory()->create([
        'user_id' => $user->id,
        'name' => 'Search Negeri',
        'filters' => [
            'state_id' => '2489',
            'time_scope' => 'upcoming',
        ],
    ]);

    $this->actingAs($user)
        ->get(route('saved-searches.index'))
        ->assertOk()
        ->assertSee('Selangor')
        ->assertDontSee('State Id: 2489');
});

it('shows radius in captured filters when location radius is present in query params', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('saved-searches.index', [
            'lat' => '3.0968296042531',
            'lng' => '101.48891599047',
            'radius_km' => '10',
            'sort' => 'distance',
            'time_scope' => 'upcoming',
        ]))
        ->assertOk()
        ->assertSee('Radius: 10 km');
});

it('hides the create saved search form when no filters are present', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('saved-searches.index'))
        ->assertOk()
        ->assertDontSee('Simpan carian ini');
});

it('shows the create saved search form when filters are present in the url', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('saved-searches.index', ['time_scope' => 'upcoming']))
        ->assertOk()
        ->assertSee('Simpan carian ini');
});

it('shows the create saved search form when location coordinates are present in the url', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('saved-searches.index', [
            'lat' => '3.14',
            'lng' => '101.68',
            'radius_km' => '20',
        ]))
        ->assertOk()
        ->assertSee('Simpan carian ini');
});

it('hides the create saved search form when only a keyword is present without filters', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('saved-searches.index', ['search' => 'tafsir']))
        ->assertOk()
        ->assertDontSee('Simpan carian ini');
});

it('allows authenticated users to edit a saved search name and notification', function () {
    $user = User::factory()->create();

    $savedSearch = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'name' => 'Original Name',
        'notify' => 'off',
    ]);

    $this->actingAs($user);

    Livewire::test(SavedSearchesIndex::class)
        ->call('startEdit', $savedSearch->id)
        ->assertSet('editingId', $savedSearch->id)
        ->assertSet('editName', 'Original Name')
        ->assertSet('editNotify', 'off')
        ->set('editName', 'Updated Name')
        ->set('editNotify', 'weekly')
        ->call('update', $savedSearch->id)
        ->assertHasNoErrors()
        ->assertSet('editingId', null);

    $savedSearch->refresh();
    expect($savedSearch->name)->toBe('Updated Name');
    expect($savedSearch->notify)->toBe('weekly');
});

it('validates edit name field is required', function () {
    $user = User::factory()->create();

    $savedSearch = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'name' => 'My Search',
        'notify' => 'daily',
    ]);

    $this->actingAs($user);

    Livewire::test(SavedSearchesIndex::class)
        ->call('startEdit', $savedSearch->id)
        ->set('editName', '')
        ->call('update', $savedSearch->id)
        ->assertHasErrors(['editName' => 'required']);
});

it('cancels editing and clears edit state', function () {
    $user = User::factory()->create();

    $savedSearch = SavedSearch::factory()->create([
        'user_id' => $user->id,
        'name' => 'My Search',
        'notify' => 'daily',
    ]);

    $this->actingAs($user);

    Livewire::test(SavedSearchesIndex::class)
        ->call('startEdit', $savedSearch->id)
        ->assertSet('editingId', $savedSearch->id)
        ->call('cancelEdit')
        ->assertSet('editingId', null)
        ->assertSet('editName', '')
        ->assertSet('editNotify', 'daily');
});

it('renders saved searches page copy and notify labels naturally in malay', function () {
    $user = User::factory()->create();

    SavedSearch::factory()->create([
        'user_id' => $user->id,
        'name' => 'Kuliah Maghrib Shah Alam',
        'query' => 'maghrib',
        'notify' => 'daily',
        'filters' => [
            'event_format' => ['online'],
            'language_codes' => ['ms', 'en'],
            'starts_after' => '2026-03-12',
            'starts_time_from' => '20:15',
        ],
    ]);

    $this->actingAs($user)
        ->withSession(['locale' => 'ms'])
        ->get(route('saved-searches.index'))
        ->assertOk()
        ->assertSee('Carian yang anda simpan')
        ->assertSee('Simpan penapis yang anda selalu guna supaya anda boleh buka semula carian yang sama tanpa tetapkan semuanya dari awal.')
        ->assertSee('Dikemas kini')
        ->assertSee('Harian')
        ->assertSee('Format Majlis: Dalam talian')
        ->assertSee('Bahasa: Bahasa Melayu, Bahasa Inggeris')
        ->assertSee('Tarikh Dari: 12 Mac 2026')
        ->assertSee('Masa Dari: 8:15 PM')
        ->assertDontSee('Daily')
        ->assertDontSee('Languages');
});
