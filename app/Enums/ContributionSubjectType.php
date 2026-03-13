<?php

namespace App\Enums;

use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;

enum ContributionSubjectType: string
{
    case Event = 'event';
    case Institution = 'institution';
    case Speaker = 'speaker';
    case Reference = 'reference';

    /**
     * @return class-string<Event|Institution|Speaker|Reference>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Event => Event::class,
            self::Institution => Institution::class,
            self::Speaker => Speaker::class,
            self::Reference => Reference::class,
        };
    }
}
