<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InstitutionType: string implements HasLabel
{
    case Masjid = 'masjid';
    case Surau = 'surau';
    case Madrasah = 'madrasah';
    case Maahad = 'maahad';
    case Pondok = 'pondok';
    case Sekolah = 'sekolah';
    case Kolej = 'kolej';
    case Universiti = 'universiti';
    case Pertubuhan = 'pertubuhan';
    case Yayasan = 'yayasan';
    case Persatuan = 'persatuan';
    case Kelab = 'kelab';
    case Usrah = 'usrah';
    case Perniagaan = 'perniagaan';
    case Syarikat = 'syarikat';
    case Koperasi = 'koperasi';
    case Hotel = 'hotel';

    public function getLabel(): string
    {
        return match ($this) {
            self::Masjid => __('Masjid'),
            self::Surau => __('Surau'),
            self::Madrasah => __('Madrasah'),
            self::Maahad => __('Maahad'),
            self::Pondok => __('Pondok'),
            self::SekolahAgama => __('Sekolah Agama'),
            self::KolejIslam => __('Kolej Islam'),
            self::Universiti => __('Universiti'),
            self::PertubuhanIslam => __('Pertubuhan Islam (NGO)'),
            self::Yayasan => __('Yayasan'),
            self::Persatuan => __('Persatuan'),
            self::Kelab => __('Kelab'),
            self::Usrah => __('Usrah'),
            self::Perniagaan => __('Perniagaan'),
            self::Syarikat => __('Syarikat'),
            self::Koperasi => __('Koperasi'),
        };
    }
}
