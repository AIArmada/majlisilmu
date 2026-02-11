<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Institution>
 */
class InstitutionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['masjid', 'surau', 'madrasah']);
        $arabicNames = [
            'Al-Ikhlas',
            'Al-Amin',
            'An-Nur',
            'As-Salam',
            'At-Taqwa',
            'Al-Hidayah',
            'Al-Falah',
            'Al-Muttaqin',
            'Ar-Rahman',
            'Al-Munawwarah',
            'Al-Ansar',
            'Al-Mukminin',
            'Al-Azhar',
            'Al-Kauthar',
            'Al-Istiqamah',
        ];
        $locations = [
            'Taman Melawati',
            'Taman Daya',
            'Taman Universiti',
            'Taman Tun Dr Ismail',
            'Bandar Baru Bangi',
            'Setia Alam',
            'Kota Damansara',
            'Shah Alam',
            'Gombak',
            'Putrajaya',
            'Cyberjaya',
            'Wangsa Maju',
            'Seri Kembangan',
            'Kajang',
            'Rawang',
            'Ampang',
            'Cheras',
            'Subang Jaya',
            'Klang',
            'Batu Caves',
            'Sungai Buloh',
            'Puchong',
            'Senawang',
            'Melaka Tengah',
            'Johor Bahru',
        ];
        $arabicName = fake()->randomElement($arabicNames);
        $location = fake()->randomElement($locations);

        $masjidNames = [
            'Masjid Jamek Kampung Baru',
            'Masjid Sultan Salahuddin Abdul Aziz Shah',
            'Masjid Wilayah Persekutuan',
            'Masjid Putra',
            'Masjid Tuanku Mizan Zainal Abidin',
            'Masjid Negeri',
            'Masjid '.$arabicName,
            'Masjid '.$arabicName.' '.$location,
            'Masjid Jamek '.$location,
            'Masjid '.$location,
        ];
        $surauNames = [
            'Surau '.$arabicName,
            'Surau '.$arabicName.' '.$location,
            'Surau '.$location,
            'Surau Al-Ikhlas '.$location,
            'Surau An-Nur '.$location,
        ];
        $otherNames = [
            'Pusat Islam '.$location,
            'Madrasah '.$arabicName,
            'Maahad Tahfiz '.$location,
            'Kompleks Islam '.$location,
            'Markaz Tarbiah '.$location,
            'Akademi Tahfiz '.$arabicName,
        ];

        $name = match ($type) {
            'masjid' => fake()->randomElement($masjidNames),
            'surau' => fake()->randomElement($surauNames),
            'madrasah' => fake()->randomElement($otherNames),
            default => fake()->randomElement($otherNames),
        };

        return [
            'type' => $type,
            'name' => $name,
            'slug' => Str::slug($name.'-'.Str::random(8)),
            'description' => fake()->optional()->paragraph(),
            'status' => 'verified',
            'is_active' => true,
        ];
    }

    #[\Override]
    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\Institution $institution) {
            $institution->address()->create([
                'line1' => fake()->streetAddress(),
                'line2' => fake()->optional()->words(2, true),
                'postcode' => fake()->postcode(),
                'lat' => fake()->randomFloat(7, 1.0, 7.0),
                'lng' => fake()->randomFloat(7, 99.0, 119.0),
            ]);

            $institution->contacts()->create([
                'category' => 'email',
                'value' => fake()->safeEmail(),
                'type' => 'work',
            ]);

            $institution->contacts()->create([
                'category' => 'phone',
                'value' => fake()->phoneNumber(),
                'type' => 'work',
            ]);
        });
    }
}
