<?php

namespace App\Observers;

use App\Actions\Events\GenerateEventSlugAction;
use App\Actions\Slugs\SyncSlugRedirectAction;
use App\Actions\Speakers\GenerateSpeakerSlugAction;
use App\Models\Speaker;
use App\Support\Cache\PublicListingsCache;
use App\Support\Search\SpeakerSearchService;

class SpeakerObserver
{
    public function __construct(
        protected GenerateEventSlugAction $generateEventSlugAction,
        protected GenerateSpeakerSlugAction $generateSpeakerSlugAction,
        protected SyncSlugRedirectAction $syncSlugRedirectAction,
        protected PublicListingsCache $publicListingsCache,
        protected SpeakerSearchService $speakerSearchService,
    ) {}

    public function saved(Speaker $speaker): void
    {
        $this->speakerSearchService->syncSpeakerRecord($speaker);

        if ($speaker->wasRecentlyCreated || $speaker->wasChanged(['name', 'honorific', 'pre_nominal', 'post_nominal'])) {
            $previousName = $speaker->wasChanged('name')
                ? trim((string) ($speaker->getPrevious()['name'] ?? ''))
                : null;

            $this->generateSpeakerSlugAction->syncSpeakerSlugsForName($speaker->name);
            $this->generateEventSlugAction->syncEventSlugsForSpeakerName($speaker->name);

            if (! in_array($previousName, [null, '', $speaker->name], true)) {
                $this->generateSpeakerSlugAction->syncSpeakerSlugsForName($previousName);
                $this->generateEventSlugAction->syncEventSlugsForSpeakerName($previousName);
            }
        }

        $this->publicListingsCache->bustMajlisListing();
    }

    public function deleted(Speaker $speaker): void
    {
        $this->syncSlugRedirectAction->purgeForModel($speaker);
        $this->generateSpeakerSlugAction->syncSpeakerSlugsForName($speaker->name);
        $this->generateEventSlugAction->syncEventSlugsForSpeakerId((string) $speaker->getKey());
        $this->generateEventSlugAction->syncEventSlugsForSpeakerName($speaker->name);
        $this->speakerSearchService->purgeSpeakerRecord($speaker);
        $this->publicListingsCache->bustMajlisListing();
    }
}
