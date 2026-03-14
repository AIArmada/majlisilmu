<?php

namespace App\Actions\SavedSearches;

use App\Models\SavedSearch;
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

        /** @var array<string, mixed> $filters */
        return $filters;
    }
}
