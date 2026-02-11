<?php

namespace App\Enums;

enum EventFormat: string
{
    case Physical = 'physical';
    case Online = 'online';
    case Hybrid = 'hybrid';

    public function label(): string
    {
        return match ($this) {
            self::Physical => __('Physical'),
            self::Online => __('Online'),
            self::Hybrid => __('Hybrid'),
        };
    }
}
