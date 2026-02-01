<?php

namespace Database\Seeders;

use App\Models\EventType;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EventTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define 3-level hierarchical event types structure
        // Root → Category → Subcategory
        $types = [
            'Islamic Events' => [
                'Ilmu' => [
                    'kuliah' => 'Kuliah',
                    'ceramah' => 'Ceramah',
                    'tazkirah' => 'Tazkirah',
                    'forum' => 'Forum',
                    'daurah' => 'Daurah',
                    'halaqah' => 'Halaqah',
                    'seminar' => 'Seminar',
                    'kelas_kitab' => 'Kelas Kitab',
                ],
                'Tilawah' => [
                    'bacaan_yasin' => 'Bacaan Yasin',
                    'khatam_quran' => 'Khatam Quran',
                    'majlis_tilawah' => 'Majlis Tilawah',
                    'tadabbur_quran' => 'Tadabbur Quran',
                ],
                'Ibadah' => [
                    'qiamullail' => 'Qiamullail',
                    'solat_hajat' => 'Solat Hajat',
                    'tahlil' => 'Tahlil',
                ],
                'Zikir & Doa' => [
                    'majlis_zikir' => 'Majlis Zikir',
                    'majlis_selawat' => 'Majlis Selawat',
                    'doa_selamat' => 'Doa Selamat',
                    'maulid' => 'Maulid',
                ],
                'Komuniti' => [
                    'gotong_royong' => 'Gotong Royong',
                    'kenduri' => 'Kenduri',
                    'iftar' => 'Iftar',
                    'sahur' => 'Sahur',
                    'korban' => 'Korban',
                    'aqiqah' => 'Aqiqah',
                ],
            ],
            'General Events' => [
                'Umum' => [
                    'academic' => 'Akademik',
                    'technology' => 'Teknologi',
                    'business' => 'Perniagaan',
                    'health' => 'Kesihatan',
                    'arts' => 'Kesenian',
                    'sports' => 'Sukan',
                ],
                'Lain-lain' => [
                    'other' => 'Lain-lain',
                ],
            ],
        ];

        $rootSortOrder = 0;

        foreach ($types as $rootName => $categories) {
            $root = EventType::updateOrCreate(
                ['slug' => Str::slug($rootName)],
                [
                    'name' => $rootName,
                    'parent_id' => null,
                    'order_column' => $rootSortOrder++,
                    'is_active' => true,
                ]
            );

            $categorySortOrder = 0;
            foreach ($categories as $categoryName => $items) {
                $category = EventType::updateOrCreate(
                    ['slug' => Str::slug($categoryName)],
                    [
                        'name' => $categoryName,
                        'parent_id' => $root->id,
                        'order_column' => $categorySortOrder++,
                        'is_active' => true,
                    ]
                );

                $itemSortOrder = 0;
                foreach ($items as $slug => $name) {
                    EventType::updateOrCreate(
                        ['slug' => $slug],
                        [
                            'name' => $name,
                            'parent_id' => $category->id,
                            'order_column' => $itemSortOrder++,
                            'is_active' => true,
                        ]
                    );
                }
            }
        }
    }
}
