<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NotificationEndpoint extends Model
{
    /** @use HasFactory<\Database\Factories\NotificationEndpointFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'owner_type',
        'owner_id',
        'channel',
        'address',
        'external_id',
        'status',
        'is_primary',
        'verified_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'channel' => NotificationChannel::class,
            'is_primary' => 'boolean',
            'verified_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }
}
