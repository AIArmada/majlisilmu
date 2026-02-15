<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EventPrayerTime: string implements HasLabel
{
    case SelepasSubuh = 'selepas_subuh';
    case SelepasZuhur = 'selepas_zuhur';
    case SebelumJumaat = 'sebelum_jumaat';
    case SelepasJumaat = 'selepas_jumaat';
    case SelepasAsar = 'selepas_asar';
    case SebelumMaghrib = 'sebelum_maghrib';
    case SelepasMaghrib = 'selepas_maghrib';
    case SelepasIsyak = 'selepas_isyak';
    case SelepasTarawih = 'selepas_tarawih';
    case LainWaktu = 'lain_waktu';

    public function getLabel(): string
    {
        return match ($this) {
            self::SelepasSubuh => __('Selepas Subuh'),
            self::SelepasZuhur => __('Selepas Zuhur'),
            self::SebelumJumaat => __('Sebelum Jumaat'),
            self::SelepasJumaat => __('Selepas Jumaat'),
            self::SelepasAsar => __('Selepas Asar'),
            self::SebelumMaghrib => __('Sebelum Maghrib'),
            self::SelepasMaghrib => __('Selepas Maghrib'),
            self::SelepasIsyak => __('Selepas Isyak'),
            self::SelepasTarawih => __('Selepas Tarawih'),
            self::LainWaktu => __('Lain Waktu'),
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
            self::SebelumJumaat => PrayerReference::FridayPrayer,
            self::SelepasJumaat => PrayerReference::FridayPrayer,
            self::SelepasAsar => PrayerReference::Asr,
            self::SebelumMaghrib => PrayerReference::Maghrib,
            self::SelepasMaghrib => PrayerReference::Maghrib,
            self::SelepasIsyak => PrayerReference::Isha,
            self::SelepasTarawih => PrayerReference::Isha,
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

        if ($this === self::SelepasTarawih) {
            return PrayerOffset::After60;
        }

        if ($this === self::SebelumJumaat) {
            return PrayerOffset::Before15;
        }

        if ($this === self::SebelumMaghrib) {
            return PrayerOffset::Before15;
        }

        return PrayerOffset::Immediately;
    }
}
