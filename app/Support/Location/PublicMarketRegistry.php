<?php

namespace App\Support\Location;

use App\Models\Country;

class PublicMarketRegistry
{
    /**
     * @var array<string, int|null>
     */
    protected ?array $countryIdsByIso2 = null;

    /**
     * @return array<string, array{label: string, flag: string, iso2: string, enabled: bool, coming_soon: bool}>
     */
    public function all(): array
    {
        /** @var array<string, array{label: string, flag: string, iso2: string, enabled: bool, coming_soon: bool}> $markets */
        $markets = config('public-markets.markets', []);

        return $markets;
    }

    public function defaultKey(): string
    {
        $configured = (string) config('public-markets.default', 'malaysia');

        if ($this->has($configured) && $this->isEnabled($configured)) {
            return $configured;
        }

        if ($this->has('malaysia') && $this->isEnabled('malaysia')) {
            return 'malaysia';
        }

        foreach ($this->all() as $key => $market) {
            if ((bool) ($market['enabled'] ?? false)) {
                return $key;
            }
        }

        return array_key_first($this->all()) ?? 'malaysia';
    }

    /**
     * @return array{key: string, label: string, flag: string, iso2: string, enabled: bool, coming_soon: bool}
     */
    public function default(): array
    {
        return $this->market($this->defaultKey());
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->all());
    }

    public function isEnabled(string $key): bool
    {
        return (bool) ($this->all()[$key]['enabled'] ?? false);
    }

    /**
     * @return array{key: string, label: string, flag: string, iso2: string, enabled: bool, coming_soon: bool}|null
     */
    public function find(string $key): ?array
    {
        if (! $this->has($key)) {
            return null;
        }

        return $this->market($key);
    }

    /**
     * @return array{key: string, label: string, flag: string, iso2: string, enabled: bool, coming_soon: bool}
     */
    public function market(string $key): array
    {
        $market = $this->all()[$key];

        return [
            'key' => $key,
            'label' => $market['label'],
            'flag' => $market['flag'],
            'iso2' => strtoupper($market['iso2']),
            'enabled' => (bool) $market['enabled'],
            'coming_soon' => (bool) $market['coming_soon'],
        ];
    }

    public function normalizeMarketKey(?string $key, bool $enabledOnly = true): string
    {
        $normalized = strtolower(trim((string) $key));

        if ($normalized === '' || ! $this->has($normalized)) {
            return $this->defaultKey();
        }

        if ($enabledOnly && ! $this->isEnabled($normalized)) {
            return $this->defaultKey();
        }

        return $normalized;
    }

    public function countryIdForMarket(string $key): ?int
    {
        $market = $this->find($key);

        if ($market === null) {
            return null;
        }

        return $this->countryIdFromIso2($market['iso2']);
    }

    public function normalizeCountryId(?int $countryId): ?int
    {
        if ($countryId === null) {
            return null;
        }

        $marketKey = $this->marketKeyForCountryId($countryId);

        if ($marketKey === null || ! $this->isEnabled($marketKey)) {
            return null;
        }

        return $countryId;
    }

    public function marketKeyForCountryId(int $countryId): ?string
    {
        foreach ($this->all() as $key => $market) {
            $resolvedCountryId = $this->countryIdFromIso2((string) $market['iso2']);

            if ($resolvedCountryId === $countryId) {
                return $key;
            }
        }

        return null;
    }

    public function countryIdFromIso2(string $iso2): ?int
    {
        $normalizedIso2 = strtoupper(trim($iso2));

        if ($normalizedIso2 === '') {
            return null;
        }

        $countryId = $this->countryIdsByIso2()[$normalizedIso2] ?? null;

        if (is_int($countryId)) {
            return $countryId;
        }

        return $normalizedIso2 === 'MY'
            ? PreferredCountryResolver::MALAYSIA_ID
            : null;
    }

    /**
     * @return array<string, int|null>
     */
    protected function countryIdsByIso2(): array
    {
        if (is_array($this->countryIdsByIso2)) {
            return $this->countryIdsByIso2;
        }

        $iso2s = collect($this->all())
            ->pluck('iso2')
            ->map(static fn (mixed $iso2): string => strtoupper((string) $iso2))
            ->unique()
            ->values()
            ->all();

        $resolved = Country::query()
            ->whereIn('iso2', $iso2s)
            ->pluck('id', 'iso2')
            ->mapWithKeys(static fn (mixed $id, mixed $iso2): array => [strtoupper((string) $iso2) => is_numeric($id) ? (int) $id : null])
            ->all();

        if (! array_key_exists('MY', $resolved)) {
            $resolved['MY'] = PreferredCountryResolver::MALAYSIA_ID;
        }

        $this->countryIdsByIso2 = $resolved;

        return $this->countryIdsByIso2;
    }
}
