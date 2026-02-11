<?php

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('returns line1 through the address_line1 accessor', function () {
    $venue = Venue::factory()->create();

    $venue->address()->updateOrCreate([], [
        'line1' => 'Jalan Bukit Jelutong',
    ]);

    expect($venue->fresh()->address_line1)->toBe('Jalan Bukit Jelutong');
});
