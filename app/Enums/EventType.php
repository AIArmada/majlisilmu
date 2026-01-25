<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

/**
 * Event types categorized by their nature.
 */
enum EventType: string implements HasColor, HasDescription, HasIcon, HasLabel
{
    // ===== ILMU (Educational/Talks) =====
    case Kuliah = 'kuliah';
    case Ceramah = 'ceramah';
    case Tazkirah = 'tazkirah';
    case Forum = 'forum';
    case Daurah = 'daurah';
    case Halaqah = 'halaqah';
    case Seminar = 'seminar';
    case KelasKitab = 'kelas_kitab';

    // ===== TILAWAH (Recitation) =====
    case BacaanYasin = 'bacaan_yasin';
    case KhatamQuran = 'khatam_quran';
    case MajlisTilawah = 'majlis_tilawah';
    case TadabbulQuran = 'tadabbur_quran';

    // ===== IBADAH (Worship) =====
    case Qiamullail = 'qiamullail';
    case SolatHajat = 'solat_hajat';
    case Tahlil = 'tahlil';

    // ===== ZIKIR & DOA =====
    case MajlisZikir = 'majlis_zikir';
    case MajlisSelawat = 'majlis_selawat';
    case DoaSelamat = 'doa_selamat';
    case Maulid = 'maulid';

    // ===== KOMUNITI (Community) =====
    case GotongRoyong = 'gotong_royong';
    case Kenduri = 'kenduri';
    case Iftar = 'iftar';
    case Sahur = 'sahur';
    case Korban = 'korban';
    case Aqiqah = 'aqiqah';

    // ===== UMUM (General) =====
    case Academic = 'academic';
    case Technology = 'technology';
    case Business = 'business';
    case Health = 'health';
    case Arts = 'arts';
    case Sports = 'sports';

    // ===== OTHER =====
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            // Ilmu
            self::Kuliah => __('Kuliah'),
            self::Ceramah => __('Ceramah'),
            self::Tazkirah => __('Tazkirah'),
            self::Forum => __('Forum'),
            self::Daurah => __('Daurah Ilmiah'),
            self::Halaqah => __('Halaqah'),
            self::Seminar => __('Seminar'),
            self::KelasKitab => __('Kelas Kitab'),

            // Tilawah
            self::BacaanYasin => __('Bacaan Yasin'),
            self::KhatamQuran => __('Khatam Al-Quran'),
            self::MajlisTilawah => __('Majlis Tilawah'),
            self::TadabbulQuran => __('Tadabbur Al-Quran'),

            // Ibadah
            self::Qiamullail => __('Qiamullail'),
            self::SolatHajat => __('Solat Hajat'),
            self::Tahlil => __('Tahlil'),

            // Zikir & Doa
            self::MajlisZikir => __('Majlis Zikir'),
            self::MajlisSelawat => __('Majlis Selawat'),
            self::DoaSelamat => __('Doa Selamat'),
            self::Maulid => __('Maulid / Maulidur Rasul'),

            // Komuniti
            self::GotongRoyong => __('Gotong-royong'),
            self::Kenduri => __('Kenduri'),
            self::Iftar => __('Iftar / Berbuka Puasa'),
            self::Sahur => __('Sahur'),
            self::Korban => __('Korban'),
            self::Aqiqah => __('Aqiqah'),

            // Umum
            self::Academic => __('Akademik'),
            self::Technology => __('Teknologi & AI'),
            self::Business => __('Bisnes & Keusahawanan'),
            self::Health => __('Kesihatan'),
            self::Arts => __('Seni & Budaya'),
            self::Sports => __('Sukan & Riadah'),

            self::Other => __('Lain-lain'),
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            // Ilmu
            self::Kuliah => __('Syarahan ilmiah biasa'),
            self::Ceramah => __('Ceramah umum untuk masyarakat'),
            self::Tazkirah => __('Peringatan ringkas'),
            self::Forum => __('Perbincangan dengan panel'),
            self::Daurah => __('Kursus intensif beberapa hari'),
            self::Halaqah => __('Lingkaran pengajian'),
            self::Seminar => __('Seminar atau bengkel'),
            self::KelasKitab => __('Pengajian kitab tertentu'),

