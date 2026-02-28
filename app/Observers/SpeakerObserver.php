<?php

namespace App\Observers;

use App\Models\Speaker;
use App\Support\Cache\PublicListingsCache;

class SpeakerObserver
{
    public function __construct(
        protected PublicListingsCache $publicListingsCache
    ) {}

    public function saved(Speaker $speaker): void
    {
        $this->publicListingsCache->bustMajlisListing();
    }

    public function deleted(Speaker $speaker): void
    {
        $this->publicListingsCache->bustMajlisListing();
    }
}
