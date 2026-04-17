<?php

declare(strict_types=1);

namespace App\Support\Api;

final class ApiPagination
{
    public static function normalizePerPage(int $value, int $default = 20, int $max = 100): int
    {
        $normalizedDefault = max($default, 1);
        $normalizedMax = max($max, $normalizedDefault);

        if ($value < 1) {
            return $normalizedDefault;
        }

        return min($value, $normalizedMax);
    }
}
