<?php

use function Pest\Laravel\get;

it('renders the homepage with content', function () {
    $response = get(route('home'));

    $response->assertStatus(200);
    $response->assertSee('Cari');
    $response->assertSee('Majlis');
    $response->assertSee('Ilmu');
    $response->assertSee('Temui kuliah');
});

it('renders the events index with content', function () {
    $response = get(route('events.index'));

    $response->assertStatus(200);
    $response->assertSee('Find Your Next');
});
