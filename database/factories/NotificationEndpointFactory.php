<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Models\NotificationEndpoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NotificationEndpoint>
 */
class NotificationEndpointFactory extends Factory
{
    protected $model = NotificationEndpoint::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $channel = fake()->randomElement([
            NotificationChannel::Email->value,
            NotificationChannel::Sms->value,
            NotificationChannel::Whatsapp->value,
            NotificationChannel::Telegram->value,
        ]);

        $address = match ($channel) {
            NotificationChannel::Email->value => fake()->safeEmail(),
            NotificationChannel::Sms->value,
            NotificationChannel::Whatsapp->value => fake()->e164PhoneNumber(),
            default => '@'.fake()->userName(),
        };

        return [
            'owner_type' => User::class,
            'owner_id' => User::factory(),
            'channel' => $channel,
            'address' => $address,
            'external_id' => null,
            'status' => 'active',
            'is_primary' => false,
            'verified_at' => now(),
            'meta' => null,
        ];
    }
}
