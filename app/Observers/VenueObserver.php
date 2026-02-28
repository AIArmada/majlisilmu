<?php

namespace App\Observers;

use App\Models\Venue;
use App\Support\Cache\PublicListingsCache;

class VenueObserver
{
    public function __construct(
        protected PublicListingsCache $publicListingsCache
    ) {}

    public function saved(Venue $venue): void
    {
        $this->publicListingsCache->bustMajlisListing();
    }

    public function deleted(Venue $venue): void
    {
        $this->publicListingsCache->bustMajlisListing();
    }
}
