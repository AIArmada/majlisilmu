<?php

namespace Database\Seeders;

use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventVisibility;
use App\Enums\PrayerOffset;
use App\Enums\PrayerReference;
use App\Enums\TagType;
use App\Enums\TimingMode;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class EventSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private array $tagIdMap = [];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Temporarily disable the EventObserver to speed up seeding
        Event::unsetEventDispatcher();

        try {
            $hadEvents = Event::query()->exists();

            $this->seedMajlisIlmuSchedule();

            if (! $hadEvents) {
                $this->seedBulkEvents();
            }

            $this->backfillSeededEventRequiredFields();
        } finally {
            // Re-enable event dispatcher after seeding
            Event::setEventDispatcher(app('events'));
        }
    }

    private function seedBulkEvents(): void
    {

        \Illuminate\Support\Facades\DB::transaction(function (): void {
            $institutions = Institution::query()
                ->limit(90)
                ->get();
            $seriesIds = \App\Models\Series::query()->pluck('id')->toArray();
            $speakerIds = Speaker::query()->pluck('id')->toArray();
            $venueIds = \App\Models\Venue::query()->pluck('id')->toArray();

            if ($institutions->isEmpty()) {
                return;
            }

            $count = 0;
            $limit = 850; // We already have ~50 from seedMajlisIlmuSchedule

            foreach ($institutions as $institution) {
                if ($count >= $limit) {
                    break;
                }

                $randomSeriesId = empty($seriesIds) ? null : $seriesIds[array_rand($seriesIds)];
                $randomVenueId = empty($venueIds) ? null : $venueIds[array_rand($venueIds)];

                $baseAttributes = [
                    'institution_id' => $institution->id,
                ];

                // Create 10 events per institution
                // Factory will determine venue_id based on event_format
                $events = Event::factory()->count(10)->create($baseAttributes);

                // For physical/hybrid events that need a venue, assign a random venue
                foreach ($events as $event) {
                    if ($event->event_format !== \App\Enums\EventFormat::Online && ! $event->venue_id && $randomVenueId) {
                        $event->update(['venue_id' => $randomVenueId]);
                    }
                }

                // If a series exists in the system, attach events via pivot table.
                if ($randomSeriesId) {
                    $order = 1;
                    foreach ($events as $event) {
                        \Illuminate\Support\Facades\DB::table('event_series')->insert([
                            'id' => (string) \Illuminate\Support\Str::uuid(),
                            'event_id' => $event->id,
                            'series_id' => $randomSeriesId,
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
                if ($speakerAttachments !== []) {
                    \Illuminate\Support\Facades\DB::table('event_speaker')->insert($speakerAttachments);
                }

                $count += 10;
            }
        });
    }

    private function seedMajlisIlmuSchedule(): void
    {
        $malaysia = \App\Models\Country::where('iso2', 'MY')->first();
        $likeOperator = $this->databaseLikeOperator();

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
                ->where('name', $likeOperator, '%selangor%')
                ->first();

            $district = $state?->districts()
                ->where('name', $likeOperator, '%petaling%')
                ->first();

            $city = $state?->cities()
                ->where('name', $likeOperator, '%shah alam%')
                ->first();

            $institution->address()->update([
                'state_id' => $state?->getKey(),
                'district_id' => $district?->getKey(),
                'city_id' => $city?->getKey(),
            ]);
        }

        $venue = \App\Models\Venue::query()->firstOrCreate([
            'slug' => 'dewan-solat-utama-mtaj',
        ], [
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
            if (isset($entry['speaker'])) {
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

            $speaker = $this->resolveScheduleSpeaker($entry['speaker'] ?? null);
            $organizerType = $speaker instanceof Speaker ? Speaker::class : Institution::class;
            $organizerId = $speaker?->getKey() ?? $institution->getKey();

            $event = Event::query()->updateOrCreate([
                'slug' => $slug,
            ], [
                'institution_id' => $institution->id,
                'venue_id' => $venue->id,
                'organizer_type' => $organizerType,
                'organizer_id' => $organizerId,
                'title' => $title,
                'description' => $descriptionParts !== [] ? implode(' | ', $descriptionParts) : null,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'timezone' => 'Asia/Kuala_Lumpur',
                // 'language' has been removed; genre/audience are now event_type/age_group etc
                'event_type' => \App\Enums\EventType::KuliahCeramah,
                'gender' => EventGenderRestriction::All,
                'age_group' => [EventAgeGroup::AllAges->value],
                'children_allowed' => true,
                'event_format' => EventFormat::Physical,
                'visibility' => EventVisibility::Public,
                'is_muslim_only' => false,
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
                    $event->languages()->syncWithoutDetaching([$malay->getKey()]);
                }
            }

            if ($speaker instanceof Speaker) {
                $event->speakers()->syncWithoutDetaching([
                    $speaker->id => ['order_column' => 1],
                ]);
            }

            $this->ensureScheduleEventHasTags($event, $title, $topic);

        }
    }

    private function resolveScheduleSpeaker(?string $speakerName): ?Speaker
    {
        if (! is_string($speakerName) || $speakerName === '') {
            return null;
        }

        return Speaker::query()->firstOrCreate([
            'slug' => \Illuminate\Support\Str::slug($speakerName),
        ], [
            'name' => $speakerName,
            'status' => 'verified',
            'is_active' => true,
        ]);
    }

    private function ensureScheduleEventHasTags(Event $event, string $title, ?string $topic): void
    {
        $existingTypes = $event->tags()
            ->pluck('type')
            ->filter(fn (mixed $type): bool => is_string($type) && $type !== '')
            ->unique()
            ->values();

        $hasRequiredTypes = $existingTypes->contains(TagType::Domain->value)
            && $existingTypes->contains(TagType::Discipline->value);

        if ($hasRequiredTypes) {
            return;
        }

        $this->hydrateTagIdMap();

        $selectedTagIds = $this->resolveSeedTagIds($title, $topic);

        if ($selectedTagIds === []) {
            return;
        }

        $combinedTagIds = $event->tags()
            ->pluck('id')
            ->merge($selectedTagIds)
            ->unique()
            ->values()
            ->all();

        $tags = Tag::query()->whereIn('id', $combinedTagIds)->get();

        if ($tags->isEmpty()) {
            return;
        }

        $event->syncTags($tags);
    }

    private function hydrateTagIdMap(): void
    {
        if ($this->tagIdMap !== []) {
            return;
        }

        Tag::query()
            ->whereIn('status', ['verified', 'pending'])
            ->get(['id', 'type', 'slug'])
            ->each(function (Tag $tag): void {
                $slug = $tag->slug;
                $normalizedSlug = null;

                if (is_string($slug)) {
                    $decodedSlug = json_decode($slug, true);
                    if (is_array($decodedSlug) && $decodedSlug !== []) {
                        $normalizedSlug = $decodedSlug['en'] ?? $decodedSlug['ms'] ?? null;
                    } else {
                        $normalizedSlug = $slug;
                    }
                }

                if (is_array($slug) && $slug !== []) {
                    $normalizedSlug = $slug['en'] ?? $slug['ms'] ?? null;
                }

                if (! is_string($normalizedSlug) || $normalizedSlug === '') {
                    return;
                }

                $key = $tag->type.':'.$normalizedSlug;
                $this->tagIdMap[$key] = (string) $tag->id;
            });
    }

    /**
     * @return list<string>
     */
    private function resolveSeedTagIds(string $title, ?string $topic): array
    {
        $haystack = mb_strtolower(trim($title.' '.($topic ?? '')));

        $domainSlug = 'syariah';
        $disciplineSlug = 'hadith_studies';
        $sourceSlug = 'hadith';
        $issueSlug = null;

        if (
            str_contains($haystack, 'tafsir') ||
            str_contains($haystack, 'quran') ||
            str_contains($haystack, 'qur\'an') ||
            str_contains($haystack, 'tadabbur')
        ) {
            $domainSlug = 'aqidah';
            $disciplineSlug = str_contains($haystack, 'tadabbur') ? 'tadabbur' : 'tafsir';
            $sourceSlug = 'quran';
        } elseif (
            str_contains($haystack, 'adab') ||
            str_contains($haystack, 'akhlak') ||
            str_contains($haystack, 'tazkiyah') ||
            str_contains($haystack, 'hikam')
        ) {
            $domainSlug = 'akhlak';
            $disciplineSlug = str_contains($haystack, 'tazkiyah') ? 'tazkiyah' : 'adab_akhlaq';
            $sourceSlug = 'turath';
        } elseif (str_contains($haystack, 'sirah')) {
            $domainSlug = 'aqidah';
            $disciplineSlug = 'sirah';
            $sourceSlug = 'hadith';
            $issueSlug = 'kepimpinan';
        } elseif (
            str_contains($haystack, 'fiqh') ||
            str_contains($haystack, 'solat') ||
            str_contains($haystack, 'zakat') ||
            str_contains($haystack, 'puasa')
        ) {
            $domainSlug = 'syariah';
            $disciplineSlug = 'ibadah';
            $sourceSlug = 'hadith';
        }

        if (str_contains($haystack, 'keluarga') || str_contains($haystack, 'keibubapaan')) {
            $issueSlug = 'keluarga';
        }

        $resolved = [
            $this->tagIdFor(TagType::Domain, $domainSlug) ?? $this->tagIdFor(TagType::Domain, 'syariah'),
            $this->tagIdFor(TagType::Discipline, $disciplineSlug) ?? $this->tagIdFor(TagType::Discipline, 'hadith_studies'),
            $this->tagIdFor(TagType::Source, $sourceSlug) ?? $this->tagIdFor(TagType::Source, 'hadith'),
            $issueSlug ? $this->tagIdFor(TagType::Issue, $issueSlug) : null,
        ];

        /** @var Collection<int, string> $uniqueIds */
        $uniqueIds = collect($resolved)
            ->filter(fn (?string $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values();

        return $uniqueIds->all();
    }

    private function tagIdFor(TagType $type, string $slug): ?string
    {
        return $this->tagIdMap[$type->value.':'.$slug] ?? null;
    }

    private function databaseLikeOperator(): string
    {
        return \Illuminate\Support\Facades\DB::connection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
    }

    private function backfillSeededEventRequiredFields(): void
    {
        $defaultDomainTagId = Tag::query()
            ->where('type', TagType::Domain->value)
            ->whereIn('status', ['verified', 'pending'])
            ->orderBy('order_column')
            ->value('id');

        $defaultDisciplineTagId = Tag::query()
            ->where('type', TagType::Discipline->value)
            ->whereIn('status', ['verified', 'pending'])
            ->orderBy('order_column')
            ->value('id');

        $defaultSourceTagId = Tag::query()
            ->where('type', TagType::Source->value)
            ->whereIn('status', ['verified', 'pending'])
            ->orderBy('order_column')
            ->value('id');

        Event::query()
            ->whereNull('submitter_id')
            ->whereNull('user_id')
            ->with([
                'speakers:id',
                'tags:id,type',
            ])
            ->chunk(200, function (Collection $events) use (
                $defaultDomainTagId,
                $defaultDisciplineTagId,
                $defaultSourceTagId
            ): void {
                foreach ($events as $event) {
                    $updates = [];

                    $ageGroup = $event->age_group;
                    $hasAgeGroup = $ageGroup instanceof Collection && $ageGroup->isNotEmpty();

                    if (! $hasAgeGroup) {
                        $updates['age_group'] = [EventAgeGroup::AllAges->value];
                    }

                    if (empty($event->organizer_type) || empty($event->organizer_id)) {
                        $firstSpeakerId = $event->speakers->first()?->getKey();

                        if (is_string($firstSpeakerId) && $firstSpeakerId !== '') {
                            $updates['organizer_type'] = Speaker::class;
                            $updates['organizer_id'] = $firstSpeakerId;
                        } elseif (! empty($event->institution_id)) {
                            $updates['organizer_type'] = Institution::class;
                            $updates['organizer_id'] = $event->institution_id;
                        }
                    }

                    if ($updates !== []) {
                        $event->fill($updates)->save();
                    }

                    $tagTypes = $event->tags
                        ->pluck('type')
                        ->filter(fn (mixed $type): bool => is_string($type) && $type !== '')
                        ->unique()
                        ->values();

                    $hasRequiredTagTypes = $tagTypes->contains(TagType::Domain->value)
                        && $tagTypes->contains(TagType::Discipline->value);

                    if ($hasRequiredTagTypes) {
                        continue;
                    }

                    $defaultIds = array_filter([
                        $defaultDomainTagId,
                        $defaultDisciplineTagId,
                        $defaultSourceTagId,
                    ]);

                    if ($defaultIds === []) {
                        continue;
                    }

                    /** @var list<string> $existingTagIds */
                    $existingTagIds = $event->tags->pluck('id')->map(fn (mixed $id): string => (string) $id)->all();

                    /** @var list<string> $finalTagIds */
                    $finalTagIds = array_values(array_unique(array_merge($existingTagIds, $defaultIds)));

                    $tags = Tag::query()->whereIn('id', $finalTagIds)->get();

                    if ($tags->isNotEmpty()) {
                        $event->syncTags($tags);
                    }
                }
            });
    }
}
