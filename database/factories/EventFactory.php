<?php

namespace Database\Factories;

use App\Enums\EventFormat;
use App\Enums\PrayerOffset;
use App\Enums\PrayerReference;
use App\Enums\TimingMode;
use App\Models\Institution;
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
            'Kuliah / Ceramah',
            'Tazkirah',
            'Kelas / Daurah',
            'Forum Perdana',
            'Seminar / Konvensyen',
            'Sesi Tadabbur',
            'Majlis Ilmu',
        ];
        $topics = [
            'Tafsir Al-Fatihah',
            'Tafsir Al-Kahfi',
            'Fiqh Solat',
            'Fiqh Puasa',
            'Fiqh Zakat',
            'Sirah Nabawiyyah',
            'Sirah Sahabah',
            'Adab Menuntut Ilmu',
            'Aqidah Ahlus Sunnah',
            'Hadis Arba\'in',
            'Riyadus Salihin',
            'Tazkiyah An-Nafs',
            'Muamalat Islam',
            'Keluarga Sakinah',
            'Remaja & Identiti',
            'Persiapan Ramadhan',
            'Isu Semasa Ummah',
            'Tadabbur Surah Yasin',
            'Tadabbur Surah Al-Mulk',
            'Tafsir Juz Amma',
        ];
        $books = [
            'Riyadus Salihin',
            'Bulughul Maram',
            'Al-Arba\'in An-Nawawiyyah',
            'Tafsir Ibnu Kathir',
            'Fiqh Manhaji',
            'Umdatul Ahkam',
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
            'Kelas / Daurah: '.$book,
            'Halaqah '.$book,
            'Tadabbur: '.$topic,
            $type.' bersama Asatizah',
        ]);
        $status = fake()->randomElement(['approved', 'approved', 'pending', 'draft']);
        $publishedAt = $status === 'approved' ? (clone $startsAt)->subDays(fake()->numberBetween(1, 14)) : null;
        $livestreamUrl = fake()->optional()->url();
        $recordingUrl = fake()->optional()->url();

        // Determine event format: 60% physical, 25% online, 15% hybrid
        $eventFormat = fake()->randomElement([
            EventFormat::Physical,
            EventFormat::Physical,
            EventFormat::Physical,
            EventFormat::Physical,
            EventFormat::Physical,
            EventFormat::Physical,
            EventFormat::Online,
            EventFormat::Online,
            EventFormat::Online,
            EventFormat::Hybrid,
            EventFormat::Hybrid,
            EventFormat::Hybrid,
        ]);

        // Adjust venue_id and URLs based on format
        $venueId = null;
        $liveUrl = null;
        $recordingUrlFinal = null;

        if ($eventFormat === EventFormat::Physical) {
            $venueId = Venue::factory();
            $liveUrl = null;
            $recordingUrlFinal = $recordingUrl ? Str::replaceFirst('http://', 'https://', $recordingUrl) : null;
        } elseif ($eventFormat === EventFormat::Online) {
            $venueId = null;
            $liveUrl = $livestreamUrl ? Str::replaceFirst('http://', 'https://', $livestreamUrl) : 'https://meet.google.com/'.Str::random(10);
            $recordingUrlFinal = $recordingUrl ? Str::replaceFirst('http://', 'https://', $recordingUrl) : null;
        } else { // Hybrid
            $venueId = fake()->boolean(75) ? Venue::factory() : null;
            $liveUrl = $livestreamUrl ? Str::replaceFirst('http://', 'https://', $livestreamUrl) : 'https://meet.google.com/'.Str::random(10);
            $recordingUrlFinal = $recordingUrl ? Str::replaceFirst('http://', 'https://', $recordingUrl) : null;
        }

        return [
            'institution_id' => Institution::factory(),
            'venue_id' => $venueId,
            'title' => $title,
            'slug' => Str::slug($title.'-'.Str::random(8)),
            'description' => fake()->optional()->paragraphs(2, true),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => 'Asia/Kuala_Lumpur',
            'timing_mode' => TimingMode::Absolute->value,
            'prayer_reference' => null,
            'prayer_offset' => null,
            'prayer_display_text' => null,
            'event_type' => [fake()->randomElement(\App\Enums\EventType::cases())],
            'gender' => fake()->randomElement(\App\Enums\EventGenderRestriction::cases()),
            'age_group' => [fake()->randomElement(\App\Enums\EventAgeGroup::cases())],
            'children_allowed' => fake()->boolean(80), // 80% allow children
            'event_format' => $eventFormat,
            'visibility' => fake()->randomElement([
                \App\Enums\EventVisibility::Public,
                \App\Enums\EventVisibility::Public,
                \App\Enums\EventVisibility::Unlisted,
            ]),
            'status' => $status,
            'live_url' => $liveUrl,
            'recording_url' => $recordingUrlFinal,
            'views_count' => fake()->numberBetween(0, 2000),
            'saves_count' => fake()->numberBetween(0, 500),
            'registrations_count' => fake()->numberBetween(0, 200),
            'published_at' => $publishedAt,
            'is_muslim_only' => fake()->boolean(90), // 90% are muslim only
            'is_active' => true,
        ];
    }

    /**
     * Configure the model factory.
     */
    #[\Override]
    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\Event $event) {
            // 30% of events have registration settings
            if (fake()->boolean(30) && ! $event->settings()->exists()) {
                $event->settings()->create([
                    'registration_required' => true,
                    'capacity' => fake()->numberBetween(30, 300),
                    'registration_opens_at' => $event->starts_at->copy()->subDays(7),
                    'registration_closes_at' => $event->starts_at->copy()->subDays(1),
                ]);
            }
        });
    }

    /**
     * Indicate that the event is prayer-relative.
     */
    public function prayerRelative(
        ?PrayerReference $prayer = null,
        ?PrayerOffset $offset = null
    ): static {
        $prayer ??= fake()->randomElement(PrayerReference::cases());
        $offset ??= fake()->randomElement([
            PrayerOffset::Immediately,
            PrayerOffset::After15,
            PrayerOffset::After30,
        ]);

        return $this->state(fn (array $attributes) => [
            'timing_mode' => TimingMode::PrayerRelative->value,
            'prayer_reference' => $prayer->value,
            'prayer_offset' => $offset->value,
            'prayer_display_text' => $offset->displayText($prayer),
        ]);
    }

    /**
     * Indicate a Kuliah Maghrib event.
     */
    public function kuliahMaghrib(): static
    {
        return $this->prayerRelative(
            PrayerReference::Maghrib,
            PrayerOffset::Immediately
        )->state(fn (array $attributes) => [
            'title' => 'Kuliah Maghrib: '.fake()->randomElement([
                'Tafsir Al-Kahfi',
                'Sirah Nabawiyyah',
                'Fiqh Solat',
            ]),
        ]);
    }

    /**
     * Indicate a Kuliah Isya event.
     */
    public function kuliahIsya(): static
    {
        return $this->prayerRelative(
            PrayerReference::Isha,
            PrayerOffset::After15
        )->state(fn (array $attributes) => [
            'title' => 'Kuliah Isya: '.fake()->randomElement([
                'Hadis Arba\'in',
                'Riyadus Salihin',
                'Aqidah Ahlus Sunnah',
            ]),
        ]);
    }

    /**
     * Indicate a Tazkirah Subuh event.
     */
    public function tazkirahSubuh(): static
    {
        return $this->prayerRelative(
            PrayerReference::Fajr,
            PrayerOffset::Immediately
        )->state(fn (array $attributes) => [
            'title' => 'Tazkirah Subuh: '.fake()->randomElement([
                'Tazkiyah An-Nafs',
                'Adab Menuntut Ilmu',
                'Zikir Pagi',
            ]),
        ]);
    }
}
