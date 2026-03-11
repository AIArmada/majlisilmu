<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\Pivot;

class EventParticipantPivot extends Pivot
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'event_participants';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'id',
        'event_id',
        'speaker_id',
        'role',
        'name',
        'order_column',
        'is_public',
        'notes',
    ];
}
