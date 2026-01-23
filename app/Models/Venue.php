<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Venue extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\VenueFactory> */
    use \App\Models\Concerns\HasAddress, \App\Models\Concerns\HasContacts, \App\Models\Concerns\HasSocialMedia, HasFactory, HasUuids, InteractsWithMedia, KeepsDeletedModels;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'institution_id',
        'name',
        'type',
        'slug',
        'facilities',
    ];

    protected function casts(): array
    {
        return [
            'facilities' => 'array',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function series(): HasMany
    {
        return $this->hasMany(Series::class);
    }
}
