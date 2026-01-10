<?php

namespace Database\Factories;

use App\Models\Event;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EventMediaLink>
 */
class EventMediaLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $provider = fake()->randomElement(['youtube', 'facebook', 'zoom', 'twitch']);

        return [
            'event_id' => Event::factory(),
            'type' => fake()->randomElement(['livestream', 'recording', 'playlist', 'slides', 'other']),
            'provider' => $provider,
            'url' => 'https://'.$provider.'.com/'.Str::random(12),
            'is_primary' => fake()->boolean(20),
        ];
    }
}
