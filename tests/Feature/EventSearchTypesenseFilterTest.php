<?php

use App\Services\EventSearchService;

test('typesense filters include active event constraint', function () {
    $service = new class extends EventSearchService
    {
        /**
         * @param  array<string, mixed>  $filters
         * @return array<int, string>
         */
        public function exposedBuildTypesenseFilterParts(array $filters): array
        {
            return $this->buildTypesenseFilterParts($filters);
        }
    };

    $filters = $service->exposedBuildTypesenseFilterParts([]);

    expect($filters)->toContain('is_active:=true');
});

test('typesense filters include subdistrict constraint when provided', function () {
    $service = new class extends EventSearchService
    {
        /**
         * @param  array<string, mixed>  $filters
         * @return array<int, string>
         */
        public function exposedBuildTypesenseFilterParts(array $filters): array
        {
            return $this->buildTypesenseFilterParts($filters);
        }
    };

    $filters = $service->exposedBuildTypesenseFilterParts([
        'subdistrict_id' => 321,
    ]);

    expect($filters)->toContain('subdistrict_id:=321');
});

test('typesense starts_after filter uses held-period overlap semantics', function () {
    $service = new class extends EventSearchService
    {
        /**
         * @param  array<string, mixed>  $filters
         * @return array<int, string>
         */
        public function exposedBuildTypesenseFilterParts(array $filters): array
        {
            return $this->buildTypesenseFilterParts($filters);
        }
    };

    $filters = $service->exposedBuildTypesenseFilterParts([
        'time_scope' => 'all',
        'starts_after' => '2026-03-20',
    ]);

    $hasOverlapFilter = collect($filters)->contains(
        static fn (string $filter): bool => str_contains($filter, 'ends_at:>=')
            && str_contains($filter, '||starts_at:>=')
    );

    expect($hasOverlapFilter)->toBeTrue();
});
