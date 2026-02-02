<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InstitutionType: string implements HasLabel
{
    case Masjid = 'masjid';
    case Surau = 'surau';
    case EducationalCenter = 'educational_center';
    case CommunityCenter = 'community_center';
    case Others = 'others';

    public function getLabel(): string
    {
        return match ($this) {
            self::Masjid => __('Masjid'),
            self::Surau => __('Surau'),
            self::EducationalCenter => __('Educational Center'),
            self::CommunityCenter => __('Community Center'),
            self::Others => __('Others'),
        };
    }
}
