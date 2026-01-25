<?php

namespace App\Models\Concerns;

use App\Models\DonationChannel;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasDonationChannels
{
    public function donationChannels(): MorphMany
    {
        return $this->morphMany(DonationChannel::class, 'donatable');
    }
}
