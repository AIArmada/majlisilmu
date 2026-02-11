<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Space>
 */
class SpaceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $prefix = fake()->randomElement(['Bilik', 'Ruang', 'Sudut', 'Anjung', 'Pejabat', 'Pusat']);
        $suffix = fake()->randomElement([
            'Solat Utama',
            'Solat Muslimah',
            'Mesyuarat',
            'Kuliah',
            'Parkir',
            'Pentadbiran',
            'Jamuan',
            'Legar',
            'Bacaan',
            'VVIP',
            'Imam',
            'Bilal',
            'IT',
            'Rakaman',
            'Rehat',
            'Gerakan',
            'Pameran',
            'Pusat Sumber',
            'Kafeteria',
            'Wuduk Lelaki',
            'Wuduk Wanita',
            'Al-Quran',
            'Al-Hadith',
            'Al-Falah',
            'Al-Hidayah',
        ]);

        $name = fake()->unique()->regexify($prefix.' '.$suffix.' (Alpha|Beta|Gamma|A|B|C|1|2|3)');
        Str::slug($name.'-'.Str::random(5));

        return [
            'name' => $name,
            'slug' => Str::slug($name.'-'.Str::random(5)),
            'capacity' => fake()->randomElement([10, 20, 30, 50, 100, 200]),
            'is_active' => true,
        ];
    }
}
