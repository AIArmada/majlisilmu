<?php

namespace Database\Factories;

use App\Models\Institution;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Series>
 */
class SeriesFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $topics = [
            'Tafsir Al-Fatihah',
            'Tafsir Al-Kahfi',
            'Fiqh Solat',
            'Fiqh Muamalat',
            'Sirah Nabawiyyah',
            'Hadis Arba\'in',
            'Akhlak & Adab',
            'Tazkiyah An-Nafs',
            'Keluarga Sakinah',
            'Tarbiah Remaja',
        ];
        $books = [
            'Riyadus Salihin',
            'Bulughul Maram',
            'Al-Arba\'in An-Nawawiyyah',
            'Tafsir Ibnu Kathir',
            'Fiqh Manhaji',
        ];

        $topic = fake()->randomElement($topics);
        $book = fake()->randomElement($books);
        $title = fake()->randomElement([
            'Siri Tafsir '.$topic,
            'Halaqah Kitab '.$book,
            'Kelas Fiqh '.$topic,
            'Siri Sirah '.$topic,
            'Kuliah Kitab '.$book,
            'Daurah '.$topic,
        ]);

        return [
            'institution_id' => Institution::factory(),
            'venue_id' => fake()->boolean(60) ? Venue::factory() : null,
            'speaker_id' => null, // Default to null, can be overridden
            'title' => $title,
            'slug' => Str::slug($title.'-'.fake()->unique()->numerify('###')),
            'description' => fake()->optional()->paragraph(),
            'visibility' => fake()->randomElement(['public', 'unlisted', 'private']),
            'language' => fake()->randomElement(['Bahasa Melayu', 'English', 'Mandarin', 'Tamil', 'Javanese', 'Arabic']),
            'audience' => fake()->randomElement(['Umum', 'Belia', 'Muslimah', 'Keluarga', 'Pelajar IPT', 'Profesional']),
        ];
    }
}
