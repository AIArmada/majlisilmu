<?php

declare(strict_types=1);

namespace App\Support\ApiDocumentation\Schemas;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Contracts\Support\Arrayable;

#[SchemaName('SpeakerListItem')]
final readonly class SpeakerListItem implements Arrayable
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public string $formatted_name,
        public int $events_count,
        public string $avatar_url,
        public ?Country $country,
        public bool $is_following,
    ) {}

    /**
     * @return array{id: string, slug: string, name: string, formatted_name: string, events_count: int, avatar_url: string, country: array{id: int, name: string, iso2: string, key: ?string}|null, is_following: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'formatted_name' => $this->formatted_name,
            'events_count' => $this->events_count,
            'avatar_url' => $this->avatar_url,
            'country' => $this->country?->toArray(),
            'is_following' => $this->is_following,
        ];
    }
}