            // Tilawah
            self::BacaanYasin => __('Bacaan surah Yasin beramai-ramai'),
            self::KhatamQuran => __('Majlis tamat baca Al-Quran'),
            self::MajlisTilawah => __('Majlis bacaan Al-Quran'),
            self::TadabbulQuran => __('Tadabbur dan penghayatan Al-Quran'),

            // Ibadah
            self::Qiamullail => __('Solat malam berjemaah'),
            self::SolatHajat => __('Solat hajat berjemaah'),
            self::Tahlil => __('Majlis tahlil dan doa'),

            // Zikir & Doa
            self::MajlisZikir => __('Majlis zikir berjemaah'),
            self::MajlisSelawat => __('Bacaan selawat berjemaah'),
            self::DoaSelamat => __('Majlis doa selamat'),
            self::Maulid => __('Sambutan kelahiran Rasulullah SAW'),

            // Komuniti
            self::GotongRoyong => __('Kerja-kerja kemasyarakatan'),
            self::Kenduri => __('Jamuan makan besar'),
            self::Iftar => __('Berbuka puasa berjemaah'),
            self::Sahur => __('Sahur berjemaah'),
            self::Korban => __('Ibadah korban'),
            self::Aqiqah => __('Majlis aqiqah'),

            // Umum
            self::Academic => __('Kelas tambahan, tuisyen atau bengkel akademik'),
            self::Technology => __('Bengkel koding, AI, atau teknologi'),
            self::Business => __('Perkongsian ilmu perniagaan'),
            self::Health => __('Ceramah atau pemeriksaan kesihatan'),
            self::Arts => __('Pameran atau bengkel seni'),
            self::Sports => __('Aktiviti riadah atau sukan'),

            self::Other => null,
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            // Ilmu - Green shades (knowledge)
            self::Kuliah, self::Ceramah, self::Tazkirah, self::Forum,
            self::Daurah, self::Halaqah, self::Seminar, self::KelasKitab => 'success',

            // Tilawah - Blue (Quran)
            self::BacaanYasin, self::KhatamQuran, self::MajlisTilawah, self::TadabbulQuran => 'info',

            // Ibadah - Purple (spiritual)
            self::Qiamullail, self::SolatHajat, self::Tahlil => 'purple',

            // Zikir & Doa - Amber (warmth)
            self::MajlisZikir, self::MajlisSelawat, self::DoaSelamat, self::Maulid => 'warning',

            // Komuniti - Pink (community)
            self::GotongRoyong, self::Kenduri, self::Iftar, self::Sahur, self::Korban, self::Aqiqah => 'pink',

            // Umum - Cyan/Teal
            self::Academic, self::Technology => 'cyan',
            self::Business => 'slate',
            self::Health => 'danger',
            self::Arts => 'fuchsia',
            self::Sports => 'lime',

            self::Other => 'gray',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            // Ilmu
            self::Kuliah, self::Ceramah, self::Tazkirah, self::Forum,
            self::Daurah, self::Halaqah, self::Seminar, self::KelasKitab => 'heroicon-o-academic-cap',

            // Tilawah
            self::BacaanYasin, self::KhatamQuran, self::MajlisTilawah, self::TadabbulQuran => 'heroicon-o-book-open',

            // Ibadah
            self::Qiamullail, self::SolatHajat, self::Tahlil => 'heroicon-o-moon',

            // Zikir & Doa
            self::MajlisZikir, self::MajlisSelawat, self::DoaSelamat, self::Maulid => 'heroicon-o-heart',

            // Komuniti
            self::GotongRoyong, self::Kenduri, self::Iftar, self::Sahur, self::Korban, self::Aqiqah => 'heroicon-o-user-group',

