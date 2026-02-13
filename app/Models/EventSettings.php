<?php

namespace App\Models;

use App\Enums\RegistrationMode;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventSettings extends Model
{
    /** @use HasFactory<\Database\Factories\EventSettingsFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'event_id',
        'registration_required',
        'capacity',
        'registration_opens_at',
        'registration_closes_at',
        'registration_mode',
        'requires_approval',
        'allow_waitlist',
        'max_per_user',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'registration_required' => 'boolean',
            'capacity' => 'integer',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'registration_mode' => RegistrationMode::class,
            'requires_approval' => 'boolean',
            'allow_waitlist' => 'boolean',
            'max_per_user' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
