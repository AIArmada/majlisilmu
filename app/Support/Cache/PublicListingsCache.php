<?php

namespace App\Support\Cache;

use Illuminate\Support\Facades\Cache;

class PublicListingsCache
{
    public function bustMajlisListing(): void
    {
        Cache::forget('default_events_search');
        Cache::forget('default_events_search_v2');
        Cache::forget('kimi_home_stats');
        Cache::forget('kimi_featured_events');
        Cache::forget('kimi_featured_events_v2');
        Cache::forget('kimi_upcoming_events');
        Cache::forget('kimi_upcoming_events_v2');
        Cache::forget('states_my');
        Cache::forget('states_my_v2');

        foreach ($this->supportedLocales() as $locale) {
            Cache::forget("events_topics_{$locale}");
            Cache::forget("events_institutions_{$locale}");
            Cache::forget("events_institutions_{$locale}_v2");
            Cache::forget("events_speakers_{$locale}");
            Cache::forget("events_speakers_{$locale}_v2");
            Cache::forget("events_disciplines_{$locale}_v2");
            Cache::forget("events_domains_{$locale}_v2");
            Cache::forget("events_sources_{$locale}_v2");
            Cache::forget("events_issues_{$locale}_v2");
            Cache::forget("events_references_{$locale}_v2");
            Cache::forget("events_venues_{$locale}_v2");
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
