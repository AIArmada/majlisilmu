<?php

namespace App\Models\Concerns;

trait HasContacts
{
    public function contacts(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(\App\Models\Contact::class, 'contactable');
    }

    public function getEmailAttribute(): ?string
    {
        return $this->contacts()->where('category', 'email')->value('value');
    }

    public function getPhoneAttribute(): ?string
    {
        return $this->contacts()->where('category', 'phone')->value('value');
    }
}
