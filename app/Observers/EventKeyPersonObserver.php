<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\EventKeyPerson;
use App\Support\Cache\PublicDirectoryCacheVersion;

class EventKeyPersonObserver
{
    public function __construct(
        protected PublicDirectoryCacheVersion $publicDirectoryCacheVersion,
    ) {}

    public function saved(EventKeyPerson $eventKeyPerson): void
    {
        $this->publicDirectoryCacheVersion->bumpForEventKeyPerson($eventKeyPerson);
    }

    public function deleted(EventKeyPerson $eventKeyPerson): void
    {
        $this->publicDirectoryCacheVersion->bumpForEventKeyPerson($eventKeyPerson);
    }
}
