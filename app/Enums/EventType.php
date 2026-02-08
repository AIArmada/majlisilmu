<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum EventType: string implements HasColor, HasIcon, HasLabel
{
    // Ilmu
    case KuliahCeramah = 'kuliah_ceramah';
    case KelasDaurah = 'kelas_daurah';
    case Forum = 'forum';
    case SeminarKonvensyen = 'seminar_konvensyen';
    case Tazkirah = 'tazkirah';

    // Ibadah
    case Qiamullail = 'qiamullail';
    case Tahlil = 'tahlil';
    case SolatHajat = 'solat_hajat';

    // Zikir & Doa
    case Zikir = 'zikir';
    case Selawat = 'selawat';
    case DoaSelamat = 'doa_selamat';

    // Tilawah
    case BacaanYasin = 'bacaan_yasin';
    case KhatamQuran = 'khatam_quran';
    case Tilawah = 'tilawah';
    case HafazanQuran = 'hafazan_quran';

    // Komuniti
    case GotongRoyong = 'gotong_royong';
    case Kenduri = 'kenduri';
    case Iftar = 'iftar';
    case Sahur = 'sahur';
    case Korban = 'korban';
    case Aqiqah = 'aqiqah';

    // Umum / Lain-lain
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::KuliahCeramah => 'Kuliah / Ceramah',
            self::KelasDaurah => 'Kelas / Daurah',
            self::Forum => 'Forum',
            self::SeminarKonvensyen => 'Seminar / Konvensyen',
            self::Tazkirah => 'Tazkirah',
            self::Qiamullail => 'Qiamullail',
            self::Tahlil => 'Tahlil',
            self::SolatHajat => 'Solat Hajat',
            self::Zikir => 'Zikir',
            self::Selawat => 'Selawat',
            self::DoaSelamat => 'Doa Selamat',
            self::BacaanYasin => 'Bacaan Yasin',
            self::KhatamQuran => 'Khatam Al-Quran',
            self::Tilawah => 'Tilawah Al-Quran',
            self::HafazanQuran => 'Hafazan Al-Quran',
            self::GotongRoyong => 'Gotong Royong',
            self::Kenduri => 'Kenduri',
            self::Iftar => 'Iftar / Berbuka Puasa',
            self::Sahur => 'Sahur',
            self::Korban => 'Korban',
            self::Aqiqah => 'Aqiqah',
            self::Other => 'Lain-lain',
        };
    }

    public function getGroup(): string
    {
        return match ($this) {
            self::KuliahCeramah, self::KelasDaurah, self::Forum, self::SeminarKonvensyen, self::Tazkirah => 'Ilmu',
            self::Qiamullail, self::Tahlil, self::SolatHajat => 'Ibadah',
            self::Zikir, self::Selawat, self::DoaSelamat => 'Zikir & Doa',
            self::BacaanYasin, self::KhatamQuran, self::Tilawah, self::HafazanQuran => 'Tilawah',
            self::GotongRoyong, self::Kenduri, self::Iftar, self::Sahur, self::Korban, self::Aqiqah => 'Komuniti',
            self::Other => 'Lain-lain',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::KuliahCeramah, self::KelasDaurah, self::Forum, self::SeminarKonvensyen, self::Tazkirah => 'info',
            self::Qiamullail, self::Tahlil, self::SolatHajat => 'success',
            self::Zikir, self::Selawat, self::DoaSelamat => 'primary',
            self::BacaanYasin, self::KhatamQuran, self::Tilawah, self::HafazanQuran => 'success',
            self::GotongRoyong, self::Kenduri, self::Iftar, self::Sahur, self::Korban, self::Aqiqah => 'warning',
            self::Other => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::KuliahCeramah => 'heroicon-m-book-open',
            self::KelasDaurah => 'heroicon-m-academic-cap',
            self::Forum => 'heroicon-m-chat-bubble-left-right',
            self::SeminarKonvensyen => 'heroicon-m-academic-cap',
            self::Tazkirah => 'heroicon-m-megaphone',
            self::Qiamullail, self::SolatHajat => 'heroicon-m-moon',
            self::Tahlil => 'heroicon-m-heart',
            self::Zikir, self::Selawat => 'heroicon-m-sparkles',
            self::DoaSelamat => 'heroicon-m-hand-raised',
            self::BacaanYasin, self::KhatamQuran, self::Tilawah, self::HafazanQuran => 'heroicon-m-book-open',
            self::GotongRoyong => 'heroicon-m-user-group',
            self::Kenduri, self::Iftar, self::Sahur => 'heroicon-m-cake',
            self::Korban, self::Aqiqah => 'heroicon-m-gift',
            self::Other => 'heroicon-m-ellipsis-horizontal-circle',
        };
    }
}
