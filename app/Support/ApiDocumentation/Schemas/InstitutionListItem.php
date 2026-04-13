<?php

declare(strict_types=1);

namespace App\Support\ApiDocumentation\Schemas;

use Dedoc\Scramble\Attributes\SchemaName;
use Illuminate\Contracts\Support\Arrayable;

#[SchemaName('InstitutionListItem')]
final readonly class InstitutionListItem implements Arrayable
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $name,
        public ?string $nickname,
        public string $display_name,
        public int $events_count,
        public int $event_count,
        public string $public_image_url,
        public string $image_url,
        public string $logo_url,
        public ?string $cover_url,
        public ?Country $country,
        public ?string $location,
        public ?string $location_text,
    ) {}

    /**
     * @return array{id: string, slug: string, name: string, nickname: ?string, display_name: string, events_count: int, event_count: int, public_image_url: string, image_url: string, logo_url: string, cover_url: ?string, country: array{id: int, name: string, iso2: string, key: ?string}|null, location: ?string, location_text: ?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'nickname' => $this->nickname,
            'display_name' => $this->display_name,
            'events_count' => $this->events_count,
            'event_count' => $this->event_count,
            'public_image_url' => $this->public_image_url,
            'image_url' => $this->image_url,
            'logo_url' => $this->logo_url,
            'cover_url' => $this->cover_url,
            'country' => $this->country?->toArray(),
            'location' => $this->location,
            'location_text' => $this->location_text,
        ];
    }
}
