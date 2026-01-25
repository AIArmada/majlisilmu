<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Reference extends Model implements HasMedia
{
    /** @use HasFactory<\Database\Factories\ReferenceFactory> */
    use HasFactory, HasUuids, InteractsWithMedia, KeepsDeletedModels;

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

    public function topics(): BelongsToMany
    {
        return $this->belongsToMany(Topic::class, 'reference_topic');
    }

    /**
     * Register media collections for Spatie Media Library.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('cover')
            ->useDisk('public')
            ->singleFile();
    }
}
