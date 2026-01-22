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
        // Define hierarchical topics structure
        // Format: 'Category' => ['child1', 'child2', ...]
        // Or nested: 'Category' => ['Subcategory' => ['child1', ...]]
        $hierarchy = [
            'Aqidah' => [
                'Tauhid' => [
                    'Tauhid Rububiyyah',
                    'Tauhid Uluhiyyah',
                    'Asma wa Sifat',
                ],
                'Rukun Iman',
                'Aqidah Ahlus Sunnah',
            ],
            'Fiqh' => [
                'Ibadah' => [
                    'Solat',
                    'Puasa',
                    'Zakat',
                    'Haji & Umrah',
                ],
                'Muamalat',
                'Munakahat',
                'Jenayah',
            ],
            'Sirah' => [
                'Sirah Nabawiyyah' => [
                    'Mekah',
                    'Madinah',
                ],
                'Sirah Sahabah',
                'Sirah Khulafa Ar-Rasyidin',
            ],
            'Akhlak' => [
                'Adab' => [
                    'Adab Menuntut Ilmu',
                    'Adab Berjiran',
                    'Adab Dalam Masjid',
                ],
                'Akhlak Rasulullah',
            ],
            'Al-Quran' => [
                'Tafsir' => [
                    'Tafsir Al-Fatihah',
                    'Tafsir Al-Kahfi',
                    'Tafsir Juz Amma',
                ],
                'Tadabbur' => [
                    'Tadabbur Surah Yasin',
                    'Tadabbur Surah Al-Mulk',
                ],
                'Ulumul Quran',
                'Tajwid',
            ],
            'Hadith' => [
                'Hadis Arba\'in',
                'Riyadus Salihin',
                'Bulughul Maram',
            ],
            'Tarbiah' => [
                'Tarbiah Remaja',
                'Tarbiah Keluarga',
                'Tazkiyah An-Nafs',
            ],
            'Keluarga' => [
                'Parenting Islami',
                'Pendidikan Anak',
                'Komunikasi Suami Isteri',
            ],
        ];

        $this->seedTopics($hierarchy);
    }

    /**
     * Recursively seed topics with their children.
     *
     * @param  array<string, mixed>  $items
     */
    private function seedTopics(array $items, ?Topic $parent = null, int &$sortOrder = 0): void
    {
        foreach ($items as $key => $value) {
            $sortOrder++;

            // If key is numeric, then value is a leaf topic name
            if (is_int($key)) {
                $name = $value;
                $children = [];
            } else {
                // Key is the topic name, value may be children array
                $name = $key;
                $children = is_array($value) ? $value : [];
            }

            $topic = Topic::query()->updateOrCreate(
                [
                    'slug' => Str::slug($name),
                ],
                [
                    'parent_id' => $parent?->id,
                    'name' => $name,
                    'is_official' => $parent === null, // Root topics are official
                    'sort_order' => $sortOrder,
                ]
            );

            // Recursively create children
            if (! empty($children)) {
                $childSortOrder = 0;
                $this->seedTopics($children, $topic, $childSortOrder);
            }
        }
    }
}
