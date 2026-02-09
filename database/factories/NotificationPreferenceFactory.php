<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationFrequency;
use App\Enums\NotificationPreferenceKey;
use App\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\NotificationPreference>
 */
class NotificationPreferenceFactory extends Factory
{
    protected $model = NotificationPreference::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_type' => User::class,
            'owner_id' => User::factory(),
            'notification_key' => NotificationPreferenceKey::SavedSearchDigest->value,
            'enabled' => true,
            'frequency' => NotificationFrequency::Daily->value,
            'channels' => [NotificationChannel::Email->value],
            'quiet_hours_start' => null,
            'quiet_hours_end' => null,
            'timezone' => config('app.timezone'),
            'meta' => null,
        ];
    }
}
