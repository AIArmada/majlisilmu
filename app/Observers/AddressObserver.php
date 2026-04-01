<?php

namespace App\Observers;

use App\Actions\Institutions\GenerateInstitutionSlugAction;
use App\Actions\Speakers\GenerateSpeakerSlugAction;
use App\Actions\Venues\GenerateVenueSlugAction;
use App\Models\Address;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\Venue;
use App\Support\Cache\PublicListingsCache;

class AddressObserver
{
    public function __construct(
        protected GenerateInstitutionSlugAction $generateInstitutionSlugAction,
        protected GenerateSpeakerSlugAction $generateSpeakerSlugAction,
        protected GenerateVenueSlugAction $generateVenueSlugAction,
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

        $addressable = $address->addressable;

        if ($addressable instanceof Institution) {
            $this->generateInstitutionSlugAction->syncInstitutionSlugsForName($addressable->name);
            $this->publicListingsCache->bustMajlisListing();

            return;
        }

        if ($addressable instanceof Speaker) {
            $this->generateSpeakerSlugAction->syncSpeakerSlugsForName($addressable->name);
            $this->publicListingsCache->bustMajlisListing();

            return;
        }

        if ($addressable instanceof Venue) {
            $this->generateVenueSlugAction->syncVenueSlugsForName($addressable->name);
            $this->publicListingsCache->bustMajlisListing();
        }
    }
}
