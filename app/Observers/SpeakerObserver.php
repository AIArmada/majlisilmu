<?php

namespace App\Observers;

use App\Actions\Slugs\SyncSlugRedirectAction;
use App\Actions\Speakers\GenerateSpeakerSlugAction;
use App\Models\Speaker;
use App\Support\Cache\PublicListingsCache;

class SpeakerObserver
{
    public function __construct(
        protected GenerateSpeakerSlugAction $generateSpeakerSlugAction,
        protected SyncSlugRedirectAction $syncSlugRedirectAction,
        protected PublicListingsCache $publicListingsCache
    ) {}

    public function saved(Speaker $speaker): void
    {
        if ($speaker->wasRecentlyCreated || $speaker->wasChanged(['name', 'honorific', 'pre_nominal', 'post_nominal'])) {
            $previousName = $speaker->wasChanged('name')
                ? trim((string) ($speaker->getPrevious()['name'] ?? ''))
                : null;

            $this->generateSpeakerSlugAction->syncSpeakerSlugsForName($speaker->name);

            if ($previousName !== null && $previousName !== '' && $previousName !== $speaker->name) {
                $this->generateSpeakerSlugAction->syncSpeakerSlugsForName($previousName);
            }
        }

        $this->publicListingsCache->bustMajlisListing();
    }

    public function deleted(Speaker $speaker): void
    {
        $this->syncSlugRedirectAction->purgeForModel($speaker);
        $this->generateSpeakerSlugAction->syncSpeakerSlugsForName($speaker->name);
        $this->publicListingsCache->bustMajlisListing();
    }
}
