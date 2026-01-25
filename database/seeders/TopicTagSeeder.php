<?php

namespace Database\Seeders;

use App\Models\Topic;
use Illuminate\Database\Seeder;

class TopicTagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tagsMap = [
            'Aqidah' => ['aqidah'],
            'Tauhid' => ['aqidah'],
            'Fiqh' => ['fiqah'],
            'Ibadah' => ['fiqah'],
            'Solat' => ['fiqah'],
            'Puasa' => ['fiqah'],
            'Zakat' => ['fiqah'],
            'Haji' => ['fiqah'],
            'Sirah' => ['sirah'],
            'Akhlak' => ['akhlak', 'tasawuf'],
            'Adab' => ['akhlak'],
            'Tazkiyah' => ['tasawuf'],
            'Tafsir' => ['al-quran'],
            'Tadabbur' => ['al-quran'],
            'Tajwid' => ['al-quran'],
            'Quran' => ['al-quran'],
            'Hadis' => ['hadis'],
            'Hadith' => ['hadis'],
            'Bukhari' => ['hadis'],
            'Muslim' => ['hadis'],
            'Riyadus' => ['hadis', 'akhlak'],
        ];

        Topic::all()->each(function (Topic $topic) use ($tagsMap) {
            foreach ($tagsMap as $keyword => $tags) {
                if (str_contains(strtolower($topic->name), strtolower($keyword))) {
                    $topic->attachIslamicTags($tags);
                }
            }
        });
    }
}
