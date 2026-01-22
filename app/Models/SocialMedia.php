<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SocialMedia extends Model
{
    use HasUuids;

    protected $fillable = [
        'socialable_type',
        'socialable_id',
        'platform',
        'url',
        'username',
    ];

    public function socialable(): MorphTo
    {
        return $this->morphTo();
    }
}
