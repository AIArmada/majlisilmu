<?php

namespace Database\Seeders;

use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\PrayerOffset;
use App\Enums\PrayerReference;
use App\Enums\RecurrenceFrequency;
use App\Enums\ScheduleKind;
use App\Enums\ScheduleState;
use App\Enums\SessionStatus;
use App\Enums\TimingMode;
use App\Models\Event;
use App\Models\EventRecurrenceRule;
use App\Models\EventSession;
use App\Models\Institution;
use App\Models\Speaker;
use App\Services\EventScheduleGeneratorService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdvancedEventSeeder extends Seeder
{
    /**
     * Seed advanced events: recurring, multi-day, and custom-chain.
     */
    public function run(EventScheduleGeneratorService $generator): void
    {
        $institution = Institution::query()
            ->where('status', 'verified')
            ->inRandomOrder()
            ->first();

        $speakerIds = Speaker::query()
            ->where('status', 'verified')
            ->inRandomOrder()
            ->limit(6)
            ->pluck('id')
            ->all();

        // Suppress moderation side-effects during seeding
        Event::unsetEventDispatcher();

        try {
            $this->seedRecurringWeekly($institution, $speakerIds, $generator);
            $this->seedRecurringPrayerRelative($institution, $speakerIds, $generator);
            $this->seedMultiDay($institution, $speakerIds, $generator);
            $this->seedCustomChain($institution, $speakerIds, $generator);
            $this->seedRecurringMonthly($institution, $speakerIds, $generator);
        } finally {
            Event::setEventDispatcher(app('events'));
        }

        $this->command->info('  [AdvancedEventSeeder] Seeded 5 advanced events (recurring × 3, multi-day × 1, custom chain × 1).');
    }

    // ──────────────────────────────────────────────────────────────
    // 1. RECURRING — Weekly (every Friday night, 3 months forward)
    // ──────────────────────────────────────────────────────────────
    private function seedRecurringWeekly(
        ?Institution $institution,
        array $speakerIds,
        EventScheduleGeneratorService $generator,
    ): void {
        $tz = 'Asia/Kuala_Lumpur';
        $startDate = now($tz)->next(Carbon::FRIDAY)->toDateString();
        $untilDate = now($tz)->addMonths(3)->toDateString();

        $event = $this->makeBaseEvent(
            title: 'Kelas / Daurah: Al-Arba\'in An-Nawawiyyah',
            description: 'Kelas mingguan membincangkan 40 hadis Imam Nawawi secara mendalam, setiap malam Jumaat selepas Isyak.',
            scheduleKind: ScheduleKind::Recurring,
            institution: $institution,
            speakerIds: $speakerIds,
            tz: $tz,
        );

        $rule = EventRecurrenceRule::query()->create([
            'event_id' => $event->id,
            'frequency' => RecurrenceFrequency::Weekly,
            'interval' => 1,
            'by_weekdays' => [Carbon::FRIDAY],       // 0=Sun … 5=Fri
            'by_month_day' => null,
            'start_date' => $startDate,
            'until_date' => $untilDate,
            'occurrence_count' => null,
            'starts_time' => '21:00:00',
            'ends_time' => '22:30:00',
            'timezone' => $tz,
            'timing_mode' => TimingMode::Absolute,
            'status' => ScheduleState::Active,
        ]);

        EventSession::withoutEvents(fn () => $generator->syncRecurringSessions($event, $rule, false));
    }

    // ──────────────────────────────────────────────────────────────
    // 2. RECURRING — Prayer-relative (after Maghrib, every Sunday)
    // ──────────────────────────────────────────────────────────────
    private function seedRecurringPrayerRelative(
        ?Institution $institution,
        array $speakerIds,
        EventScheduleGeneratorService $generator,
    ): void {
        $tz = 'Asia/Kuala_Lumpur';
        $startDate = now($tz)->next(Carbon::SUNDAY)->toDateString();
        $untilDate = now($tz)->addMonths(2)->toDateString();

        $event = $this->makeBaseEvent(
            title: 'Tadabbur: Sirah Nabawiyyah — Siri Mingguan',
            description: 'Tadabbur sirah Nabi ﷺ setiap Ahad selepas Maghrib. Sesuai untuk seluruh keluarga.',
            scheduleKind: ScheduleKind::Recurring,
            institution: $institution,
            speakerIds: $speakerIds,
            tz: $tz,
            prayerReference: PrayerReference::Maghrib,
            prayerOffset: PrayerOffset::Immediately,
            prayerDisplayText: 'Selepas Maghrib',
        );

        $rule = EventRecurrenceRule::query()->create([
            'event_id' => $event->id,
            'frequency' => RecurrenceFrequency::Weekly,
            'interval' => 1,
            'by_weekdays' => [Carbon::SUNDAY],
            'by_month_day' => null,
            'start_date' => $startDate,
            'until_date' => $untilDate,
            'occurrence_count' => null,
            'starts_time' => null,
            'ends_time' => null,
            'timezone' => $tz,
            'timing_mode' => TimingMode::PrayerRelative,
            'prayer_reference' => PrayerReference::Maghrib,
            'prayer_offset' => PrayerOffset::Immediately,
            'prayer_display_text' => 'Selepas Maghrib',
            'status' => ScheduleState::Active,
        ]);

        EventSession::withoutEvents(fn () => $generator->syncRecurringSessions($event, $rule, false));
    }

    // ──────────────────────────────────────────────────────────────
    // 3. RECURRING — Monthly (last Saturday of each month)
    // ──────────────────────────────────────────────────────────────
    private function seedRecurringMonthly(
        ?Institution $institution,
        array $speakerIds,
        EventScheduleGeneratorService $generator,
    ): void {
        $tz = 'Asia/Kuala_Lumpur';
        // Find the next/first Saturday of this month as a start anchor
        $nextSat = now($tz)->next(Carbon::SATURDAY);
        $startDate = $nextSat->toDateString();
        $untilDate = now($tz)->addMonths(6)->toDateString();

        $event = $this->makeBaseEvent(
            title: 'Halaqah Bulanan: Fiqh Semasa',
            description: 'Halaqah bulanan membincangkan isu-isu fiqh semasa dalam kehidupan moden. Setiap Sabtu pertama setiap bulan.',
            scheduleKind: ScheduleKind::Recurring,
            institution: $institution,
            speakerIds: $speakerIds,
            tz: $tz,
        );

        $rule = EventRecurrenceRule::query()->create([
            'event_id' => $event->id,
            'frequency' => RecurrenceFrequency::Monthly,
            'interval' => 1,
            'by_weekdays' => null,
            'by_month_day' => (int) $nextSat->format('d'),   // e.g. the 7th
            'start_date' => $startDate,
            'until_date' => $untilDate,
            'occurrence_count' => null,
            'starts_time' => '09:00:00',
            'ends_time' => '11:00:00',
            'timezone' => $tz,
            'timing_mode' => TimingMode::Absolute,
            'status' => ScheduleState::Active,
        ]);

        EventSession::withoutEvents(fn () => $generator->syncRecurringSessions($event, $rule, false));
    }

    // ──────────────────────────────────────────────────────────────
    // 4. MULTI-DAY — 3-day daurah (consecutive days)
    // ──────────────────────────────────────────────────────────────
    private function seedMultiDay(
        ?Institution $institution,
        array $speakerIds,
        EventScheduleGeneratorService $generator,
    ): void {
        $tz = 'Asia/Kuala_Lumpur';
        // Schedule it 2 weeks from now on a Friday
        $friday = now($tz)->next(Carbon::FRIDAY)->addWeek();

        $event = $this->makeBaseEvent(
            title: 'Daurah Ilmiah: Bulughul Maram — 3 Hari',
            description: 'Daurah intensif 3 hari membahas kitab Bulughul Maram. Pagi dan petang setiap hari. Terbuka untuk semua peringkat.',
            scheduleKind: ScheduleKind::MultiDay,
            institution: $institution,
            speakerIds: $speakerIds,
            tz: $tz,
        );

        // Morning + evening session for each of the 3 days
        $sessions = [
            // Friday
            [$friday->copy()->setTime(9, 0), $friday->copy()->setTime(12, 0)],
            [$friday->copy()->setTime(14, 0), $friday->copy()->setTime(17, 0)],
            // Saturday
            [$friday->copy()->addDay()->setTime(9, 0), $friday->copy()->addDay()->setTime(12, 0)],
            [$friday->copy()->addDay()->setTime(14, 0), $friday->copy()->addDay()->setTime(17, 0)],
            // Sunday
            [$friday->copy()->addDays(2)->setTime(9, 0), $friday->copy()->addDays(2)->setTime(12, 0)],
            [$friday->copy()->addDays(2)->setTime(14, 0), $friday->copy()->addDays(2)->setTime(16, 0)],
        ];

        foreach ($sessions as [$startsAt, $endsAt]) {
            $generator->upsertManualSession($event, [
                'starts_at' => $startsAt->utc(),
                'ends_at' => $endsAt->utc(),
                'timezone' => $tz,
                'status' => SessionStatus::Scheduled->value,
                'timing_mode' => TimingMode::Absolute->value,
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // 5. CUSTOM CHAIN — Irregular ad-hoc sessions (e.g. a seminar
    //    spread across non-consecutive weekends)
    // ──────────────────────────────────────────────────────────────
    private function seedCustomChain(
        ?Institution $institution,
        array $speakerIds,
        EventScheduleGeneratorService $generator,
    ): void {
        $tz = 'Asia/Kuala_Lumpur';
        $base = now($tz)->next(Carbon::SATURDAY)->addWeek();

        $event = $this->makeBaseEvent(
            title: 'Siri Seminar: Aqidah Ahli Sunnah Wal Jamaah',
            description: 'Siri seminar 4 sesi merentasi beberapa minggu, mendalami asas aqidah Ahli Sunnah Wal Jamaah.',
            scheduleKind: ScheduleKind::CustomChain,
            institution: $institution,
            speakerIds: $speakerIds,
            tz: $tz,
        );

        $sessions = [
            // Sesi 1 — Saturday week 1
            [$base->copy()->setTime(9, 0), $base->copy()->setTime(13, 0)],
            // Sesi 2 — Saturday week 3
            [$base->copy()->addWeeks(2)->setTime(9, 0), $base->copy()->addWeeks(2)->setTime(13, 0)],
            // Sesi 3 — Saturday week 5
            [$base->copy()->addWeeks(4)->setTime(9, 0), $base->copy()->addWeeks(4)->setTime(13, 0)],
            // Sesi 4 — Sunday week 6 (final session on a different day)
            [$base->copy()->addWeeks(5)->next(Carbon::SUNDAY)->setTime(9, 0), $base->copy()->addWeeks(5)->next(Carbon::SUNDAY)->setTime(12, 0)],
        ];

        foreach ($sessions as [$startsAt, $endsAt]) {
            $generator->upsertManualSession($event, [
                'starts_at' => $startsAt->utc(),
                'ends_at' => $endsAt->utc(),
                'timezone' => $tz,
                'status' => SessionStatus::Scheduled->value,
                'timing_mode' => TimingMode::Absolute->value,
            ]);
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Create the base Event row that all schedule types build on top of.
     */
    private function makeBaseEvent(
        string $title,
        string $description,
        ScheduleKind $scheduleKind,
        ?Institution $institution,
        array $speakerIds,
        string $tz,
        ?PrayerReference $prayerReference = null,
        ?PrayerOffset $prayerOffset = null,
        ?string $prayerDisplayText = null,
    ): Event {
        $timingMode = ($prayerReference !== null)
            ? TimingMode::PrayerRelative
            : TimingMode::Absolute;

        $event = Event::query()->create([
            'user_id' => null,
            'submitter_id' => null,
            'institution_id' => $institution?->id,
            'venue_id' => null,
            'organizer_type' => $institution !== null ? Institution::class : null,
            'organizer_id' => $institution?->id,
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'description' => $description,
            'starts_at' => now($tz)->utc(),
            'ends_at' => now($tz)->addHours(2)->utc(),
            'timezone' => $tz,
            'event_type' => [EventType::KuliahCeramah],
            'event_format' => EventFormat::Physical,
            'visibility' => EventVisibility::Public,
            'gender' => EventGenderRestriction::All,
            'age_group' => [EventAgeGroup::AllAges->value],
            'children_allowed' => true,
            'is_muslim_only' => false,
            'status' => 'approved',
            'published_at' => now()->subDay(),
            'schedule_kind' => $scheduleKind->value,
            'schedule_state' => ScheduleState::Active->value,
            'is_active' => true,
            'timing_mode' => $timingMode->value,
            'prayer_reference' => $prayerReference?->value,
            'prayer_offset' => $prayerOffset?->value,
            'prayer_display_text' => $prayerDisplayText,
        ]);

        if (! empty($speakerIds)) {
            $selected = array_slice($speakerIds, 0, random_int(1, min(3, count($speakerIds))));
            $attach = [];
            foreach ($selected as $i => $speakerId) {
                $attach[$speakerId] = ['order_column' => $i + 1];
            }
            $event->speakers()->syncWithoutDetaching($attach);
        }

        return $event;
    }
}
