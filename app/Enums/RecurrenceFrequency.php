<?php

namespace App\Enums;

enum RecurrenceFrequency: string
{
    case Daily = 'daily';
    case Weekly = 'weekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Daily => __('Daily'),
            self::Weekly => __('Weekly'),
            self::Monthly => __('Monthly'),
        };
    }
}
