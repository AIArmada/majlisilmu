<?php

namespace App\Observers;

use App\Actions\Institutions\GenerateInstitutionSlugAction;
use App\Actions\Slugs\SyncSlugRedirectAction;
use App\Models\Institution;
use App\Support\Cache\PublicListingsCache;

class InstitutionObserver
{
    public function __construct(
        protected GenerateInstitutionSlugAction $generateInstitutionSlugAction,
        protected SyncSlugRedirectAction $syncSlugRedirectAction,
        protected PublicListingsCache $publicListingsCache
    ) {}

    public function saved(Institution $institution): void
    {
        $previousName = $institution->wasChanged('name')
            ? trim((string) ($institution->getPrevious()['name'] ?? ''))
            : null;

        $this->generateInstitutionSlugAction->syncInstitutionSlugsForName($institution->name);

        if ($previousName !== null && $previousName !== '' && $previousName !== $institution->name) {
            $this->generateInstitutionSlugAction->syncInstitutionSlugsForName($previousName);
        }

        $this->publicListingsCache->bustMajlisListing();
    }

    public function deleted(Institution $institution): void
    {
        $this->syncSlugRedirectAction->purgeForModel($institution);
        $this->generateInstitutionSlugAction->syncInstitutionSlugsForName($institution->name);
        $this->publicListingsCache->bustMajlisListing();
    }
}
