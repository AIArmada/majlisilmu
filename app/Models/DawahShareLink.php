<?php

namespace App\Models;

use Database\Factories\DawahShareLinkFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DawahShareLink extends Model
{
    /** @use HasFactory<DawahShareLinkFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'subject_type',
        'subject_id',
        'subject_key',
        'destination_url',
        'canonical_url',
        'share_token',
        'title_snapshot',
        'metadata',
        'last_shared_at',
    ];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_shared_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<DawahShareAttribution, $this>
     */
    public function attributions(): HasMany
    {
        return $this->hasMany(DawahShareAttribution::class, 'link_id');
    }

    /**
     * @return HasMany<DawahShareVisit, $this>
     */
    public function visits(): HasMany
    {
        return $this->hasMany(DawahShareVisit::class, 'link_id');
    }

    /**
     * @return HasMany<DawahShareOutcome, $this>
     */
    public function outcomes(): HasMany
    {
        return $this->hasMany(DawahShareOutcome::class, 'link_id');
    }

    /**
     * @return HasMany<DawahShareShareEvent, $this>
     */
    public function shareEvents(): HasMany
    {
        return $this->hasMany(DawahShareShareEvent::class, 'link_id');
    }
}
