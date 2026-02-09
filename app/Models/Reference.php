<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Reference extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\ReferenceFactory> */
    use HasFactory, HasUuids, InteractsWithMedia, KeepsDeletedModels;

    protected static function booted(): void
    {
        //
    }

    protected $fillable = [
        'title',
        'author',
        'type',
        'publication_year',
        'publisher',
        'description',
        'external_link',
        'is_canonical',
    ];

    protected function casts(): array
    {
        return [
            'is_canonical' => 'boolean',
        ];
    }

    /**
     * Register media collections for Spatie Media Library.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->useDisk(config('media-library.disk_name'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->withResponsiveImages()
            ->singleFile();
    }

    /**
     * Register media conversions for optimized image delivery.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(200)
            ->height(280)
            ->sharpen(10)
            ->format('webp')
            ->performOnCollections('cover');
    }
}
