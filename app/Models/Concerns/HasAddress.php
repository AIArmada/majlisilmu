<?php

namespace App\Models\Concerns;

trait HasAddress
{
    public function address(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(\App\Models\Address::class, 'addressable');
    }

    /**
     * Get the address line 1 (alias).
     */
    public function getAddressLine1Attribute(): string
    {
        return $this->addressModel?->line1 ?? '';
    }

    /**
     * Get the address relationship (helper to avoid conflict with legacy accessors if any, strict typing).
     */
    public function getAddressModelAttribute()
    {
        return $this->relationLoaded('address') ? $this->address : $this->address()->first();
    }
}
