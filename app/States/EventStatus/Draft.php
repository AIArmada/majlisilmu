<?php

namespace App\States\EventStatus;

use Filament\Support\Colors\Color;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

final class Draft extends EventStatus implements HasColor, HasIcon, HasLabel
{
    public static $name = 'draft';

    public function getLabel(): string
    {
        return __('Draft');
    }

    public function getColor(): string|array
    {
        return Color::Gray;
    }

    public function getIcon(): string
    {
        return 'heroicon-o-pencil-square';
    }
}
