<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Tags\Tag as SpatieTag;

class Tag extends SpatieTag
{
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::deleting(function (Tag $tag) {
            \Illuminate\Support\Facades\DB::table('taggables')->where('tag_id', $tag->id)->delete();
        });
    }
}
