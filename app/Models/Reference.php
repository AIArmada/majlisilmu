<?php

namespace App\Models;

use App\Models\Concerns\HasSocialMedia;
use Illuminate\Database\Eloquent\Builder;
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
    use HasFactory, HasSocialMedia, HasUuids, InteractsWithMedia, KeepsDeletedModels;

    #[\Override]
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
        'is_canonical',
        'status',
        'is_active',
    ];

    #[\Override]
    protected function casts(): array
    {
        return [
            'is_canonical' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Scope a query to only include active references.
     */
    #[\Illuminate\Database\Eloquent\Attributes\Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Register media collections for Spatie Media Library.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('front_cover')
            ->useDisk(config('media-library.disk_name'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->withResponsiveImages()
            ->singleFile();

        $this->addMediaCollection('back_cover')
            ->useDisk(config('media-library.disk_name'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->withResponsiveImages()
            ->singleFile();

        $this->addMediaCollection('gallery')
            ->useDisk(config('media-library.disk_name'))
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->withResponsiveImages();
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
            ->performOnCollections('front_cover', 'back_cover');

        $this->addMediaConversion('gallery_thumb')
            ->width(368)
            ->height(232)
            ->sharpen(10)
            ->format('webp')
            ->performOnCollections('gallery');
    }
}
