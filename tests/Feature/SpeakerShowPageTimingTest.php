<?php

use App\Enums\EventFormat;
use App\Enums\EventParticipantRole;
use App\Enums\TimingMode;
use App\Models\District;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\Venue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

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

    $speaker->speakerEvents()->attach($event->id);

    $expectedEndTime = $event->ends_at?->copy()->timezone('Asia/Kuala_Lumpur')->format('h:i A');

    $this->withCookie('user_timezone', 'Asia/Kuala_Lumpur')
        ->get(route('speakers.show', $speaker))
        ->assertSuccessful()
        ->assertSee('Selepas Asar')
        ->assertSee($expectedEndTime)
        ->assertDontSee($event->starts_at?->format('h:i A'));
});

it('shows cancelled public events with cancelled badge on speaker page', function () {
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
    ]);

    $event = Event::factory()->create([
        'status' => 'cancelled',
        'visibility' => 'public',
        'starts_at' => now()->addDay()->setTime(17, 45),
        'ends_at' => now()->addDay()->setTime(19, 15),
    ]);

    $speaker->speakerEvents()->attach($event->id);

    $this->get(route('speakers.show', $speaker))
        ->assertSuccessful()
        ->assertSee($event->title)
        ->assertSee('Dibatalkan');
});

it('uses stronger calendar event colors on speaker page', function () {
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
    ]);

    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(3),
        'title' => 'Kuliah Kalender Penceramah',
    ]);

    $speaker->speakerEvents()->attach($event->id);

    $this->get(route('speakers.show', $speaker))
        ->assertSuccessful()
        ->assertSee('border-emerald-300 bg-emerald-100 text-emerald-900 shadow-emerald-200/80 hover:bg-emerald-200', false)
        ->assertDontSee('bg-emerald-50 text-emerald-700 hover:bg-emerald-100', false);
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

    $speaker->speakerEvents()->attach($event->id);

    $this->withCookie('user_timezone', 'Asia/Kuala_Lumpur')
        ->get(route('speakers.show', $speaker))
        ->assertSuccessful()
        ->assertSee('Selepas Asar')
        ->assertSee('8:40 PM')
        ->assertDontSee('12:40 PM');
});

it('shows dedicated venue name for event location on speaker page when available', function () {
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
    ]);

    $institution = Institution::factory()->create([
        'name' => 'Masjid Al-Hidayah Test',
    ]);

    $venue = Venue::factory()->create([
        'name' => 'Dewan Utama Test',
    ]);

    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'event_format' => EventFormat::Physical,
        'institution_id' => $institution->id,
        'venue_id' => $venue->id,
        'starts_at' => now()->addDay()->setTime(17, 45),
        'ends_at' => now()->addDay()->setTime(19, 15),
        'timing_mode' => TimingMode::PrayerRelative,
        'prayer_display_text' => 'Selepas Asar',
    ]);

    $speaker->speakerEvents()->attach($event->id);

    $this->withCookie('user_timezone', 'Asia/Kuala_Lumpur')
        ->get(route('speakers.show', $speaker))
        ->assertSuccessful()
        ->assertSee('Dewan Utama Test');
});

it('falls back to institution name for event location on speaker page when venue is missing', function () {
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
    ]);

    $institution = Institution::factory()->create([
        'name' => 'Masjid Al-Hidayah Test',
    ]);

    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'event_format' => EventFormat::Physical,
        'institution_id' => $institution->id,
        'venue_id' => null,
        'starts_at' => now()->addDay()->setTime(17, 45),
        'ends_at' => now()->addDay()->setTime(19, 15),
        'timing_mode' => TimingMode::PrayerRelative,
        'prayer_display_text' => 'Selepas Asar',
    ]);

    $speaker->speakerEvents()->attach($event->id);

    $this->withCookie('user_timezone', 'Asia/Kuala_Lumpur')
        ->get(route('speakers.show', $speaker))
        ->assertSuccessful()
        ->assertSee('Masjid Al-Hidayah Test');
});

it('hides state when district is kuala lumpur putrajaya or labuan', function () {
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
    ]);

    $institution = Institution::factory()->create([
        'name' => 'Masjid Al-Hidayah Test',
    ]);

    $venue = Venue::factory()->create([
        'name' => 'Dewan Utama Test',
    ]);

    $stateId = DB::table('states')->insertGetId([
        'country_id' => 132,
        'name' => 'Kuala Lumpur',
        'country_code' => 'MY',
    ]);

    $district = District::query()->create([
        'country_id' => 132,
        'state_id' => (int) $stateId,
        'country_code' => 'MY',
        'name' => 'Kuala Lumpur',
    ]);

    $venue->address()->update([
        'state_id' => (int) $stateId,
        'district_id' => $district->id,
        'subdistrict_id' => null,
    ]);

    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'event_format' => EventFormat::Physical,
        'institution_id' => $institution->id,
        'venue_id' => $venue->id,
        'starts_at' => now()->addDay()->setTime(17, 45),
        'ends_at' => now()->addDay()->setTime(19, 15),
        'timing_mode' => TimingMode::PrayerRelative,
        'prayer_display_text' => 'Selepas Asar',
    ]);

    $speaker->speakerEvents()->attach($event->id);

    $this->withCookie('user_timezone', 'Asia/Kuala_Lumpur')
        ->get(route('speakers.show', $speaker))
        ->assertSuccessful()
        ->assertSee('Dewan Utama Test, '.$district->name)
        ->assertDontSee('Dewan Utama Test, '.$district->name.', Kuala Lumpur');
});

it('renders speaker page when linked event has online format and no location address', function () {
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
    ]);

    $event = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'event_format' => \App\Enums\EventFormat::Online,
        'institution_id' => null,
        'venue_id' => null,
        'space_id' => null,
        'starts_at' => now()->addDay()->setTime(17, 45),
        'ends_at' => now()->addDay()->setTime(19, 15),
        'timing_mode' => TimingMode::Absolute,
    ]);

    $speaker->speakerEvents()->attach($event->id);

    $this->withCookie('user_timezone', 'Asia/Kuala_Lumpur')
        ->get(route('speakers.show', $speaker))
        ->assertSuccessful()
        ->assertSee($event->title);
});

it('shows linked non-speaker roles in a separate section on the speaker page', function () {
    $speaker = Speaker::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);

    $speakerEvent = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDay(),
        'title' => 'Kuliah Utama Penceramah',
    ]);

    $moderatedEvent = Event::factory()->create([
        'status' => 'approved',
        'visibility' => 'public',
        'starts_at' => now()->addDays(2),
        'title' => 'Forum Dengan Moderator',
    ]);

    $speakerEvent->keyPeople()->create([
        'speaker_id' => $speaker->id,
        'role' => EventParticipantRole::Speaker,
        'order_column' => 1,
        'is_public' => true,
    ]);

    $moderatedEvent->keyPeople()->create([
        'speaker_id' => $speaker->id,
        'role' => EventParticipantRole::Moderator,
        'order_column' => 1,
        'is_public' => true,
    ]);

    $response = $this->get(route('speakers.show', $speaker));

    $response->assertSuccessful()
        ->assertSee('Kuliah Utama Penceramah')
        ->assertSee('Peranan Lain Dalam Majlis')
        ->assertSee('Moderator')
        ->assertSee('Forum Dengan Moderator');

    expect(substr_count($response->getContent(), 'Forum Dengan Moderator'))->toBe(1);
});
