<?php

namespace App\Observers;

use App\Actions\Institutions\GenerateInstitutionSlugAction;
use App\Actions\Slugs\SyncSlugRedirectAction;
use App\Models\Institution;
use App\Support\Cache\PublicDirectoryCacheVersion;
use App\Support\Cache\PublicListingsCache;
use App\Support\Search\InstitutionSearchService;

class InstitutionObserver
{
    public function __construct(
        protected GenerateInstitutionSlugAction $generateInstitutionSlugAction,
        protected SyncSlugRedirectAction $syncSlugRedirectAction,
        protected PublicDirectoryCacheVersion $publicDirectoryCacheVersion,
        protected PublicListingsCache $publicListingsCache,
        protected InstitutionSearchService $institutionSearchService,
    ) {}

    public function saved(Institution $institution): void
    {
        $previousName = $institution->wasChanged('name')
            ? trim((string) ($institution->getPrevious()['name'] ?? ''))
            : null;

        $this->generateInstitutionSlugAction->syncInstitutionSlugsForName($institution->name);

        if (! in_array($previousName, [null, '', $institution->name], true)) {
            $this->generateInstitutionSlugAction->syncInstitutionSlugsForName($previousName);
        }

        $this->publicListingsCache->bustMajlisListing();
        $this->publicDirectoryCacheVersion->bumpInstitution();
        $this->institutionSearchService->bustPublicSearchCache();
    }

    public function deleted(Institution $institution): void
    {
        $this->syncSlugRedirectAction->purgeForModel($institution);
        $this->generateInstitutionSlugAction->syncInstitutionSlugsForName($institution->name);
        $this->publicListingsCache->bustMajlisListing();
        $this->publicDirectoryCacheVersion->bumpInstitution();
        $this->institutionSearchService->bustPublicSearchCache();
    }
}
