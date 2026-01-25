<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasLanguages
{
    /**
     * Get all of the languages for the model.
     */
    public function languages(): MorphToMany
    {
        return $this->morphToMany(\Nnjeim\World\Models\Language::class, 'languageable', 'languageables');
    }

    /**
     * Sync the languages for the model.
     *
     * @param  array<int>|int  $languages
     */
    public function syncLanguages(array|int $languages): void
    {
        $this->languages()->sync($languages);
    }
}
