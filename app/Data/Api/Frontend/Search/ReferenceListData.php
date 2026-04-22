<?php

namespace App\Data\Api\Frontend\Search;

use App\Models\Reference;
use App\Models\User;
use Spatie\LaravelData\Data;

class ReferenceListData extends Data
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public ?string $author,
        public ?string $type,
        public ?string $publisher,
        public ?string $publication_year,
        public bool $is_active,
        public int $events_count,
        public ?string $front_cover_url,
        public bool $is_following,
    ) {}

    public static function fromModel(Reference $reference, ?User $user = null): self
    {
        $attributes = $reference->getAttributes();
        $isFollowing = array_key_exists('is_following', $attributes)
            ? (bool) $attributes['is_following']
            : ($user?->isFollowing($reference) ?? false);

        return new self(
            id: (string) $reference->id,
            slug: (string) $reference->slug,
            title: (string) $reference->title,
            author: $reference->author,
            type: $reference->type,
            publisher: $reference->publisher,
            publication_year: filled($reference->publication_year) ? (string) $reference->publication_year : null,
            is_active: (bool) $reference->is_active,
            events_count: (int) ($reference->events_count ?? 0),
            front_cover_url: $reference->getFirstMediaUrl('front_cover', 'thumb') ?: ($reference->getFirstMediaUrl('front_cover') ?: null),
            is_following: $isFollowing,
        );
    }
}