            // Umum
            self::Academic => 'heroicon-o-book-open',
            self::Technology => 'heroicon-o-cpu-chip',
            self::Business => 'heroicon-o-briefcase',
            self::Health => 'heroicon-o-heart',
            self::Arts => 'heroicon-o-musical-note',
            self::Sports => 'heroicon-o-trophy',

            self::Other => 'heroicon-o-calendar',
        };
    }

    /**
     * Get the category/group this event type belongs to.
     */
    public function getCategory(): string
    {
        return match ($this) {
            self::Kuliah, self::Ceramah, self::Tazkirah, self::Forum,
            self::Daurah, self::Halaqah, self::Seminar, self::KelasKitab => 'ilmu',

            self::BacaanYasin, self::KhatamQuran, self::MajlisTilawah, self::TadabbulQuran => 'tilawah',

            self::Qiamullail, self::SolatHajat, self::Tahlil => 'ibadah',

            self::MajlisZikir, self::MajlisSelawat, self::DoaSelamat, self::Maulid => 'zikir',

            self::GotongRoyong, self::Kenduri, self::Iftar, self::Sahur, self::Korban, self::Aqiqah => 'komuniti',

            self::Academic, self::Technology, self::Business, self::Health, self::Arts, self::Sports => 'umum',

            self::Other => 'other',
        };
    }

    /**
     * Get event types grouped by category for dropdown.
     */
    /**
     * Get event types grouped by category for dropdown.
     */
    public static function getGroupedOptions(): array
    {
        return [
            __('Ilmu (Pengajian)') => [
                self::Kuliah->value => self::Kuliah->getLabel(),
                self::Ceramah->value => self::Ceramah->getLabel(),
                self::Tazkirah->value => self::Tazkirah->getLabel(),
                self::Forum->value => self::Forum->getLabel(),
                self::Daurah->value => self::Daurah->getLabel(),
                self::Halaqah->value => self::Halaqah->getLabel(),
                self::Seminar->value => self::Seminar->getLabel(),
                self::KelasKitab->value => self::KelasKitab->getLabel(),
            ],
            __('Tilawah (Al-Quran)') => [
                self::BacaanYasin->value => self::BacaanYasin->getLabel(),
                self::KhatamQuran->value => self::KhatamQuran->getLabel(),
                self::MajlisTilawah->value => self::MajlisTilawah->getLabel(),
                self::TadabbulQuran->value => self::TadabbulQuran->getLabel(),
            ],
            __('Ibadah') => [
                self::Qiamullail->value => self::Qiamullail->getLabel(),
                self::SolatHajat->value => self::SolatHajat->getLabel(),
                self::Tahlil->value => self::Tahlil->getLabel(),
            ],
            __('Zikir & Doa') => [
                self::MajlisZikir->value => self::MajlisZikir->getLabel(),
                self::MajlisSelawat->value => self::MajlisSelawat->getLabel(),
                self::DoaSelamat->value => self::DoaSelamat->getLabel(),
                self::Maulid->value => self::Maulid->getLabel(),
            ],
            __('Komuniti') => [
                self::GotongRoyong->value => self::GotongRoyong->getLabel(),
                self::Kenduri->value => self::Kenduri->getLabel(),
                self::Iftar->value => self::Iftar->getLabel(),
                self::Sahur->value => self::Sahur->getLabel(),
                self::Korban->value => self::Korban->getLabel(),
                self::Aqiqah->value => self::Aqiqah->getLabel(),
            ],
            /*
            // Hidden for now as per requirements
            __('Umum & Kemahiran') => [
                self::Academic->value => self::Academic->getLabel(),
                self::Technology->value => self::Technology->getLabel(),
                self::Business->value => self::Business->getLabel(),
                self::Health->value => self::Health->getLabel(),
                self::Arts->value => self::Arts->getLabel(),
                self::Sports->value => self::Sports->getLabel(),
            ],
            __('Lain-lain') => [
                self::Other->value => self::Other->getLabel(),
            ],
            */
        ];
    }
}
