<?php

use App\Livewire\Pages\Contributions\SubmitInstitution;
use App\Models\District;
use App\Models\Event;
use App\Models\Institution;
use App\Models\State;
use App\Models\Subdistrict;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

use function Pest\Laravel\get;

it('renders translated hero and search copy on institution index', function () {
    app()->setLocale('ms');

    get('/institusi')
        ->assertSuccessful()
        ->assertSee(__('Centers of'))
        ->assertSee(__('Knowledge & Community'))
        ->assertSee(__('Search institutions...'));
});

it('renders translated no-result copy on institution index', function () {
    app()->setLocale('ms');

    get('/institusi?search=zzzzzzzzz')
        ->assertSuccessful()
        ->assertSee(__('No institutions found'))
        ->assertSee(__('We couldn\'t find any institutions matching your search.'));
});

it('shows add-missing-institution call to action on institution index', function () {
    get('/institusi')
        ->assertSuccessful()
        ->assertSee('Tak jumpa institusi yang anda cari? Cadangkan institusi baharu.')
        ->assertSee('Cadangkan institusi baharu');
});

it('redirects guests to login when opening add institution form', function () {
    get(route('contributions.submit-institution'))
        ->assertRedirect(route('login'));
});

it('allows users to submit a missing institution from institution index with pending status', function () {
    $user = User::factory()->create();
    $institutionName = 'Institusi Cadangan Baru';

    Livewire::actingAs($user)
        ->test(SubmitInstitution::class)
        ->set('data.name', $institutionName)
        ->set('data.type', 'masjid')
        ->set('data.google_maps_url', 'https://maps.google.com/?q=3.1390,101.6869')
        ->call('submit')
        ->assertHasNoErrors();

    $institution = Institution::query()
        ->where('name', $institutionName)
        ->first();

    expect($institution)->not->toBeNull()
        ->and($institution?->status)->toBe('pending');
});

it('supports fuzzy search with minor institution name typos', function () {
    Institution::factory()->create([
        'name' => 'Masjid Al Hidayah',
        'status' => 'verified',
    ]);

    Institution::factory()->create([
        'name' => 'Pusat Pengajian An-Nur',
        'status' => 'verified',
    ]);

    get('/institusi?search=Hidayh')
        ->assertSuccessful()
        ->assertSee('Masjid Al Hidayah')
        ->assertDontSee('Pusat Pengajian An-Nur');
});

it('keeps multi-word search strict to phrase-relevant institutions', function () {
    Institution::factory()->create([
        'name' => 'Masjid Besi Putrajaya',
        'status' => 'verified',
    ]);

    Institution::factory()->create([
        'name' => 'Masjid Al Hidayah',
        'status' => 'verified',
    ]);

    Institution::factory()->create([
        'name' => 'Pusat Komuniti Besi',
        'status' => 'verified',
    ]);

    get('/institusi?search=masjid+besi')
        ->assertSuccessful()
        ->assertSee('Masjid Besi Putrajaya')
        ->assertDontSee('Masjid Al Hidayah')
        ->assertDontSee('Pusat Komuniti Besi');
});

it('updates institution results live when search changes', function () {
    Institution::factory()->create([
        'name' => 'Masjid Al Hidayah',
        'status' => 'verified',
    ]);

    Institution::factory()->create([
        'name' => 'Pusat Pengajian An-Nur',
        'status' => 'verified',
    ]);

    Livewire::test('pages.institutions.index')
        ->set('search', 'Hidayh')
        ->assertSee('Masjid Al Hidayah')
        ->assertDontSee('Pusat Pengajian An-Nur');
});

it('shows location hierarchy values without labels on institution cards', function () {
    $state = State::where('country_code', 'MY')->first();

    if (! $state) {
        $countryId = DB::table('countries')->insertGetId([
            'iso2' => 'MY',
            'name' => 'Malaysia',
            'status' => 1,
            'phone_code' => '60',
            'iso3' => 'MYS',
            'region' => 'Asia',
            'subregion' => 'South-Eastern Asia',
        ]);

        $stateId = DB::table('states')->insertGetId([
            'country_id' => $countryId,
            'name' => 'Selangor',
            'country_code' => 'MY',
        ]);

        $state = State::query()->findOrFail($stateId);
    }

    $district = District::query()->create([
        'country_id' => (int) $state->country_id,
        'state_id' => (int) $state->id,
        'country_code' => 'MY',
        'name' => 'Petaling',
    ]);

    $subdistrict = Subdistrict::query()->create([
        'country_id' => (int) $state->country_id,
        'state_id' => (int) $state->id,
        'district_id' => (int) $district->id,
        'country_code' => 'MY',
        'name' => 'Shah Alam',
    ]);

    $institution = Institution::factory()->create([
        'name' => 'Masjid Al Hidayah',
        'status' => 'verified',
    ]);

    $institution->address()->update([
        'state_id' => (int) $state->id,
        'district_id' => (int) $district->id,
        'subdistrict_id' => (int) $subdistrict->id,
    ]);

    get('/institusi?search=Hidayah')
        ->assertSuccessful()
        ->assertSee('Selangor, Petaling, Shah Alam')
        ->assertDontSee(__('Negeri').':')
        ->assertDontSee(__('Daerah').':')
        ->assertDontSee(__('Bandar / Mukim / Zon').':');
});

