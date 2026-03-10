<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DawahShareAttribution extends Model
{
    /** @use HasFactory<\Database\Factories\DawahShareAttributionFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'link_id',
        'user_id',
        'visitor_key',
        'cookie_value',
        'landing_url',
        'referrer_url',
        'ip_address',
        'user_agent',
        'signed_up_user_id',
        'metadata',
        'first_seen_at',
        'last_seen_at',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<DawahShareLink, $this>
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(DawahShareLink::class, 'link_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function signedUpUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signed_up_user_id');
    }

    /**
     * @return HasMany<DawahShareVisit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(DawahShareVisit::class, 'attribution_id');
    }

    /**
     * @return HasMany<DawahShareOutcome, $this>
     */
    public function outcomes(): HasMany
    {
        return $this->hasMany(DawahShareOutcome::class, 'attribution_id');
    }
}
