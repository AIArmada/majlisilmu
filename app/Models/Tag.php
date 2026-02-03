<?php

namespace App\Models;

use App\Enums\TagType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\Tags\Tag as SpatieTag;

class Tag extends SpatieTag implements Sortable
{
    use HasFactory, HasUuids, SortableTrait;

    public $incrementing = false;

    protected $keyType = 'string';

    public array $sortable = [
        'order_column_name' => 'order_column',
        'sort_when_creating' => true,
    ];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'slug' => 'array',
            'order_column' => 'integer',
        ];
    }

    /**
     * Get the type as an enum instance.
     */
    public function getTypeEnumAttribute(): ?TagType
    {
        return $this->type ? TagType::from($this->type) : null;
    }

    /**
     * Build the sort query scoped by type.
     */
    public function buildSortQuery(): Builder
    {
        $query = static::query();

        if ($this->type) {
            $query->where('type', $this->type);
        }

        return $query;
    }

    /**
     * Scope to filter by tag type.
     */
    public function scopeOfType(Builder $query, TagType|string $type): Builder
    {
        $value = $type instanceof TagType ? $type->value : $type;

        return $query->where('type', $value);
    }
}
