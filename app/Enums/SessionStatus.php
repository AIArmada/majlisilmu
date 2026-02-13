<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Scheduled = 'scheduled';
    case Paused = 'paused';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => __('Scheduled'),
            self::Paused => __('Paused'),
            self::Cancelled => __('Cancelled'),
        };
    }
}
