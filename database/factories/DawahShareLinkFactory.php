<?php

namespace Database\Factories;

use App\Models\DawahShareLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DawahShareLink>
 */
class DawahShareLinkFactory extends Factory
{
    protected $model = DawahShareLink::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subject_type' => 'page',
            'subject_id' => null,
            'subject_key' => 'page:'.fake()->unique()->slug(),
            'destination_url' => rtrim((string) config('app.url'), '/').'/majlis',
            'canonical_url' => rtrim((string) config('app.url'), '/').'/majlis',
            'share_token' => Str::random(40),
            'title_snapshot' => fake()->sentence(),
            'metadata' => [],
            'last_shared_at' => now(),
        ];
    }
}
