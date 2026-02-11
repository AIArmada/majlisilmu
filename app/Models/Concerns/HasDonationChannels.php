<?php

namespace App\Models\Concerns;

use App\Models\DonationChannel;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasDonationChannels
{
    /**
     * @return MorphMany<DonationChannel, $this>
     */
    public function donationChannels(): MorphMany
    {
        return $this->morphMany(DonationChannel::class, 'donatable');
    }
}
