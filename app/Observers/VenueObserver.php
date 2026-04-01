<?php

namespace App\Observers;

use App\Actions\Slugs\SyncSlugRedirectAction;
use App\Actions\Venues\GenerateVenueSlugAction;
use App\Models\Venue;
use App\Support\Cache\PublicListingsCache;

class VenueObserver
{
    public function __construct(
        protected GenerateVenueSlugAction $generateVenueSlugAction,
        protected SyncSlugRedirectAction $syncSlugRedirectAction,
        protected PublicListingsCache $publicListingsCache
    ) {}

    public function saved(Venue $venue): void
    {
        if ($venue->wasRecentlyCreated || $venue->wasChanged('name')) {
            $previousName = $venue->wasChanged('name')
                ? trim((string) ($venue->getPrevious()['name'] ?? ''))
                : null;

            $this->generateVenueSlugAction->syncVenueSlugsForName($venue->name);

            if ($previousName !== null && $previousName !== '' && $previousName !== $venue->name) {
                $this->generateVenueSlugAction->syncVenueSlugsForName($previousName);
            }
        }

        $this->publicListingsCache->bustMajlisListing();
    }

    public function deleted(Venue $venue): void
    {
        $this->syncSlugRedirectAction->purgeForModel($venue);
        $this->generateVenueSlugAction->syncVenueSlugsForName($venue->name);
        $this->publicListingsCache->bustMajlisListing();
    }
}
