<?php

use Livewire\Livewire;

test('zohor is now spelled as zuhur', function () {
    Livewire::test('pages.submit-event.create')
        ->set('data.event_date', '2026-03-01')
        ->call('updateDateAndPrayerTimes', '2026-03-01')
        ->assertSee('Selepas Zuhur')
        ->assertDontSee('Selepas Zohor');
});

test('selepas jumaat appears on friday', function () {
    // Feb 6, 2026 is a Friday
    Livewire::test('pages.submit-event.create')
        ->set('data.event_date', '2026-02-06')
        ->call('updateDateAndPrayerTimes', '2026-02-06')
        ->assertSee('Selepas Jumaat');
});

test('selepas jumaat does not appear on non-friday', function () {
    // Feb 5, 2026 is a Thursday
    Livewire::test('pages.submit-event.create')
        ->set('data.event_date', '2026-02-05')
        ->call('updateDateAndPrayerTimes', '2026-02-05')
        ->assertDontSee('Selepas Jumaat');
});

test('selepas tarawikh appears during ramadhan', function () {
    // Feb 25, 2026 is during Ramadhan (Feb 18 - Mar 19, 2026)
    Livewire::test('pages.submit-event.create')
        ->set('data.event_date', '2026-02-25')
        ->call('updateDateAndPrayerTimes', '2026-02-25')
        ->assertSee('Selepas Tarawikh');
});

test('selepas tarawikh does not appear outside ramadhan', function () {
    // Apr 1, 2026 is after Ramadhan
    Livewire::test('pages.submit-event.create')
        ->set('data.event_date', '2026-04-01')
        ->call('updateDateAndPrayerTimes', '2026-04-01')
        ->assertDontSee('Selepas Tarawikh');
});

test('both selepas jumaat and tarawikh appear on friday during ramadhan', function () {
    // Feb 27, 2026 is a Friday during Ramadhan
    Livewire::test('pages.submit-event.create')
        ->set('data.event_date', '2026-02-27')
        ->call('updateDateAndPrayerTimes', '2026-02-27')
        ->assertSee('Selepas Jumaat')
        ->assertSee('Selepas Tarawikh');
});
