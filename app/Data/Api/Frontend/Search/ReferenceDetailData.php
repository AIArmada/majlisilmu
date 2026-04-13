<?php

namespace App\Data\Api\Frontend\Search;

use App\Models\Reference;
use App\Models\User;
use Spatie\LaravelData\Data;

class ReferenceDetailData extends Data
{
    /**
     * @param  array{front_cover_url: string, back_cover_url: string}  $media
     * @param  list<array<string, mixed>>  $social_media
     */
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public ?string $author,
        public ?string $type,
        public ?string $publisher,
        public int|string|null $publication_year,
        public ?string $description,
        public bool $is_active,
        public bool $is_following,
        public array $media,
        public array $social_media,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $socialMedia
     */
    public static function fromModel(Reference $reference, ?User $user, array $socialMedia): self
    {
        return new self(
            id: (string) $reference->id,
            slug: (string) $reference->slug,
            title: (string) $reference->title,
            author: $reference->author,
            type: $reference->type,
            publisher: $reference->publisher,
            publication_year: $reference->publication_year,
            description: $reference->description,
            is_active: (bool) $reference->is_active,
            is_following: $user?->isFollowing($reference) ?? false,
            media: ReferenceDetailMediaData::fromModel($reference)->toArray(),
            social_media: $socialMedia,
        );
    }
}
