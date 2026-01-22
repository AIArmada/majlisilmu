<?php

namespace App\Models\Concerns;

trait HasSocialMedia
{
    public function socialMedia(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Models\SocialMedia::class, 'socialable');
    }
}
