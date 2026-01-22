<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Nnjeim\World\Models\State as WorldState;

class State extends WorldState
{
    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }

    /**
     * Get all addresses in this state.
     */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }
}
