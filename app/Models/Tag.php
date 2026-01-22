<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Tags\Tag as SpatieTag;

class Tag extends SpatieTag
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';
}
