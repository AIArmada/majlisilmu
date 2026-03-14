<?php

namespace App\Actions\Contributions;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Carbon;
use Lorisleiva\Actions\Concerns\AsAction;

class ResolveContributionChangedPayloadAction
{
    use AsAction;

    /**
     * @param  array<string, mixed>  $state
     * @param  array<string, mixed>  $originalData
     * @return array<string, mixed>
     */
    public function handle(array $state, array $originalData): array
    {
        $changes = [];

        foreach ($state as $key => $value) {
            if (! array_key_exists($key, $originalData)) {
                continue;
            }

            if ($this->valuesEqual($value, $originalData[$key])) {
                continue;
            }

            $changes[$key] = $value;
        }

        return $changes;
    }

    private function valuesEqual(mixed $left, mixed $right): bool
    {
        return json_encode($this->normalizeComparable($left)) === json_encode($this->normalizeComparable($right));
    }

    private function normalizeComparable(mixed $value): mixed
    {
        if ($value instanceof Carbon) {
            return $value->toISOString();
        }

        if ($value instanceof \BackedEnum) {
            return $value->value;
        }

        if ($value instanceof Arrayable) {
            return $this->normalizeComparable($value->toArray());
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->normalizeComparable($item), $value);
        }

        return $value;
    }
}
