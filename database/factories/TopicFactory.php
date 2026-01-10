<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Topic>
 */
class TopicFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $topics = [
            ['name' => 'Aqidah Ahlus Sunnah', 'category' => 'aqidah', 'is_official' => true],
            ['name' => 'Tauhid Rububiyyah', 'category' => 'aqidah', 'is_official' => true],
            ['name' => 'Tauhid Uluhiyyah', 'category' => 'aqidah', 'is_official' => true],
            ['name' => 'Asma wa Sifat', 'category' => 'aqidah', 'is_official' => true],
            ['name' => 'Rukun Iman', 'category' => 'aqidah', 'is_official' => true],
            ['name' => 'Fiqh Solat', 'category' => 'fiqh', 'is_official' => true],
            ['name' => 'Fiqh Puasa', 'category' => 'fiqh', 'is_official' => true],
            ['name' => 'Fiqh Zakat', 'category' => 'fiqh', 'is_official' => true],
            ['name' => 'Fiqh Haji & Umrah', 'category' => 'fiqh', 'is_official' => true],
            ['name' => 'Fiqh Muamalat', 'category' => 'fiqh', 'is_official' => true],
            ['name' => 'Fiqh Munakahat', 'category' => 'fiqh', 'is_official' => false],
            ['name' => 'Fiqh Jenayah', 'category' => 'fiqh', 'is_official' => false],
            ['name' => 'Fiqh Ibadah', 'category' => 'fiqh', 'is_official' => false],
            ['name' => 'Sirah Nabawiyyah', 'category' => 'sirah', 'is_official' => true],
            ['name' => 'Sirah Sahabah', 'category' => 'sirah', 'is_official' => true],
            ['name' => 'Sirah Khulafa Ar-Rasyidin', 'category' => 'sirah', 'is_official' => false],
            ['name' => 'Sirah Imam Syafie', 'category' => 'sirah', 'is_official' => false],
            ['name' => 'Sirah Rasulullah di Mekah', 'category' => 'sirah', 'is_official' => false],
            ['name' => 'Sirah Rasulullah di Madinah', 'category' => 'sirah', 'is_official' => false],
            ['name' => 'Akhlak Seorang Muslim', 'category' => 'akhlak', 'is_official' => true],
            ['name' => 'Adab Menuntut Ilmu', 'category' => 'akhlak', 'is_official' => true],
            ['name' => 'Adab Berjiran', 'category' => 'akhlak', 'is_official' => false],
            ['name' => 'Adab Dalam Masjid', 'category' => 'akhlak', 'is_official' => false],
            ['name' => 'Akhlak Rasulullah', 'category' => 'akhlak', 'is_official' => false],
            ['name' => 'Tafsir Al-Fatihah', 'category' => 'quran', 'is_official' => true],
            ['name' => 'Tafsir Al-Kahfi', 'category' => 'quran', 'is_official' => false],
            ['name' => 'Tafsir Juz Amma', 'category' => 'quran', 'is_official' => false],
            ['name' => 'Tadabbur Surah Yasin', 'category' => 'quran', 'is_official' => false],
            ['name' => 'Tadabbur Surah Al-Mulk', 'category' => 'quran', 'is_official' => false],
            ['name' => 'Ulumul Quran', 'category' => 'quran', 'is_official' => false],
            ['name' => 'Tajwid Asas', 'category' => 'quran', 'is_official' => false],
            ['name' => 'Halaqah Al-Quran', 'category' => 'quran', 'is_official' => false],
            ['name' => 'Tahsin Al-Quran', 'category' => 'quran', 'is_official' => false],
            ['name' => 'Hadis Arba\'in', 'category' => 'hadith', 'is_official' => true],
            ['name' => 'Riyadus Salihin', 'category' => 'hadith', 'is_official' => false],
            ['name' => 'Bulughul Maram', 'category' => 'hadith', 'is_official' => false],
            ['name' => 'Umdatul Ahkam', 'category' => 'hadith', 'is_official' => false],
            ['name' => 'Hadis Bukhari Pilihan', 'category' => 'hadith', 'is_official' => false],
            ['name' => 'Hadis Muslim Pilihan', 'category' => 'hadith', 'is_official' => false],
            ['name' => 'Tarbiah Remaja', 'category' => 'tarbiah', 'is_official' => false],
            ['name' => 'Tarbiah Keluarga', 'category' => 'tarbiah', 'is_official' => false],
            ['name' => 'Usrah Asas', 'category' => 'tarbiah', 'is_official' => false],
            ['name' => 'Manhaj Tarbiah', 'category' => 'tarbiah', 'is_official' => false],
            ['name' => 'Tazkiyah An-Nafs', 'category' => 'tarbiah', 'is_official' => false],
            ['name' => 'Keluarga Sakinah', 'category' => 'family', 'is_official' => false],
            ['name' => 'Parenting Islami', 'category' => 'family', 'is_official' => false],
            ['name' => 'Komunikasi Suami Isteri', 'category' => 'family', 'is_official' => false],
            ['name' => 'Pendidikan Anak', 'category' => 'family', 'is_official' => false],
        ];

        $topic = fake()->unique()->randomElement($topics);
        $name = $topic['name'];

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'category' => $topic['category'],
            'is_official' => $topic['is_official'],
        ];
    }
}
