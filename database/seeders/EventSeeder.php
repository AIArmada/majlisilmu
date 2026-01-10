<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\Topic;
use Illuminate\Database\Seeder;

class EventSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $hadEvents = Event::query()->exists();

        $this->seedMajlisIlmuSchedule();

        if ($hadEvents) {
            return;
        }

        $institutions = Institution::query()
            ->with(['venues', 'donationAccounts', 'series'])
            ->get();
        $speakers = Speaker::query()->get();
        $topics = Topic::query()->get();

        if ($institutions->isEmpty()) {
            return;
        }

        $institutions->each(function (Institution $institution) use ($speakers, $topics): void {
            $venue = $institution->venues->isNotEmpty() ? $institution->venues->random() : null;
            $donationAccount = $institution->donationAccounts->isNotEmpty()
                ? $institution->donationAccounts->random()
                : null;
            $series = $institution->series->isNotEmpty() ? $institution->series->random() : null;

            $events = Event::factory()
                ->count(4)
                ->create([
                    'institution_id' => $institution->id,
                    'venue_id' => $venue?->id,
                    'series_id' => $series?->id,
                    'donation_account_id' => $donationAccount?->id,
                    'state_id' => $institution->state_id,
                    'district_id' => $institution->district_id,
                ]);

            $events->each(function (Event $event) use ($speakers, $topics): void {
                if ($speakers->isNotEmpty()) {
                    $speakerCount = min(3, $speakers->count());
                    $event->speakers()->attach(
                        $speakers->random(random_int(1, $speakerCount))->pluck('id')->all()
                    );
                }

                if ($topics->isNotEmpty()) {
                    $topicCount = min(3, $topics->count());
                    $event->topics()->attach(
                        $topics->random(random_int(1, $topicCount))->pluck('id')->all()
                    );
                }
            });
        });
    }

    private function seedMajlisIlmuSchedule(): void
    {
        $institution = Institution::query()->firstOrCreate([
            'slug' => 'masjid-tengku-ampuan-jemaah-bukit-jelutong',
        ], [
            'type' => 'masjid',
            'name' => 'Masjid Tengku Ampuan Jemaah Bukit Jelutong',
            'description' => 'Jadual kuliah Januari 2026.',
            'phone' => '03-78313641',
            'email' => 'mtajbj@gmail.com',
            'website_url' => 'https://mtajbj.gov.my',
            'address_line1' => 'Bukit Jelutong',
            'city' => 'Shah Alam',
        ]);

        if (! $institution->state_id) {
            $state = \App\Models\State::query()
                ->where('name', 'ILIKE', '%selangor%')
                ->first();

            $district = $state?->districts()
                ->where('name', 'ILIKE', '%petaling%')
                ->first();

            $institution->update([
                'state_id' => $state?->id,
                'district_id' => $district?->id,
            ]);
        }

        $venue = \App\Models\Venue::query()->firstOrCreate([
            'slug' => 'dewan-solat-utama-mtaj',
        ], [
            'institution_id' => $institution->id,
            'name' => 'Dewan Solat Utama',
            'state_id' => $institution->state_id,
            'district_id' => $institution->district_id,
            'address_line1' => $institution->address_line1,
            'city' => $institution->city,
        ]);

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
                'language' => 'Bahasa Melayu',
                'genre' => $entry['slot'],
                'audience' => 'Umum',
                'visibility' => 'public',
                'status' => 'approved',
                'published_at' => $startsAt->copy()->subDays(7),
            ]);

            if (! empty($entry['speaker'])) {
                $speakerName = $entry['speaker'];
                $speaker = \App\Models\Speaker::query()->firstOrCreate([
                    'slug' => \Illuminate\Support\Str::slug($speakerName),
                ], [
                    'name' => $speakerName,
                    'verification_status' => 'verified',
                    'trust_score' => 90,
                ]);

                $event->speakers()->syncWithoutDetaching([
                    $speaker->id => ['sort_order' => 1],
                ]);
            }

            if ($topic && ! $note) {
                $category = null;
                $topicLower = mb_strtolower($topic);
                if (str_contains($topicLower, 'tafsir') || str_contains($topicLower, 'juz') || str_contains($topicLower, 'quran')) {
                    $category = 'quran';
                } elseif (str_contains($topicLower, 'hadis') || str_contains($topicLower, 'hadith')) {
                    $category = 'hadith';
                } elseif (str_contains($topicLower, 'fiqh')) {
                    $category = 'fiqh';
                } elseif (str_contains($topicLower, 'sirah')) {
                    $category = 'sirah';
                } elseif (str_contains($topicLower, 'adab') || str_contains($topicLower, 'akhlak')) {
                    $category = 'akhlak';
                }

                $topicModel = \App\Models\Topic::query()->firstOrCreate([
                    'slug' => \Illuminate\Support\Str::slug($topic),
                ], [
                    'name' => $topic,
                    'category' => $category,
                    'is_official' => false,
                ]);

                $event->topics()->syncWithoutDetaching([$topicModel->id]);
            }
        }
    }
}
