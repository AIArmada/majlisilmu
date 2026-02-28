<?php

namespace App\Observers;

use App\Models\Institution;
use App\Support\Cache\PublicListingsCache;

class InstitutionObserver
{
    public function __construct(
        protected PublicListingsCache $publicListingsCache
    ) {}

    public function saved(Institution $institution): void
    {
        $this->publicListingsCache->bustMajlisListing();
    }

    public function deleted(Institution $institution): void
    {
        $this->publicListingsCache->bustMajlisListing();
    }
}
