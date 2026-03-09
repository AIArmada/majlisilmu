<?php

namespace Database\Factories;

use App\Models\DawahShareAttribution;
use App\Models\DawahShareLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<DawahShareAttribution>
 */
class DawahShareAttributionFactory extends Factory
{
    protected $model = DawahShareAttribution::class;

    public function definition(): array
    {
        return [
            'link_id' => DawahShareLink::factory(),
            'user_id' => User::factory(),
            'visitor_key' => (string) Str::ulid(),
            'cookie_value' => (string) Str::ulid(),
            'landing_url' => rtrim((string) config('app.url'), '/').'/majlis',
            'referrer_url' => 'https://example.com',
            'ip_address' => fake()->ipv4(),
            'user_agent' => fake()->userAgent(),
            'signed_up_user_id' => null,
            'metadata' => [],
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'expires_at' => now()->addDays(30),
        ];
    }
}
