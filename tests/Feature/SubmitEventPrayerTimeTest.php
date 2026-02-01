<?php

use App\Enums\EventAgeGroup;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Models\EventType;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\Topic;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow(Carbon::parse('2026-02-01 07:00:00', 'Asia/Kuala_Lumpur'));
});

afterEach(function () {
    Carbon::setTestNow();
});

it('filters prayer time options based on current time for today', function () {
    $component = Livewire::test('pages.submit-event.create');
    $options = $component->instance()->getPrayerTimeOptions('2026-02-01');

    expect($options)->not->toHaveKey(EventPrayerTime::SelepasSubuh->value)
        ->and($options)->toHaveKey(EventPrayerTime::LainWaktu->value);
});

it('shows all prayer time options when no date is selected', function () {
    $component = Livewire::test('pages.submit-event.create');
    $options = $component->instance()->getPrayerTimeOptions(null);

    expect($options)->toHaveKey(EventPrayerTime::SelepasSubuh->value)
        ->and($options)->toHaveKey(EventPrayerTime::SelepasZuhur->value)
        ->and($options)->toHaveKey(EventPrayerTime::SelepasAsar->value)
        ->and($options)->toHaveKey(EventPrayerTime::SelepasMaghrib->value)
        ->and($options)->toHaveKey(EventPrayerTime::SelepasIsyak->value)
        ->and($options)->toHaveKey(EventPrayerTime::LainWaktu->value);
});
