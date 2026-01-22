<?php

namespace App\Enums;

enum TimingMode: string
{
    case Absolute = 'absolute';
    case PrayerRelative = 'prayer_relative';

    public function label(): string
    {
        return match ($this) {
            self::Absolute => 'Waktu Tertentu',
            self::PrayerRelative => 'Waktu Solat',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Absolute => 'Tetapkan waktu yang tepat (cth: 10:00 pagi)',
            self::PrayerRelative => 'Berkaitan dengan waktu solat (cth: selepas Maghrib)',
        };
    }
}
