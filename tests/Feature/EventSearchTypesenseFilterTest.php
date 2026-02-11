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
