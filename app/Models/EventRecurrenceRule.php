<?php

namespace App\Models;

use App\Enums\RecurrenceFrequency;
use App\Enums\ScheduleState;
use App\Enums\TimingMode;
use App\Enums\PrayerOffset;
use App\Enums\PrayerReference;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EventRecurrenceRule extends Model
{
    /** @use HasFactory<\Database\Factories\EventRecurrenceRuleFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'frequency',
        'interval',
        'by_weekdays',
        'by_month_day',
        'start_date',
        'until_date',
        'occurrence_count',
        'starts_time',
        'ends_time',
        'timezone',
        'timing_mode',
        'prayer_reference',
        'prayer_offset',
        'prayer_display_text',
        'status',
        'generated_until',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'frequency' => RecurrenceFrequency::class,
            'interval' => 'integer',
            'by_weekdays' => 'array',
            'by_month_day' => 'integer',
            'start_date' => 'date',
            'until_date' => 'date',
            'occurrence_count' => 'integer',
            'starts_time' => 'datetime:H:i:s',
            'ends_time' => 'datetime:H:i:s',
            'timing_mode' => TimingMode::class,
            'prayer_reference' => PrayerReference::class,
            'prayer_offset' => PrayerOffset::class,
            'status' => ScheduleState::class,
            'generated_until' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<EventSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(EventSession::class, 'recurrence_rule_id')->orderBy('starts_at');
    }

    public function isActive(): bool
    {
        return $this->status === ScheduleState::Active;
    }
}
