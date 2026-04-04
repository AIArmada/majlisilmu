<?php

use App\Livewire\Pages\Contributions\SubmitSpeaker;
use App\Models\Event;
use App\Models\Speaker;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\get;

function ensureSpeakerIndexMalaysiaCountryExists(): int
{
    $malaysiaId = DB::table('countries')->where('id', 132)->value('id');

    if (is_int($malaysiaId)) {
        return $malaysiaId;
    }

    return DB::table('countries')->insertGetId([
        'id' => 132,
        'iso2' => 'MY',
        'name' => 'Malaysia',
        'status' => 1,
        'phone_code' => '60',
        'iso3' => 'MYS',
        'region' => 'Asia',
        'subregion' => 'South-Eastern Asia',
    ]);
}

it('can search speakers case-insensitively', function () {
    // Create speakers with different cases
    Speaker::factory()->create([
        'name' => 'Samad Al-Bakri',
        'status' => 'verified',
        'is_active' => true,
    ]);

    Speaker::factory()->create([
        'name' => 'Ahmad Bin Ali',
        'status' => 'verified',
        'is_active' => true,
    ]);

    // Test with exact name
    get('/penceramah?search=Samad')
        ->assertSuccessful()
        ->assertSee('Samad')
        ->assertDontSee('Ahmad');

    // Test with lowercase name (should find Samad because of ILIKE fix)
    get('/penceramah?search=samad')
        ->assertSuccessful()
        ->assertSee('Samad')
        ->assertDontSee('Ahmad');
});

it('filters by active status on public speaker index', function () {
    Speaker::factory()->create([
        'name' => 'Active Speaker',
        'status' => 'verified',
        'is_active' => true,
    ]);

    Speaker::factory()->create([
        'name' => 'Inactive Speaker',
        'status' => 'verified',
        'is_active' => false,
    ]);

    get('/penceramah')
        ->assertSuccessful()
        ->assertSee('Active Speaker')
        ->assertDontSee('Inactive Speaker');
});

it('renders translated search placeholder on speaker index', function () {
    app()->setLocale('ms');

    get('/penceramah')
        ->assertSuccessful()
        ->assertSee(__('Search speakers...'));
});

it('shows add-missing-speaker call to action on speaker index', function () {
    get('/penceramah')
        ->assertSuccessful()
        ->assertSee('Tak jumpa penceramah yang anda cari? Cadangkan profil baharu.')
        ->assertSee('Cadangkan penceramah baharu');
});

it('supports fuzzy search with minor typos', function () {
    Speaker::factory()->create([
        'name' => 'Samad Al-Bakri',
        'status' => 'verified',
        'is_active' => true,
    ]);

    Speaker::factory()->create([
        'name' => 'Sulaiman Hasan',
        'status' => 'verified',
        'is_active' => true,
    ]);

    get('/penceramah?search=Smad')
        ->assertSuccessful()
        ->assertSee('Samad')
        ->assertDontSee('Sulaiman');
});

it('updates search results live when query changes', function () {
    Speaker::factory()->create([
        'name' => 'Samad Al-Bakri',
        'status' => 'verified',
        'is_active' => true,
    ]);

    Speaker::factory()->create([
        'name' => 'Ahmad Bin Ali',
        'status' => 'verified',
        'is_active' => true,
    ]);

    Livewire::test('pages.speakers.index')
        ->set('search', 'Smad')
        ->assertSee('Samad')
        ->assertDontSee('Ahmad');
});

it('allows users to submit a missing speaker from speaker index with pending status', function () {
    $speakerName = 'Ustaz Cadangan Baru';
    $user = User::factory()->create();
    $countryId = ensureSpeakerIndexMalaysiaCountryExists();

    Livewire::actingAs($user)
        ->test(SubmitSpeaker::class)
        ->set('data.name', $speakerName)
        ->set('data.gender', 'male')
        ->set('data.country_id', (string) $countryId)
        ->call('submit')
        ->assertHasNoErrors();

    $speaker = Speaker::query()
        ->where('name', $speakerName)
        ->first();

    expect($speaker)->not->toBeNull()
        ->and($speaker?->status)->toBe('pending')
        ->and($speaker?->is_active)->toBeTrue();
});

it('redirects guests to login when opening add speaker form', function () {
    get(route('contributions.submit-speaker'))
        ->assertRedirect(route('login'));
});

it('counts only upcoming public events on the speaker index cards', function () {
    $speaker = Speaker::factory()->create([
        'name' => 'Speaker Dengan Majlis Akan Datang',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $upcomingEvent = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(3),
    ]);

    $pastEvent = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->subDays(3),
    ]);

    $speaker->speakerEvents()->attach([$upcomingEvent->id, $pastEvent->id]);

    $component = Livewire::test('pages.speakers.index')
        ->assertSee('Speaker Dengan Majlis Akan Datang');

    $listedSpeaker = collect($component->instance()->speakers->items())
        ->firstWhere('id', $speaker->id);

    expect($listedSpeaker)->not->toBeNull()
        ->and((int) $listedSpeaker?->events_count)->toBe(1);
});

it('renders profile-quality avatar URLs on the speaker index cards', function () {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');

    $speaker = Speaker::factory()->create([
        'name' => 'Kazim Elias',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $speaker->addMedia(UploadedFile::fake()->image('kazim.jpg', 1200, 1200))
        ->toMediaCollection('avatar');

    get('/penceramah?search=kazim')
        ->assertSuccessful()
        ->assertSee($speaker->public_avatar_url, false);
});
