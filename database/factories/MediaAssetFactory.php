<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MediaAsset>
 */
class MediaAssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $extension = fake()->randomElement(['jpg', 'png']);

        return [
            'disk' => fake()->randomElement(['public', 's3', 'r2']),
            'path' => 'media/'.Str::uuid().'.'.$extension,
            'mime_type' => $extension === 'png' ? 'image/png' : 'image/jpeg',
            'size_bytes' => fake()->numberBetween(15_000, 2_500_000),
            'original_name' => fake()->words(2, true).'.'.$extension,
            'uploaded_by' => fake()->boolean(70) ? User::factory() : null,
        ];
    }
}
