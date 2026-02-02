<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\DeletedModels\Models\Concerns\KeepsDeletedModels;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Tags\HasTags;

class Topic extends Model implements HasMedia, Sortable
{
    /** @use HasFactory<\Database\Factories\TopicFactory> */
    use HasFactory, HasTags, HasUuids, InteractsWithMedia, KeepsDeletedModels, SortableTrait;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'is_official',
        'order_column',
        'status',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected static function booted(): void
    {
        static::deleting(function (Topic $topic) {
            $topic->children()->update(['parent_id' => null]);
        });
    }

    protected function casts(): array
    {
        return [
            'is_official' => 'boolean',
            'order_column' => 'integer',
        ];
    }

    // ─────────────────────────────────────────────────────────────
    // Relationships
    // ─────────────────────────────────────────────────────────────

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Topic::class, 'parent_id')->ordered();
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_topic')
            ->withPivot('order_column')
            ->withTimestamps()
            ->orderByPivot('order_column');
    }

    public function references(): BelongsToMany
    {
        return $this->belongsToMany(Reference::class, 'reference_topic');
    }

    // ─────────────────────────────────────────────────────────────
    // Scopes
    // ─────────────────────────────────────────────────────────────

    /**
     * Get only root-level topics (no parent).
     */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Check if this topic is a root topic.
     */
    public function isRoot(): bool
    {
        return $this->parent_id === null;
    }

    /**
     * Check if this topic has children.
     */
    public function hasChildren(): bool
    {
        return $this->children()->exists();
    }

    /**
     * Get all ancestors (parent, grandparent, etc.).
     *
     * @return Collection<int, Topic>
     */
    public function ancestors(): Collection
    {
        $ancestors = collect();
        $current = $this->parent;

        while ($current) {
            $ancestors->push($current);
            $current = $current->parent;
        }

        return $ancestors->reverse()->values();
    }

    /**
     * Get the full path as a string (e.g., "Fiqh > Ibadah > Solat").
     */
    public function getFullPath(string $separator = ' > '): string
    {
        return $this->ancestors()
            ->push($this)
            ->pluck('name')
            ->implode($separator);
    }

    /**
     * Get the depth level (0 = root, 1 = first child, etc.).
     */
    public function getDepth(): int
    {
        return $this->ancestors()->count();
    }

    /**
     * Register media collections for Spatie Media Library.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('icon')
            ->useDisk('public')
            ->singleFile();

        $this->addMediaCollection('banner')
            ->useDisk('public')
            ->singleFile();
    }

    /**
     * Get the default tag type for this model.
     */
    public static function getTagClassName(): string
    {
        return \App\Models\Tag::class;
    }

    /**
     * Sync islamic discipline tags (e.g., 'hadis', 'fiqah', 'sirah').
     *
     * @param  array<string>|string  $tags
     */
    public function syncIslamicTags(array|string $tags): static
    {
        return $this->syncTagsWithType($tags, 'islamic');
    }

    /**
     * Attach islamic discipline tags.
     *
     * @param  array<string>|string  $tags
     */
    public function attachIslamicTags(array|string $tags): static
    {
        return $this->attachTags($tags, 'islamic');
    }

    /**
     * Scope to filter topics by islamic discipline tag.
     */
    public function scopeWithIslamicTag(Builder $query, string $tag): Builder
    {
        return $query->withAnyTags([$tag], 'islamic');
    }
}
