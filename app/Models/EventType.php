<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;

class EventType extends Model implements Sortable
{
    /** @use HasFactory<\Database\Factories\EventTypeFactory> */
    use HasFactory, HasUuids, SortableTrait;

    protected $fillable = [
        'parent_id',
        'name',
        'slug',
        'order_column',
        'is_active',
        'status',
    ];

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected static function booted(): void
    {
        static::deleting(function (EventType $eventType) {
            $eventType->children()->update(['parent_id' => null]);
            $eventType->events()->update(['event_type_id' => null]);
        });
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'order_column' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeParents(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function scopeChildren(Builder $query): Builder
    {
        return $query->whereNotNull('parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(EventType::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(EventType::class, 'parent_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'event_type_id');
    }

    /**
     * Get the default event type (Kuliah).
     */
    public static function getDefault(): ?self
    {
        return Cache::remember('event_type_default', 3600, function () {
            return static::where('slug', 'kuliah')->first();
        });
    }

    /**
     * Get event types grouped by parent category for dropdowns.
     *
     * @return array<string, array<string, string>>
     */
    public static function getGroupedOptions(): array
    {
        return Cache::remember('event_types_grouped', 3600, function () {
            $categories = static::query()
                ->active()
                ->parents()
                ->with(['children' => fn ($q) => $q->active()->ordered()])
                ->ordered()
                ->get();

            $grouped = [];
            foreach ($categories as $category) {
                if ($category->children->isEmpty()) {
                    continue;
                }
                $grouped[$category->name] = $category->children
                    ->pluck('name', 'slug')
                    ->toArray();
            }

            return $grouped;
        });
    }

    /**
     * Get flat list of active child event types for simple dropdowns.
     *
     * @return array<string, string>
     */
    public static function getOptions(): array
    {
        return Cache::remember('event_types_options', 3600, function () {
            return static::query()
                ->active()
                ->children()
                ->ordered()
                ->pluck('name', 'id')
                ->toArray();
        });
    }
}
