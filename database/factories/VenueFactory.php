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
        $slug = Str::slug($name.'-'.fake()->unique()->numerify('###'));

        return [
            'institution_id' => Institution::factory(),
            'name' => $name,
            'slug' => $slug,
            'state_id' => null,
            'district_id' => null,
            'address_line1' => fake()->streetAddress(),
            'address_line2' => fake()->optional()->secondaryAddress(),
            'postcode' => fake()->postcode(),
            'city' => fake()->city(),
            'lat' => fake()->optional()->randomFloat(7, 1.0, 7.0),
            'lng' => fake()->optional()->randomFloat(7, 99.0, 119.0),
            'google_maps_place_id' => fake()->optional()->numerify('ChI###########'),
            'waze_place_url' => fake()->optional()->url(),
            'facilities' => [
                'parking' => fake()->boolean(),
                'oku' => fake()->boolean(),
                'women_section' => fake()->boolean(),
                'ablution_area' => fake()->boolean(),
            ],
        ];
    }
}
