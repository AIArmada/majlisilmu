<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Series extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\SeriesFactory> */
    use \App\Models\Concerns\HasLanguages, HasFactory, HasUuids, InteractsWithMedia;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'institution_id',
        'speaker_id',
        'title',
        'slug',
        'description',
        'visibility',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function speaker(): BelongsTo
    {
        return $this->belongsTo(Speaker::class);
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_series')
            ->withPivot('order_column')
            ->withTimestamps()
            ->orderByPivot('order_column');
    }

    /**
     * Register media collections for Spatie Media Library.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->useDisk('public')
            ->singleFile();

        $this->addMediaCollection('gallery')
            ->useDisk('public');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
