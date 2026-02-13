<?php

use App\Enums\RecurrenceFrequency;
use App\Enums\ScheduleState;
use App\Enums\TimingMode;
use App\Models\Event;
use App\Models\EventRecurrenceRule;
use App\Models\EventSession;
use App\Services\EventScheduleGeneratorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('preserves recurrence rule linkage when updating generated sessions manually', function () {
    $event = Event::factory()->create([
        'timezone' => 'Asia/Kuala_Lumpur',
    ]);

    $rule = EventRecurrenceRule::factory()->create([
        'event_id' => $event->id,
        'status' => ScheduleState::Paused->value,
    ]);

    $session = EventSession::factory()->create([
        'event_id' => $event->id,
        'recurrence_rule_id' => $rule->id,
        'is_generated' => true,
    ]);

    $generator = app(EventScheduleGeneratorService::class);

    $generator->upsertManualSession($event, [
        'starts_at' => Carbon::parse('2026-02-20 20:00:00', 'Asia/Kuala_Lumpur'),
        'ends_at' => Carbon::parse('2026-02-20 22:00:00', 'Asia/Kuala_Lumpur'),
        'status' => 'scheduled',
        'timezone' => 'Asia/Kuala_Lumpur',
        'timing_mode' => 'absolute',
    ], $session);

    $session->refresh();

    expect($session->recurrence_rule_id)->toBe($rule->id)
        ->and($session->is_generated)->toBeTrue();
});

it('uses generated_until to append recurring sessions without regenerating existing ones', function () {
    Carbon::setTestNow(Carbon::parse('2026-02-12 00:00:00', 'Asia/Kuala_Lumpur'));

    $event = Event::factory()->create([
        'timezone' => 'Asia/Kuala_Lumpur',
        'schedule_kind' => 'recurring',
    ]);

    $rule = EventRecurrenceRule::query()->create([
        'event_id' => $event->id,
        'frequency' => RecurrenceFrequency::Daily->value,
        'interval' => 1,
        'start_date' => '2026-02-13',
        'occurrence_count' => 5,
        'starts_time' => '20:00:00',
        'ends_time' => '22:00:00',
        'timezone' => 'Asia/Kuala_Lumpur',
        'timing_mode' => TimingMode::Absolute->value,
        'status' => ScheduleState::Paused->value,
    ]);

    $generator = app(EventScheduleGeneratorService::class);

    $rule->status = ScheduleState::Active;
    $firstBatch = $generator->syncRecurringSessions($event->fresh(), $rule, false);

    $rule->refresh();
    $rule->occurrence_count = 7;
    $rule->save();

    $secondBatch = $generator->syncRecurringSessions($event->fresh(), $rule->fresh(), false);

    expect($firstBatch)->toBe(5)
        ->and($secondBatch)->toBe(2)
        ->and($event->sessions()->count())->toBe(7);

    Carbon::setTestNow();
});

