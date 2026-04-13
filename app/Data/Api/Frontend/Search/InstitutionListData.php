<?php

namespace App\Data\Api\Frontend\Search;

use App\Models\Institution;
use App\Support\Location\AddressHierarchyFormatter;
use Spatie\LaravelData\Data;

class InstitutionListData extends Data
{
    /**
     * @param  array{id: int, name: string, iso2: string, key: ?string}|null  $country
     */
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
        public ?array $country,
        public ?string $location,
        public ?string $location_text,
    ) {}

    public static function fromModel(Institution $institution): self
    {
        $eventsCount = (int) ($institution->events_count ?? 0);
        $publicImageUrl = (string) $institution->public_image_url;
        $logoUrl = (string) $institution->public_logo_url;
        $coverUrl = (string) $institution->public_cover_url;
        $logoFallbackUrl = $institution->getFallbackMediaUrl('logo', 'thumb');
        $resolvedLogoUrl = $logoUrl !== ''
            ? $logoUrl
            : ($logoFallbackUrl !== '' ? $logoFallbackUrl : $publicImageUrl);
        $location = AddressHierarchyFormatter::format($institution->address);

        return new self(
            id: (string) $institution->id,
            slug: (string) $institution->slug,
            name: (string) $institution->name,
            nickname: $institution->nickname,
            display_name: (string) $institution->display_name,
            events_count: $eventsCount,
            event_count: $eventsCount,
            public_image_url: $publicImageUrl,
            image_url: $publicImageUrl,
            logo_url: $resolvedLogoUrl,
            cover_url: $coverUrl !== '' ? $coverUrl : null,
            country: CountryData::fromAddress($institution->address)?->toArray(),
            location: $location !== '' ? $location : null,
            location_text: $location !== '' ? $location : null,
        );
    }
}
