<?php

use App\Models\Event;
use Database\Seeders\EventSeeder;
use Database\Seeders\TagSeeder;

it('seeds schedule events with required submit-event fields', function () {
    $this->seed(TagSeeder::class);

    // Prevent bulk event generation in EventSeeder so test stays focused and fast.
    Event::factory()->create();

    $this->seed(EventSeeder::class);

    $event = Event::query()
        ->where('slug', 'dhuha-adab-iman-2026-01-05')
        ->first();

    expect($event)->not->toBeNull();

    $ageGroup = $event?->age_group;

    expect($ageGroup)->toBeInstanceOf(\Illuminate\Support\Collection::class)
        ->and($ageGroup?->isNotEmpty())->toBeTrue()
        ->and($event?->organizer_type)->toBeIn([App\Models\Institution::class, App\Models\Speaker::class])
        ->and($event?->organizer_id)->not->toBeNull();

    $tagTypes = $event?->tags()->pluck('type')->unique()->values()->all() ?? [];

    expect($tagTypes)
        ->toContain('domain')
        ->toContain('discipline');

    $hasInstitutionLocation = is_string($event?->institution_id) && $event->institution_id !== '';
    $hasVenueLocation = is_string($event?->venue_id) && $event->venue_id !== '';

    expect($hasInstitutionLocation xor $hasVenueLocation)->toBeTrue();
});
