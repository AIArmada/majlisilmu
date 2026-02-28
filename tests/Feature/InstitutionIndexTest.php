<?php

use App\Models\Institution;
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

it('shows location hierarchy labels on institution cards', function () {
    Institution::factory()->create([
        'name' => 'Masjid Al Hidayah',
        'status' => 'verified',
    ]);

    get('/institusi')
        ->assertSuccessful()
        ->assertSee(__('Negeri').':')
        ->assertSee(__('Daerah').':')
        ->assertSee(__('Daerah Kecil').':');
});
