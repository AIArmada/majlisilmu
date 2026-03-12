<?php

namespace App\Models;

use Database\Factories\MediaLinkFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MediaLink extends Model
{
    /** @use HasFactory<MediaLinkFactory> */
    use HasFactory, HasUuids;

    protected $table = 'media_links';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'mediable_type',
        'mediable_id',
        'type',
        'provider',
        'url',
        'is_primary',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function mediable(): MorphTo
    {
        return $this->morphTo();
    }
}
