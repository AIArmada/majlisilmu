<?php

use App\Enums\EventFormat;
use Livewire\Livewire;

test('location section is visible when event format is physical', function () {
    Livewire::test('pages.submit-event.create')
        ->set('data.event_format', EventFormat::Physical)
        ->assertSee(__('Location'));
});

test('location section is hidden when event format is online', function () {
    Livewire::test('pages.submit-event.create')
        ->set('data.event_format', EventFormat::Online)
        ->assertDontSee(__('Location'));
});

test('location section is visible when event format is hybrid', function () {
    Livewire::test('pages.submit-event.create')
        ->set('data.event_format', EventFormat::Hybrid)
        ->assertSee(__('Location'));
});
