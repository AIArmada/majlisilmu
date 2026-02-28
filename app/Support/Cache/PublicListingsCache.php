<?php

namespace App\Support\Cache;

use Illuminate\Support\Facades\Cache;

class PublicListingsCache
{
    public function bustMajlisListing(): void
    {
        Cache::forget('default_events_search');

        foreach ($this->supportedLocales() as $locale) {
            Cache::forget("events_topics_{$locale}");
            Cache::forget("events_institutions_{$locale}");
            Cache::forget("events_speakers_{$locale}");
        }
    }

    /**
     * @return list<string>
     */
    private function supportedLocales(): array
    {
        $supportedLocales = config('app.supported_locales', []);

        if (is_array($supportedLocales) && $supportedLocales !== []) {
            $localeCodes = collect(array_keys($supportedLocales))
                ->filter(static fn (mixed $locale): bool => is_string($locale) && $locale !== '')
                ->map(static fn (mixed $locale): string => (string) $locale)
                ->values()
                ->all();

            if ($localeCodes !== []) {
                return $localeCodes;
            }
        }

        $defaultLocale = config('app.locale', 'ms');

        if (is_string($defaultLocale) && $defaultLocale !== '') {
            return [$defaultLocale];
        }

        return ['ms'];
    }
}
