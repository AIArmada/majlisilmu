<?php

namespace App\Support\Location;

use App\Models\Address;

class AddressHierarchyFormatter
{
    /**
     * @var list<string>
     */
    private const array STATE_HIDDEN_DISTRICTS = ['kuala lumpur', 'putrajaya', 'labuan'];

    /**
     * @param  list<'subdistrict'|'district'|'state'>  $order
     * @return list<string>
     */
    public static function parts(?Address $address, array $order = ['subdistrict', 'district', 'state']): array
    {
        $districtName = self::normalizePart($address?->district?->name);
        $stateName = self::normalizePart($address?->state?->name);
        $subdistrictName = self::normalizePart($address?->subdistrict?->name);

        if (is_string($districtName) && in_array(mb_strtolower($districtName), self::STATE_HIDDEN_DISTRICTS, true)) {
            $stateName = null;
        }

        $availableParts = [
            'subdistrict' => $subdistrictName,
            'district' => $districtName,
            'state' => $stateName,
        ];

        $parts = [];

        foreach ($order as $key) {
            $part = $availableParts[$key] ?? null;

            if ($part === null) {
                continue;
            }

            $previousPart = $parts[array_key_last($parts)] ?? null;

            if (is_string($previousPart) && mb_strtolower($previousPart) === mb_strtolower($part)) {
                continue;
            }

            $parts[] = $part;
        }

        return $parts;
    }

    /**
     * @param  list<'subdistrict'|'district'|'state'>  $order
     */
    public static function format(?Address $address, array $order = ['subdistrict', 'district', 'state'], string $separator = ', '): string
    {
        return implode($separator, self::parts($address, $order));
    }

    private static function normalizePart(?string $value): ?string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
