<?php

namespace App\Observers;

use App\Actions\References\GenerateReferenceSlugAction;
use App\Actions\Slugs\SyncSlugRedirectAction;
use App\Models\Reference;

class ReferenceObserver
{
    public function __construct(
        protected GenerateReferenceSlugAction $generateReferenceSlugAction,
        protected SyncSlugRedirectAction $syncSlugRedirectAction,
    ) {}

    public function updated(Reference $reference): void
    {
        if (! $reference->wasChanged('title')) {
            return;
        }

        $previousTitle = trim((string) ($reference->getPrevious()['title'] ?? ''));

        $this->generateReferenceSlugAction->syncReferenceSlugsForTitle($reference->title);

        if ($previousTitle !== '' && $previousTitle !== $reference->title) {
            $this->generateReferenceSlugAction->syncReferenceSlugsForTitle($previousTitle);
        }
    }

    public function deleted(Reference $reference): void
    {
        $this->syncSlugRedirectAction->purgeForModel($reference);
        $this->generateReferenceSlugAction->syncReferenceSlugsForTitle($reference->title);
    }
}
