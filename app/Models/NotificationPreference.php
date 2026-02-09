<?php

namespace App\Models;

use App\Enums\NotificationFrequency;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationPreference extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationPreferenceFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_type',
        'owner_id',
        'notification_key',
        'enabled',
        'frequency',
        'channels',
        'quiet_hours_start',
        'quiet_hours_end',
        'timezone',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'frequency' => NotificationFrequency::class,
            'channels' => 'array',
            'meta' => 'array',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
