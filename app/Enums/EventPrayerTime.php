<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EventPrayerTime: string implements HasLabel
{
    case SelepasSubuh = 'selepas_subuh';
    case SelepasZuhur = 'selepas_zuhur';
    case SelepasJumaat = 'selepas_jumaat';
    case SelepasAsar = 'selepas_asar';
    case SelepasMaghrib = 'selepas_maghrib';
    case SelepasIsyak = 'selepas_isyak';
    case SelepasTarawikh = 'selepas_tarawikh';
    case LainWaktu = 'lain_waktu';

    public function getLabel(): string
    {
        return match ($this) {
            self::SelepasSubuh => 'Selepas Subuh',
            self::SelepasZuhur => 'Selepas Zuhur',
            self::SelepasJumaat => 'Selepas Jumaat',
            self::SelepasAsar => 'Selepas Asar',
            self::SelepasMaghrib => 'Selepas Maghrib',
            self::SelepasIsyak => 'Selepas Isyak',
            self::SelepasTarawikh => 'Selepas Tarawikh',
            self::LainWaktu => 'Lain Waktu',
        };
    }

    /**
     * Check if this is a custom time selection.
     */
    public function isCustomTime(): bool
    {
        return $this === self::LainWaktu;
    }

    /**
     * Get the corresponding PrayerReference for this timing.
     */
    public function toPrayerReference(): ?PrayerReference
    {
        return match ($this) {
            self::SelepasSubuh => PrayerReference::Fajr,
            self::SelepasZuhur => PrayerReference::Dhuhr,
            self::SelepasJumaat => PrayerReference::FridayPrayer,
            self::SelepasAsar => PrayerReference::Asr,
            self::SelepasMaghrib => PrayerReference::Maghrib,
            self::SelepasIsyak => PrayerReference::Isha,
            self::SelepasTarawikh => PrayerReference::Isha,
            self::LainWaktu => null,
        };
    }

    /**
     * Get the default PrayerOffset for this timing.
     */
    public function getDefaultOffset(): ?PrayerOffset
    {
        if ($this === self::LainWaktu) {
            return null;
        }

        if ($this === self::SelepasTarawikh) {
            return PrayerOffset::After60;
        }

        return PrayerOffset::Immediately;
    }
}
