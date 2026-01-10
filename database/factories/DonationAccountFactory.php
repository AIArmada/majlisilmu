<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DonationAccount>
 */
class DonationAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'institution_id' => Institution::factory(),
            'label' => fake()->optional()->randomElement(['Tabung Masjid', 'Infaq', 'Dana Pembangunan']),
            'recipient_name' => fake()->name(),
            'bank_name' => fake()->optional()->randomElement([
                'Maybank',
                'CIMB',
                'Bank Islam',
                'RHB',
                'Public Bank',
                'Bank Rakyat',
            ]),
            'account_number' => fake()->optional()->numerify('############'),
            'duitnow_id' => fake()->optional()->numerify('DN########'),
            'qr_asset_id' => fake()->boolean(40) ? MediaAsset::factory() : null,
            'verification_status' => fake()->randomElement(['unverified', 'pending', 'verified']),
        ];
    }
}
