<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DawahShareOutcome extends Model
{
    /** @use HasFactory<\Database\Factories\DawahShareOutcomeFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'link_id',
        'attribution_id',
        'sharer_user_id',
        'actor_user_id',
        'outcome_type',
        'subject_type',
        'subject_id',
        'subject_key',
        'outcome_key',
        'metadata',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    #[\Override]
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'occurred_at' => 'datetime',
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
     * @return BelongsTo<DawahShareAttribution, $this>
     */
    public function attribution(): BelongsTo
    {
        return $this->belongsTo(DawahShareAttribution::class, 'attribution_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sharer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sharer_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
