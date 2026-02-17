<?php

use App\Enums\TimingMode;
use App\Models\Event;
use App\Models\Speaker;
use Illuminate\Support\Carbon;

it('shows prayer-relative timing text on speaker page instead of absolute time', function () {
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
    ]);

    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDay()->setTime(17, 45),
        'ends_at' => now()->addDay()->setTime(19, 15),
        'timing_mode' => TimingMode::PrayerRelative,
        'prayer_display_text' => 'Selepas Asar',
    ]);

    $speaker->events()->attach($event->id);

    $expectedEndTime = $event->ends_at?->copy()->timezone($event->timezone ?: 'Asia/Kuala_Lumpur')->format('h:i A');

    $this->get(route('speakers.show', $speaker))
        ->assertSuccessful()
        ->assertSee('Selepas Asar')
        ->assertSee($expectedEndTime)
        ->assertDontSee($event->starts_at?->format('h:i A'));
});

it('renders event end time in event timezone on speaker page', function () {
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
    ]);

    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'timezone' => 'Asia/Kuala_Lumpur',
        'starts_at' => Carbon::parse('2026-02-18 09:00:00', 'UTC'),
        'ends_at' => Carbon::parse('2026-02-18 12:40:00', 'UTC'),
        'timing_mode' => TimingMode::PrayerRelative,
        'prayer_display_text' => 'Selepas Asar',
    ]);

    $speaker->events()->attach($event->id);

    $this->get(route('speakers.show', $speaker))
        ->assertSuccessful()
        ->assertSee('Selepas Asar')
        ->assertSee('8:40 PM')
        ->assertDontSee('12:40 PM');
});
