<?php

use App\Livewire\Pages\Home\GlmHome;

it('renders the GLM homepage', function () {
    $response = $this->get('/glm');

    $response->assertStatus(200);
});

it('contains the GLM home livewire component', function () {
    $this->get('/glm')
        ->assertSeeLivewire(GlmHome::class);
});

it('displays the search form', function () {
    $response = $this->get('/glm');

    $response->assertSee('Cari topik, ustaz, atau lokasi');
});

it('displays quick filter links', function () {
    $response = $this->get('/glm');

    $response->assertSee('Malam Ini');
    $response->assertSee('Jumaat Ini');
    $response->assertSee('Minggu Ini');
});

it('displays the main heading', function () {
    $response = $this->get('/glm');

    $response->assertSee('Cari Majlis Ilmu');
});
