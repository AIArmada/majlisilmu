<?php

namespace App\Models;

use Database\Factories\DawahShareVisitFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DawahShareVisit extends Model
{
    /** @use HasFactory<DawahShareVisitFactory> */
    use HasFactory, HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'link_id',
        'attribution_id',
        'visitor_key',
        'visited_url',
        'subject_type',
        'subject_id',
        'subject_key',
        'visit_kind',
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
}
