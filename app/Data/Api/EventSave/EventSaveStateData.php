<?php

namespace App\Data\Api\EventSave;

use Spatie\LaravelData\Data;

class EventSaveStateData extends Data
{
    public function __construct(
        public bool $is_saved,
    ) {}

    public static function fromState(bool $isSaved): self
    {
        return new self(
            is_saved: $isSaved,
        );
    }
}
