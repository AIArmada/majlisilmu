<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Nnjeim\World\Models\City as WorldCity;

class City extends WorldCity
{
    /**
     * Events in this city.
     */
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Institutions in this city.
     */
    public function institutions(): HasMany
    {
        return $this->hasMany(Institution::class);
    }

    /**
     * Venues in this city.
     */
    public function venues(): HasMany
    {
        return $this->hasMany(Venue::class);
    }
}
