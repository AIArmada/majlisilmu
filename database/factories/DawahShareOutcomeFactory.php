<?php

namespace Database\Factories;

use App\Models\DawahShareAttribution;
use App\Models\DawahShareLink;
use App\Models\DawahShareOutcome;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DawahShareOutcome>
 */
class DawahShareOutcomeFactory extends Factory
{
    protected $model = DawahShareOutcome::class;

    public function definition(): array
    {
        return [
            'link_id' => DawahShareLink::factory(),
            'attribution_id' => DawahShareAttribution::factory(),
            'sharer_user_id' => User::factory(),
            'actor_user_id' => User::factory(),
            'outcome_type' => 'signup',
            'subject_type' => 'page',
            'subject_id' => null,
            'subject_key' => 'page:'.fake()->unique()->slug(),
            'outcome_key' => 'outcome:'.Str::uuid()->toString(),
            'metadata' => [],
            'occurred_at' => now(),
        ];
    }
}
