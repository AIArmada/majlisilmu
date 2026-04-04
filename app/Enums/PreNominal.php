<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PreNominal: string implements HasLabel
{
    // Academic
    case Dr = 'dr';
    case Prof = 'prof';
    case ProfMadya = 'prof_madya';

    // Professional
    case Ir = 'ir';
    case Ar = 'ar';

    // Islamic Teachers/Scholars
    case Ustaz = 'ustaz';
    case Ustazah = 'ustazah';
    case Syeikh = 'syeikh';
    case Habib = 'habib';
    case Pendeta = 'pendeta';
    case TuanGuru = 'tuan_guru';

    // Quran Memorizers/Reciters
    case Hafiz = 'hafiz';
    case Hafizah = 'hafizah';
    case Qari = 'qari';
    case Qariah = 'qariah';

    // Program-Based/Religious Roles
    case ImamMuda = 'imam_muda';
    case Dai = 'dai';
    case Mufti = 'mufti';
    case Kadi = 'kadi';

    public function getLabel(): string
    {
        return match ($this) {
            // Academic
            self::Dr => __('Dr'),
            self::Prof => __('Prof'),
            self::ProfMadya => __('Prof Madya'),

            // Professional
            self::Ir => __('Ir'),
            self::Ar => __('Ar'),

            // Islamic Teachers/Scholars
            self::Ustaz => __('Ustaz'),
            self::Ustazah => __('Ustazah'),
            self::Syeikh => __('Syeikh'),
            self::Habib => __('Habib'),
            self::Pendeta => __('Pendeta'),
            self::TuanGuru => __('Tuan Guru'),

            // Quran Memorizers/Reciters
            self::Hafiz => __('Hafiz'),
            self::Hafizah => __('Hafizah'),
            self::Qari => __('Qari'),
            self::Qariah => __('Qariah'),

            // Program-Based/Religious Roles
            self::ImamMuda => __('Imam Muda'),
            self::Dai => __('Dai'),
            self::Mufti => __('Mufti'),
            self::Kadi => __('Kadi'),
        };
    }
}
