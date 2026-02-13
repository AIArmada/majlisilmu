<?php

namespace App\Enums;

enum RegistrationMode: string
{
    case Event = 'event';
    case Session = 'session';

    public function label(): string
    {
        return match ($this) {
            self::Event => __('Whole Event'),
            self::Session => __('Per Session'),
        };
    }
}
