<?php

namespace App\Enums;

enum ScheduleKind: string
{
    case Single = 'single';
    case MultiDay = 'multi_day';
    case Recurring = 'recurring';
    case CustomChain = 'custom_chain';

    public function label(): string
    {
        return match ($this) {
            self::Single => __('Single Day'),
            self::MultiDay => __('Multi-day'),
            self::Recurring => __('Recurring'),
            self::CustomChain => __('Custom Chain'),
        };
    }
}