it('shows location scope controls on institution index', function () {
    get('/institusi')
        ->assertSuccessful()
        ->assertSee(__('Semua Negeri'))
        ->assertSee(__('Semua Daerah'))
        ->assertSee(__('Semua Bandar / Mukim / Zon'));
});

it('filters institutions by negeri, daerah, and subdistrict scopes', function () {
    $stateA = State::where('country_code', 'MY')->first();

    if (! $stateA) {
        $countryId = DB::table('countries')->insertGetId([
            'iso2' => 'MY',
            'name' => 'Malaysia',
            'status' => 1,
            'phone_code' => '60',
            'iso3' => 'MYS',
            'region' => 'Asia',
            'subregion' => 'South-Eastern Asia',
        ]);

        $stateAId = DB::table('states')->insertGetId([
            'country_id' => $countryId,
            'name' => 'Selangor',
            'country_code' => 'MY',
        ]);

        $stateA = State::query()->findOrFail($stateAId);
    }

    $stateBId = DB::table('states')->insertGetId([
        'country_id' => (int) $stateA->country_id,
        'name' => 'Negeri Ujian B',
        'country_code' => 'MY',
    ]);
    $stateB = State::query()->findOrFail($stateBId);

    $districtA = District::query()->create([
        'country_id' => (int) $stateA->country_id,
        'state_id' => (int) $stateA->id,
        'country_code' => 'MY',
        'name' => 'Daerah Ujian A',
    ]);

    $districtA2 = District::query()->create([
        'country_id' => (int) $stateA->country_id,
        'state_id' => (int) $stateA->id,
        'country_code' => 'MY',
        'name' => 'Daerah Ujian A2',
    ]);

    $districtB = District::query()->create([
        'country_id' => (int) $stateB->country_id,
        'state_id' => (int) $stateB->id,
        'country_code' => 'MY',
        'name' => 'Daerah Ujian B',
    ]);

    $subdistrictA = Subdistrict::query()->create([
        'country_id' => (int) $stateA->country_id,
        'state_id' => (int) $stateA->id,
        'district_id' => (int) $districtA->id,
        'country_code' => 'MY',
        'name' => 'Mukim Ujian A',
    ]);

    $subdistrictA2 = Subdistrict::query()->create([
        'country_id' => (int) $stateA->country_id,
        'state_id' => (int) $stateA->id,
        'district_id' => (int) $districtA2->id,
        'country_code' => 'MY',
        'name' => 'Mukim Ujian A2',
    ]);

    $subdistrictB = Subdistrict::query()->create([
        'country_id' => (int) $stateB->country_id,
        'state_id' => (int) $stateB->id,
        'district_id' => (int) $districtB->id,
        'country_code' => 'MY',
        'name' => 'Mukim Ujian B',
    ]);

    $institutionA = Institution::factory()->create([
        'name' => 'Institusi Scope A',
        'status' => 'verified',
    ]);
    $institutionA->address()->update([
        'state_id' => (int) $stateA->id,
        'district_id' => (int) $districtA->id,
        'subdistrict_id' => (int) $subdistrictA->id,
    ]);

    $institutionA2 = Institution::factory()->create([
        'name' => 'Institusi Scope A2',
        'status' => 'verified',
    ]);
    $institutionA2->address()->update([
        'state_id' => (int) $stateA->id,
        'district_id' => (int) $districtA2->id,
        'subdistrict_id' => (int) $subdistrictA2->id,
    ]);

    $institutionB = Institution::factory()->create([
        'name' => 'Institusi Scope B',
        'status' => 'verified',
    ]);
    $institutionB->address()->update([
        'state_id' => (int) $stateB->id,
        'district_id' => (int) $districtB->id,
        'subdistrict_id' => (int) $subdistrictB->id,
    ]);

    get('/institusi?state_id='.$stateA->id)
        ->assertSuccessful()
        ->assertSee('Institusi Scope A')
        ->assertSee('Institusi Scope A2')
        ->assertDontSee('Institusi Scope B');

    get('/institusi?state_id='.$stateA->id.'&district_id='.$districtA->id)
        ->assertSuccessful()
        ->assertSee('Institusi Scope A')
        ->assertDontSee('Institusi Scope A2')
        ->assertDontSee('Institusi Scope B');

    get('/institusi?state_id='.$stateA->id.'&district_id='.$districtA->id.'&subdistrict_id='.$subdistrictA->id)
        ->assertSuccessful()
        ->assertSee('Institusi Scope A')
        ->assertDontSee('Institusi Scope A2')
        ->assertDontSee('Institusi Scope B');
});

it('counts approved and pending public active events on institution cards', function () {
    $institution = Institution::factory()->create([
        'name' => 'Institusi Kiraan Acara',
        'slug' => 'institusi-kiraan-acara',
        'status' => 'verified',
    ]);

    Event::factory()->for($institution)->create([
        'title' => 'Approved Event',
        'status' => 'approved',
        'visibility' => 'public',
        'is_active' => true,
        'starts_at' => now()->addDays(1),
        'published_at' => now(),
    ]);

    Event::factory()->for($institution)->create([
        'title' => 'Pending Event',
        'status' => 'pending',
        'visibility' => 'public',
        'is_active' => true,
        'starts_at' => now()->addDays(1),
        'published_at' => null,
    ]);

    get('/institusi?search=Kiraan')
        ->assertSuccessful()
        ->assertSee('Institusi Kiraan Acara')
        ->assertSee('2 '.__('Events'))
        ->assertDontSee('1 '.__('Events'));
});
