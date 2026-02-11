<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Nnjeim\World\Models\Country as WorldCountry;

class Country extends WorldCountry
{
    #[\Override]
    public function states(): HasMany
    {
        return $this->hasMany(State::class);
    }

    public function institutions(): HasMany
    {
        return $this->hasMany(Institution::class);
    }

    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }
}
