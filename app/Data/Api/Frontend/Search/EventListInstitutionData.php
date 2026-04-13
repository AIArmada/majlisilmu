<?php

namespace App\Data\Api\Frontend\Search;

use App\Models\Institution;
use Spatie\LaravelData\Data;

class EventListInstitutionData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public string $display_name,
        public string $public_image_url,
        public string $logo_url,
    ) {}

    public static function fromModel(Institution $institution): self
    {
        return new self(
            id: (string) $institution->id,
            name: (string) $institution->name,
            slug: (string) $institution->slug,
            display_name: (string) $institution->display_name,
            public_image_url: (string) $institution->public_image_url,
            logo_url: $institution->getFirstMediaUrl('logo', 'thumb') ?: $institution->getFirstMediaUrl('logo'),
        );
    }
}
