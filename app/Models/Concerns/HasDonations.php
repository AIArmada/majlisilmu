<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasDonations
{
    public function donations(): MorphMany
    {
        return $this->morphMany(\App\Models\Donation::class, 'donatable');
    }
}
