<?php

namespace Database\Factories;

use App\Models\DawahShareAttribution;
use App\Models\DawahShareLink;
use App\Models\DawahShareVisit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DawahShareVisit>
 */
class DawahShareVisitFactory extends Factory
{
    protected $model = DawahShareVisit::class;

    public function definition(): array
    {
        return [
            'link_id' => DawahShareLink::factory(),
            'attribution_id' => DawahShareAttribution::factory(),
            'visitor_key' => (string) Str::ulid(),
            'visited_url' => rtrim((string) config('app.url'), '/').'/majlis',
            'subject_type' => 'page',
            'subject_id' => null,
            'subject_key' => 'page:'.fake()->unique()->slug(),
            'visit_kind' => 'landing',
            'metadata' => [],
            'occurred_at' => now(),
        ];
    }
}
