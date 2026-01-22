<?php

namespace Database\Factories;

use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Donation>
 */
class DonationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $method = fake()->randomElement(['bank_account', 'duitnow', 'ewallet']);

        $base = [
            'donatable_type' => Institution::class,
            'donatable_id' => Institution::factory(),
            'label' => fake()->optional()->randomElement(['Tabung Masjid', 'Infaq', 'Dana Pembangunan', 'Zakat', 'Sedekah']),
            'recipient_name' => fake()->name(),
            'method' => $method,
            'reference_note' => fake()->optional()->sentence(),
            'status' => fake()->randomElement(['unverified', 'verified']),
            'is_default' => false,
        ];

        return match ($method) {
            'bank_account' => array_merge($base, $this->bankAccountFields()),
            'duitnow' => array_merge($base, $this->duitnowFields()),
            'ewallet' => array_merge($base, $this->ewalletFields()),
        };
    }

    protected function bankAccountFields(): array
    {
        return [
            'bank_code' => fake()->randomElement(['MBB', 'CIMB', 'BIMB', 'RHB', 'PBB', 'BKRM']),
            'bank_name' => fake()->randomElement([
                'Maybank',
                'CIMB',
                'Bank Islam',
                'RHB',
                'Public Bank',
                'Bank Rakyat',
            ]),
            'account_number' => fake()->numerify('############'),
            // Ensure other method fields are null
            'duitnow_type' => null,
            'duitnow_value' => null,
            'ewallet_provider' => null,
            'ewallet_handle' => null,
            'ewallet_qr_payload' => null,
        ];
    }

    protected function duitnowFields(): array
    {
        $type = fake()->randomElement(['mobile', 'nric', 'business']);

        return [
            'duitnow_type' => $type,
            'duitnow_value' => match ($type) {
                'mobile' => fake()->numerify('01#-#######'),
                'nric' => fake()->numerify('######-##-####'),
                'business' => fake()->numerify('########'),
            },
            // Ensure other method fields are null
            'bank_code' => null,
            'bank_name' => null,
            'account_number' => null,
            'ewallet_provider' => null,
            'ewallet_handle' => null,
            'ewallet_qr_payload' => null,
        ];
    }

    protected function ewalletFields(): array
    {
        $provider = fake()->randomElement(['tng', 'grab', 'shopee', 'boost']);

        return [
            'ewallet_provider' => $provider,
            'ewallet_handle' => fake()->numerify('01#-#######'),
            'ewallet_qr_payload' => null,
            // Ensure other method fields are null
            'bank_code' => null,
            'bank_name' => null,
            'account_number' => null,
            'duitnow_type' => null,
            'duitnow_value' => null,
        ];
    }

    /**
     * State for bank account method.
     */
    public function bankAccount(): static
    {
        return $this->state(fn (array $attributes) => array_merge(
            ['method' => 'bank_account'],
            $this->bankAccountFields()
        ));
    }

    /**
     * State for DuitNow method.
     */
    public function duitnow(): static
    {
        return $this->state(fn (array $attributes) => array_merge(
            ['method' => 'duitnow'],
            $this->duitnowFields()
        ));
    }

    /**
     * State for e-wallet method.
     */
    public function ewallet(): static
    {
        return $this->state(fn (array $attributes) => array_merge(
            ['method' => 'ewallet'],
            $this->ewalletFields()
        ));
    }

    /**
     * State for verified status.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'verified',
            'verified_at' => now(),
        ]);
    }

    /**
     * State for default donation.
     */
    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
