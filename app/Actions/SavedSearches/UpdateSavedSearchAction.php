<?php

namespace App\Actions\SavedSearches;

use App\Models\SavedSearch;
use Illuminate\Support\Arr;
use Lorisleiva\Actions\Concerns\AsAction;

final class UpdateSavedSearchAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(SavedSearch $savedSearch, array $attributes): SavedSearch
    {
        $normalizedAttributes = $this->normalizeAttributes($attributes);

        if ($normalizedAttributes !== []) {
            $savedSearch->update($normalizedAttributes);
        }

        return $savedSearch->fresh() ?? $savedSearch;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function normalizeAttributes(array $attributes): array
    {
        $normalized = [];

        if (array_key_exists('name', $attributes)) {
            $normalized['name'] = (string) $attributes['name'];
        }

        if (array_key_exists('query', $attributes)) {
            $normalized['query'] = $attributes['query'] !== null
                ? (string) $attributes['query']
                : null;
        }

        if (array_key_exists('filters', $attributes)) {
            $normalized['filters'] = $this->normalizeFilters($attributes['filters']);
        }

        if (array_key_exists('radius_km', $attributes)) {
            $normalized['radius_km'] = $attributes['radius_km'] !== null
                ? (int) $attributes['radius_km']
                : null;
        }

        if (array_key_exists('lat', $attributes)) {
            $normalized['lat'] = $attributes['lat'] !== null
                ? (float) $attributes['lat']
                : null;
        }

        if (array_key_exists('lng', $attributes)) {
            $normalized['lng'] = $attributes['lng'] !== null
                ? (float) $attributes['lng']
                : null;
        }

        if (array_key_exists('notify', $attributes) && $attributes['notify'] !== null) {
            $normalized['notify'] = (string) $attributes['notify'];
        }

        return $normalized;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeFilters(mixed $filters): ?array
    {
        if (! is_array($filters) || $filters === []) {
            return null;
        }

        $normalizedFilters = Arr::only($filters, $this->allowedFilterKeys());

        if (array_key_exists('language_codes', $normalizedFilters)) {
            $normalizedFilters['language_codes'] = array_values(array_filter(
                array_map(static function (mixed $languageCode): ?string {
                    $normalizedLanguageCode = trim((string) $languageCode);

                    return $normalizedLanguageCode !== '' ? $normalizedLanguageCode : null;
                }, (array) $normalizedFilters['language_codes']),
                static fn (?string $languageCode): bool => $languageCode !== null,
            ));

            if ($normalizedFilters['language_codes'] === []) {
                unset($normalizedFilters['language_codes']);
            }
        }

        /** @var array<string, mixed> $normalizedFilters */
        return $normalizedFilters === [] ? null : $normalizedFilters;
    }

    /**
     * @return list<string>
     */
    private function allowedFilterKeys(): array
    {
        return [
            'country_id',
            'state_id',
            'district_id',
            'subdistrict_id',
            'institution_id',
            'venue_id',
            'speaker_ids',
            'key_person_roles',
            'person_in_charge_ids',
            'person_in_charge_search',
            'moderator_ids',
            'imam_ids',
            'khatib_ids',
            'bilal_ids',
            'domain_tag_ids',
            'topic_ids',
            'source_tag_ids',
            'issue_tag_ids',
            'reference_ids',
            'language_codes',
            'event_type',
            'event_format',
            'gender',
            'starts_after',
            'starts_before',
            'starts_on_local_date',
            'time_scope',
            'prayer_time',
            'timing_mode',
            'starts_time_from',
            'starts_time_until',
            'children_allowed',
            'is_muslim_only',
            'has_event_url',
            'has_live_url',
            'has_end_time',
            'age_group',
        ];
    }
}
