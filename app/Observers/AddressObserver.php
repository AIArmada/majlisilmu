<?php

namespace App\Observers;

use App\Actions\Institutions\GenerateInstitutionSlugAction;
use App\Models\Address;
use App\Models\Institution;
use App\Support\Cache\PublicListingsCache;

class AddressObserver
{
    public function __construct(
        protected GenerateInstitutionSlugAction $generateInstitutionSlugAction,
        protected PublicListingsCache $publicListingsCache,
    ) {}

    public function saved(Address $address): void
    {
        $this->syncInstitutionSlug($address);
    }

    public function deleted(Address $address): void
    {
        $this->syncInstitutionSlug($address);
    }

    private function syncInstitutionSlug(Address $address): void
    {
        $address->loadMissing('addressable');

        $institution = $address->addressable;

        if (! $institution instanceof Institution) {
            return;
        }

        $this->generateInstitutionSlugAction->syncInstitutionSlugsForName($institution->name);
        $this->publicListingsCache->bustMajlisListing();
    }
}
