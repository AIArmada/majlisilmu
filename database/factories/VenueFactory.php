<?php

namespace Database\Factories;

use App\Models\Institution;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Venue>
 */
class VenueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->randomElement([
            'Dewan Utama',
            'Dewan Serbaguna',
            'Dewan Solat Utama',
            'Dewan Al-Ikhlas',
            'Dewan An-Nur',
            'Dewan Al-Amin',
            'Balai Islam',
            'Anjung Ilmu',
            'Ruang Serbaguna',
            'Dewan Kuliah',
            'Dewan Seminar',
        ]);
        $slug = Str::slug($name.'-'.Str::random(8));

        return [
            'institution_id' => Institution::factory(),
            'name' => $name,
            'slug' => $slug,
            'description' => fake()->paragraph(),
            'type' => fake()->randomElement([
                'main_hall',
                'seminar_room',
                'classroom',
                'auditorium',
                'field',
                'foyer',
                'other',
            ]),
            'facilities' => [
                'parking' => fake()->boolean(),
                'oku' => fake()->boolean(),
                'women_section' => fake()->boolean(),
                'ablution_area' => fake()->boolean(),
            ],
            'status' => 'verified',
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\Venue $venue) {
            $venue->address()->create([
                'line1' => fake()->streetAddress(),
                'line2' => fake()->optional()->secondaryAddress(),
                'postcode' => fake()->postcode(),
                'lat' => fake()->randomFloat(7, 1.0, 7.0),
                'lng' => fake()->randomFloat(7, 99.0, 119.0),
                'google_place_id' => fake()->optional()->numerify('ChI###########'),
                'waze_url' => fake()->optional()->url(),
            ]);
        });
    }
}
