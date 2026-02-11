<?php

namespace App\Models\Concerns;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasContacts
{
    /**
     * @return MorphMany<Contact, $this>
     */
    public function contacts(): MorphMany
    {
        return $this->morphMany(Contact::class, 'contactable');
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
