<?php

namespace App\Models;

use App\Enums\SessionStatus;
use App\Enums\TimingMode;
use App\Enums\PrayerOffset;
use App\Enums\PrayerReference;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSession extends Model
{
    /** @use HasFactory<\Database\Factories\EventSessionFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'recurrence_rule_id',
        'starts_at',
        'ends_at',
        'timezone',
        'status',
        'is_generated',
        'capacity',
        'timing_mode',
        'prayer_reference',
        'prayer_offset',
        'prayer_display_text',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => SessionStatus::class,
            'is_generated' => 'boolean',
            'capacity' => 'integer',
            'timing_mode' => TimingMode::class,
            'prayer_reference' => PrayerReference::class,
            'prayer_offset' => PrayerOffset::class,
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
     * @return BelongsTo<EventRecurrenceRule, $this>
     */
    public function recurrenceRule(): BelongsTo
    {
        return $this->belongsTo(EventRecurrenceRule::class, 'recurrence_rule_id');
    }

    public function isActiveForPublic(): bool
    {
        return $this->status === SessionStatus::Scheduled;
    }
}
