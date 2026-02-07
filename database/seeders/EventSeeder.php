<?php

namespace Database\Seeders;

use App\Enums\PrayerOffset;
use App\Enums\PrayerReference;
use App\Enums\TimingMode;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Temporarily disable the EventObserver to speed up seeding
        Event::unsetEventDispatcher();

        $hadEvents = Event::query()->exists();

        $this->seedMajlisIlmuSchedule();

        if (! $hadEvents) {
            $this->seedBulkEvents();
        }

        // Re-enable event dispatcher after seeding
        Event::setEventDispatcher(app('events'));
    }

    private function seedBulkEvents(): void
    {

        \Illuminate\Support\Facades\DB::transaction(function (): void {
            $institutions = Institution::query()
                ->with(['venues', 'series'])
                ->limit(90)
                ->get();
            $speakerIds = Speaker::query()->pluck('id')->toArray();

            if ($institutions->isEmpty()) {
                return;
            }

            $count = 0;
            $limit = 850; // We already have ~50 from seedMajlisIlmuSchedule

            foreach ($institutions as $institution) {
                if ($count >= $limit) {
                    break;
                }

                $venue = $institution->venues->isNotEmpty() ? $institution->venues->random() : null;
                $series = $institution->series->isNotEmpty() ? $institution->series->random() : null;

                $baseAttributes = [
                    'institution_id' => $institution->id,
                ];

                // Create 10 events per institution
                // Factory will determine venue_id based on event_format
                $events = Event::factory()->count(10)->create($baseAttributes);

                // For physical/hybrid events that need a venue, assign this institution's venue
                foreach ($events as $event) {
                    if ($event->event_format !== \App\Enums\EventFormat::Online && ! $event->venue_id && $venue) {
                        $event->update(['venue_id' => $venue->id]);
                    }
                }

                // If there's a series, attach events to it via pivot table
                if ($series) {
                    $order = 1;
                    foreach ($events as $event) {
                        \Illuminate\Support\Facades\DB::table('event_series')->insert([
                            'id' => (string) \Illuminate\Support\Str::uuid(),
                            'event_id' => $event->id,
                            'series_id' => $series->id,
                            'order_column' => $order++,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }
                }

                // Prepare bulk relationship data
                $speakerAttachments = [];

                foreach ($events as $event) {
                    // Randomly select 1-3 speakers
                    if (! empty($speakerIds)) {
                        $numSpeakers = min(random_int(1, 3), count($speakerIds));
                        $selectedSpeakers = (array) array_rand(array_flip($speakerIds), $numSpeakers);
                        foreach ($selectedSpeakers as $speakerId) {
                            $speakerAttachments[] = [
                                'event_id' => $event->id,
                                'speaker_id' => $speakerId,
                            ];
                        }
                    }
                }

                // Bulk insert relationships
                if (! empty($speakerAttachments)) {
                    \Illuminate\Support\Facades\DB::table('event_speaker')->insert($speakerAttachments);
                }

                $count += 10;
            }
        });
    }

    private function seedMajlisIlmuSchedule(): void
    {
        $malaysia = \App\Models\Country::where('iso2', 'MY')->first();

        $institution = Institution::query()->firstOrCreate([
            'slug' => 'masjid-tengku-ampuan-jemaah-bukit-jelutong',
        ], [
            'type' => 'masjid',
            'name' => 'Masjid Tengku Ampuan Jemaah Bukit Jelutong',
            'description' => 'Jadual kuliah Januari 2026.',
            'status' => 'verified',
        ]);

        $institution->contacts()->firstOrCreate(
            ['category' => 'email'],
            ['value' => 'mtajbj@gmail.com', 'type' => 'work']
        );

        $institution->contacts()->firstOrCreate(
            ['category' => 'phone'],
            ['value' => '03-78313641', 'type' => 'work']
        );

        if (! $institution->address) {
            $institution->address()->create([
                'line1' => 'Bukit Jelutong',
                'lat' => 3.0991666,
                'lng' => 101.529892,
                'country_id' => $malaysia?->id,
            ]);

            $state = \App\Models\State::query()
                ->where('name', 'ILIKE', '%selangor%')
                ->first();

            $district = $state?->districts()
                ->where('name', 'ILIKE', '%petaling%')
                ->first();

            $city = $state?->cities()
                ->where('name', 'ILIKE', '%shah alam%')
                ->first();

            $institution->address()->update([
                'state_id' => $state?->id,
                'district_id' => $district?->id,
                'city_id' => $city?->id,
            ]);
        }

        $venue = \App\Models\Venue::query()->firstOrCreate([
            'slug' => 'dewan-solat-utama-mtaj',
        ], [
            'institution_id' => $institution->id,
            'name' => 'Dewan Solat Utama',
        ]);

        if (! $venue->address) {
            $venue->address()->create([
                'line1' => $institution->address?->line1,
                'country_id' => $institution->address?->country_id,
                'state_id' => $institution->address?->state_id,
                'district_id' => $institution->address?->district_id,
                'city_id' => $institution->address?->city_id,
            ]);
        }

        $schedule = [
            ['date' => '2026-01-05', 'slot' => 'Dhuha', 'time' => '10:30', 'speaker' => 'Ust Mukhlisur Riyadus', 'topic' => 'Adab Iman'],
            ['date' => '2026-01-05', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Mohd Aris Johari', 'topic' => 'Tafsir Juz Amma (Surah Jasim)'],
            ['date' => '2026-01-12', 'slot' => 'Dhuha', 'time' => '10:00', 'speaker' => 'Ust Muhd Zulkifli', 'topic' => 'Kitab Idaman Penuntut Ilmu'],
            ['date' => '2026-01-12', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Mohd Faiz al-Izzani', 'topic' => 'Tafsir Juz Amma'],
            ['date' => '2026-01-19', 'slot' => 'Dhuha', 'time' => '10:00', 'speaker' => 'Ust Mohd Nazri Abdul Razak', 'topic' => 'Berusrah Bersama'],
            ['date' => '2026-01-19', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Anuar Harun', 'topic' => 'Tafsir Juz Amma'],
            ['date' => '2026-01-26', 'slot' => 'Dhuha', 'time' => '10:00', 'speaker' => 'Ust Mohd Nazri Abdul Razak', 'topic' => 'Berusrah Bersama'],
            ['date' => '2026-01-26', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Fawwaz Mohd Nur', 'topic' => 'Tafsir Juz Amma'],

            ['date' => '2026-01-06', 'slot' => 'Quran Time', 'time' => '08:45', 'speaker' => 'Ust Adi Hamman Mahwi', 'topic' => 'Quran Time'],
            ['date' => '2026-01-06', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Dr Adnin Ramly'],
            ['date' => '2026-01-13', 'slot' => 'Dhuha', 'time' => '10:30', 'speaker' => 'Ust Abdul Khair Zaki'],
            ['date' => '2026-01-13', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Abu Hazim'],
            ['date' => '2026-01-20', 'slot' => 'Quran Time', 'time' => '08:45', 'speaker' => 'Ust Adi Hamman Mahwi', 'topic' => 'Quran Time'],
            ['date' => '2026-01-20', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Ebit Lew'],
            ['date' => '2026-01-27', 'slot' => 'Talaqqi al-Quran', 'time' => '10:30', 'speaker' => 'Ust Izani Zulkifli', 'topic' => 'Talaqqi al-Quran'],
            ['date' => '2026-01-27', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Dr Zulkifli Mohamad al-Bakri'],

            ['date' => '2026-01-07', 'slot' => 'Dhuha', 'time' => '10:00', 'speaker' => 'Ust Muhd Zulkifli', 'topic' => 'Kitab Idaman Penuntut Ilmu'],
            ['date' => '2026-01-07', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Dato Dr Danial Zainal Abidin'],
            ['date' => '2026-01-14', 'slot' => 'Dhuha', 'time' => '10:00', 'speaker' => 'Ust Muhamad Azmi', 'topic' => 'Kitab Nuru al-Iqna Masail Taharah'],
            ['date' => '2026-01-14', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Syed Mohd Shahabuddin'],
            ['date' => '2026-01-21', 'slot' => 'Dhuha', 'time' => '10:00', 'speaker' => 'Ust Muhamad Azmi'],
            ['date' => '2026-01-21', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Hj Zakaria Othman'],
            ['date' => '2026-01-28', 'slot' => 'Dhuha', 'time' => '10:00', 'speaker' => 'Ust Muhd Azmi'],
            ['date' => '2026-01-28', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Jamil Hashim'],

            ['date' => '2026-01-01', 'slot' => 'Maghrib', 'time' => '20:00', 'topic' => 'Bacaan Yasin & Tazkirah'],
            ['date' => '2026-01-08', 'slot' => 'Dhuha', 'time' => '10:30', 'speaker' => 'Ust Muhd Izudin Salem', 'topic' => 'Hadis Riyadus Solihin'],
            ['date' => '2026-01-08', 'slot' => 'Maghrib', 'time' => '20:00', 'topic' => 'Bacaan Yasin & Tazkirah'],
            ['date' => '2026-01-15', 'slot' => 'Dhuha', 'time' => '10:30', 'speaker' => 'Puan Farhana Abdul Ghani', 'topic' => 'Hikam ke-140'],
            ['date' => '2026-01-15', 'slot' => 'Maghrib', 'time' => '20:00', 'topic' => 'Bacaan Yasin & Tazkirah'],
            ['date' => '2026-01-22', 'slot' => 'Dhuha', 'time' => '10:30', 'speaker' => 'Dr Azman Shah Alias'],
            ['date' => '2026-01-22', 'slot' => 'Maghrib', 'time' => '20:00', 'topic' => 'Bacaan Yasin & Tazkirah'],
            ['date' => '2026-01-29', 'slot' => 'Dhuha', 'time' => '10:30', 'speaker' => 'Ust Anas Mohd'],
            ['date' => '2026-01-29', 'slot' => 'Maghrib', 'time' => '20:00', 'topic' => 'Bacaan Yasin & Tazkirah'],

            ['date' => '2026-01-02', 'slot' => 'Dhuha', 'time' => '10:00', 'note' => 'Ditangguhkan', 'topic' => 'Kuliah Dhuha'],
            ['date' => '2026-01-02', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Akid Shafie'],
            ['date' => '2026-01-09', 'slot' => 'Dhuha', 'time' => '10:00', 'speaker' => 'Ust Ahmad Fawwaz Zaidon'],
            ['date' => '2026-01-09', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Adnin Ramly'],
            ['date' => '2026-01-16', 'slot' => 'Dhuha', 'time' => '10:00', 'speaker' => 'Dato Dr Najmuddin'],
            ['date' => '2026-01-16', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Ahmad Saffwan'],
            ['date' => '2026-01-23', 'slot' => 'Dhuha', 'time' => '10:00', 'speaker' => 'Dato Dr Mohd Radzi'],
            ['date' => '2026-01-23', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Dato Dr Ahmad Zaki'],
            ['date' => '2026-01-30', 'slot' => 'Dhuha', 'time' => '10:00', 'speaker' => 'Dr Mizan Mohamed'],
            ['date' => '2026-01-30', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Jamil Hashim'],

            ['date' => '2026-01-03', 'slot' => 'Subuh', 'time' => '05:45', 'speaker' => 'Ust Roslan Mohamed'],
            ['date' => '2026-01-03', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Fahmi Ideris'],
            ['date' => '2026-01-10', 'slot' => 'Subuh', 'time' => '05:45', 'speaker' => 'Ust Radzi Shahari'],
            ['date' => '2026-01-10', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Ahmad Anwar'],
            ['date' => '2026-01-17', 'slot' => 'Subuh', 'time' => '05:45', 'speaker' => 'Ust Ahmad Rosli'],
            ['date' => '2026-01-17', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Mohd Sufyan'],
            ['date' => '2026-01-24', 'slot' => 'Subuh', 'time' => '05:45', 'speaker' => 'Ust Mohd Shah Rizal'],
            ['date' => '2026-01-24', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Dr Fathullah Kani'],
            ['date' => '2026-01-31', 'slot' => 'Maghrib', 'time' => '20:00', 'note' => 'Dibatalkan', 'topic' => 'Kuliah Maghrib'],

            ['date' => '2026-01-04', 'slot' => 'Subuh', 'time' => '05:45', 'speaker' => 'Mufti Wilayah Persekutuan'],
            ['date' => '2026-01-04', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Imam Muda Hassan'],
            ['date' => '2026-01-11', 'slot' => 'Subuh', 'time' => '05:45', 'speaker' => 'Dato Prof Dr Basri Ibrahim'],
            ['date' => '2026-01-11', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Ahmad Anwar'],
            ['date' => '2026-01-18', 'slot' => 'Subuh', 'time' => '05:45', 'speaker' => 'Dato Seri Zulkifli al-Bakri'],
            ['date' => '2026-01-18', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Ibrahim Zamzibar'],
            ['date' => '2026-01-25', 'slot' => 'Subuh', 'time' => '05:45', 'speaker' => 'Dato Dr Danial Zainal Abidin'],
            ['date' => '2026-01-25', 'slot' => 'Maghrib', 'time' => '20:00', 'speaker' => 'Ust Azhar Idrus'],
        ];

        foreach ($schedule as $entry) {
            $startsAt = \Illuminate\Support\Carbon::parse($entry['date'].' '.$entry['time'], 'Asia/Kuala_Lumpur');
            $endsAt = $startsAt->copy()->addMinutes(90);
            $topic = $entry['topic'] ?? null;
            $note = $entry['note'] ?? null;

            $title = $entry['slot'];
            if ($topic) {
                $title = $entry['slot'].': '.$topic;
            }
            if ($note) {
                $title .= ' - '.$note;
            }

            $descriptionParts = [];
            if ($topic) {
                $descriptionParts[] = $topic;
            }
            if (! empty($entry['speaker'])) {
                $descriptionParts[] = 'Bersama '.$entry['speaker'];
            }
            if ($note) {
                $descriptionParts[] = $note;
            }

            $slug = \Illuminate\Support\Str::slug($title.'-'.$entry['date']);

            // Determine timing mode
            $timingMode = TimingMode::Absolute->value;
            $prayerReference = null;
            $prayerOffset = null;
            $prayerDisplayText = null;

            $slotLower = strtolower($entry['slot']);
            if (str_contains($slotLower, 'maghrib')) {
                $timingMode = TimingMode::PrayerRelative->value;
                $prayerReference = PrayerReference::Maghrib->value;
                $prayerOffset = PrayerOffset::Immediately->value;
                $prayerDisplayText = 'Selepas Maghrib';
            } elseif (str_contains($slotLower, 'isyak') || str_contains($slotLower, 'isya')) {
                $timingMode = TimingMode::PrayerRelative->value;
                $prayerReference = PrayerReference::Isha->value;
                $prayerOffset = PrayerOffset::After15->value; // Usually Isyak lectures start a bit later
                $prayerDisplayText = '15 minit selepas Isyak';
            } elseif (str_contains($slotLower, 'subuh')) {
                $timingMode = TimingMode::PrayerRelative->value;
                $prayerReference = PrayerReference::Fajr->value;
                $prayerOffset = PrayerOffset::Immediately->value;
                $prayerDisplayText = 'Selepas Subuh';
            } elseif (str_contains($slotLower, 'zuhur') || str_contains($slotLower, 'zohor')) {
                $timingMode = TimingMode::PrayerRelative->value;
                $prayerReference = PrayerReference::Dhuhr->value;
                $prayerOffset = PrayerOffset::Immediately->value;
                $prayerDisplayText = 'Selepas Zohor';
            }

            $event = Event::query()->updateOrCreate([
                'slug' => $slug,
            ], [
                'institution_id' => $institution->id,
                'venue_id' => $venue->id,
                'title' => $title,
                'description' => $descriptionParts ? implode(' | ', $descriptionParts) : null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'timezone' => 'Asia/Kuala_Lumpur',
                // 'language' has been removed; genre/audience are now event_type/age_group etc
                'event_type' => \App\Enums\EventType::KuliahCeramah,
                'visibility' => 'public',
                'status' => 'approved',
                'published_at' => $startsAt->copy()->subDays(7),
                'timing_mode' => $timingMode,
                'prayer_reference' => $prayerReference,
                'prayer_offset' => $prayerOffset,
                'prayer_display_text' => $prayerDisplayText,
            ]);

            // Attach default language (Malay) if exists
            if (class_exists(\Nnjeim\World\Models\Language::class)) {
                $malay = \Nnjeim\World\Models\Language::where('code', 'ms')->first();
                if ($malay) {
                    $event->languages()->syncWithoutDetaching([$malay->id]);
                }
            }

            if (! empty($entry['speaker'])) {
                $speakerName = $entry['speaker'];
                $speaker = \App\Models\Speaker::query()->firstOrCreate([
                    'slug' => \Illuminate\Support\Str::slug($speakerName),
                ], [
                    'name' => $speakerName,
                    'status' => 'verified',
                ]);

                $event->speakers()->syncWithoutDetaching([
                    $speaker->id => ['order_column' => 1],
                ]);
            }

        }
    }
}
