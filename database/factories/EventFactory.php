<?php

namespace Database\Factories;

use App\Models\DonationAccount;
use App\Models\Institution;
use App\Models\Series;
use App\Models\Venue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Event>
 */
class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventTypes = [
            'Kuliah Maghrib', 'Tazkirah Subuh', 'Kuliah Dhuha', 'Halaqah Al-Quran',
            'Kelas Fiqh', 'Kelas Kitab', 'Forum Perdana', 'Sesi Tadabbur',
            'Majlis Ilmu', 'Daurah Ilmiah', 'Seminar Ilmu', 'Kuliah Isya',
        ];
        $topics = [
            'Tafsir Al-Fatihah', 'Tafsir Al-Kahfi', 'Fiqh Solat', 'Fiqh Puasa',
            'Fiqh Zakat', 'Sirah Nabawiyyah', 'Sirah Sahabah', 'Adab Menuntut Ilmu',
            'Aqidah Ahlus Sunnah', 'Hadis Arba\'in', 'Riyadus Salihin',
            'Tazkiyah An-Nafs', 'Muamalat Islam', 'Keluarga Sakinah',
            'Remaja & Identiti', 'Persiapan Ramadhan', 'Isu Semasa Ummah',
            'Tadabbur Surah Yasin', 'Tadabbur Surah Al-Mulk', 'Tafsir Juz Amma',
        ];
        $books = [
            'Riyadus Salihin', 'Bulughul Maram', 'Al-Arba\'in An-Nawawiyyah',
            'Tafsir Ibnu Kathir', 'Fiqh Manhaji', 'Umdatul Ahkam',
        ];

        $startsAt = Carbon::instance(fake()->dateTimeBetween('now', '+2 months'));
        $endsAt = (clone $startsAt)->addHours(fake()->numberBetween(1, 3));
        $type = fake()->randomElement($eventTypes);
        $topic = fake()->randomElement($topics);
        $book = fake()->randomElement($books);
        $title = fake()->randomElement([
            $type.': '.$topic,
            $type.' - '.$topic,
            $topic.' ('.$type.')',
            'Kelas Kitab: '.$book,
            'Halaqah '.$book,
            'Tadabbur: '.$topic,
            $type.' bersama Asatizah',
        ]);
        $registrationRequired = fake()->boolean(30);
        $registrationOpensAt = $registrationRequired ? (clone $startsAt)->subDays(7) : null;
        $registrationClosesAt = $registrationRequired ? (clone $startsAt)->subDays(1) : null;
        $status = fake()->randomElement(['approved', 'approved', 'pending', 'draft']);
        $publishedAt = $status === 'approved' ? (clone $startsAt)->subDays(fake()->numberBetween(1, 14)) : null;
        $livestreamUrl = fake()->optional()->url();
        $recordingUrl = fake()->optional()->url();

        return [
            'institution_id' => Institution::factory(),
            'venue_id' => fake()->boolean(75) ? Venue::factory() : null,
            'series_id' => fake()->boolean(25) ? Series::factory() : null,
            'title' => $title,
            'slug' => Str::slug($title.'-'.fake()->unique()->numerify('###')),
            'description' => fake()->optional()->paragraphs(2, true),
            'state_id' => null,
            'district_id' => null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => 'Asia/Kuala_Lumpur',
            'language' => fake()->randomElement(['Bahasa Melayu', 'English', 'Mandarin', 'Tamil', 'Javanese', 'Arabic']),
            'genre' => fake()->randomElement(['Kuliah', 'Tazkirah', 'Halaqah', 'Forum', 'Daurah', 'Kelas Kitab']),
            'audience' => fake()->randomElement(['Umum', 'Belia', 'Muslimah', 'Keluarga', 'Pelajar IPT', 'Profesional']),
            'visibility' => fake()->randomElement(['public', 'public', 'unlisted']),
            'status' => $status,
            'livestream_url' => $livestreamUrl
                ? Str::replaceFirst('http://', 'https://', $livestreamUrl)
                : null,
            'recording_url' => $recordingUrl
                ? Str::replaceFirst('http://', 'https://', $recordingUrl)
                : null,
            'donation_account_id' => fake()->boolean(50) ? DonationAccount::factory() : null,
            'registration_required' => $registrationRequired,
            'capacity' => $registrationRequired ? fake()->numberBetween(30, 300) : null,
            'registration_opens_at' => $registrationOpensAt,
            'registration_closes_at' => $registrationClosesAt,
            'views_count' => fake()->numberBetween(0, 2000),
            'saves_count' => fake()->numberBetween(0, 500),
            'registrations_count' => fake()->numberBetween(0, 200),
            'published_at' => $publishedAt,
        ];
    }
}
