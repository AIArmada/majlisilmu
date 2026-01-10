<?php

namespace Database\Seeders;

use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TopicSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $topics = [
            ['name' => 'Aqidah', 'category' => 'aqidah', 'is_official' => true],
            ['name' => 'Fiqh Ibadah', 'category' => 'fiqh', 'is_official' => true],
            ['name' => 'Sirah Nabawiyyah', 'category' => 'sirah', 'is_official' => true],
            ['name' => 'Akhlak', 'category' => 'akhlak', 'is_official' => true],
            ['name' => 'Tafsir Al-Quran', 'category' => 'quran', 'is_official' => true],
            ['name' => 'Hadith', 'category' => 'hadith', 'is_official' => true],
            ['name' => 'Tarbiah', 'category' => 'tarbiah', 'is_official' => true],
            ['name' => 'Keluarga & Parenting', 'category' => 'family', 'is_official' => false],
            ['name' => 'Muamalat', 'category' => 'fiqh', 'is_official' => false],
            ['name' => 'Sirah Sahabah', 'category' => 'sirah', 'is_official' => false],
        ];

        foreach ($topics as $topic) {
            Topic::query()->updateOrCreate([
                'slug' => Str::slug($topic['name']),
            ], $topic);
        }
    }
}
