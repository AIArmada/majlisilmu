<?php

use App\Enums\EventFormat;

test('event format can be set to physical', function () {
    \Livewire\Livewire::test('pages.submit-event.create')
        ->set('data.event_format', EventFormat::Physical->value)
        ->assertSet('data.event_format', EventFormat::Physical->value);
});

test('event format can be set to online', function () {
    \Livewire\Livewire::test('pages.submit-event.create')
        ->set('data.event_format', EventFormat::Online->value)
        ->assertSet('data.event_format', EventFormat::Online->value);
});

test('event format can be set to hybrid', function () {
    \Livewire\Livewire::test('pages.submit-event.create')
        ->set('data.event_format', EventFormat::Hybrid->value)
        ->assertSet('data.event_format', EventFormat::Hybrid->value);
});
