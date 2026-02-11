<?php

namespace App\Forms\Components;

use Filament\Forms\Components\Select as FilamentSelect;

class Select extends FilamentSelect
{
    public function closeOnSelect(bool $condition = true): static
    {
        if ($condition) {
            $this->extraAttributes([
                'x-close-on-select' => true,
            ]);
        }

        return $this;
    }

    public function quickAdd(): static
    {
        if (static::hasMacro('quickAdd')) {
            $result = $this->__call('quickAdd', []);

            return $result instanceof static ? $result : $this;
        }

        return $this;
    }
}
