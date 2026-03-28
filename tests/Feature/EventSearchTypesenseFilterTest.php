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

test('typesense filters include country constraint when provided', function () {
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
        'country_id' => 132,
    ]);

    expect($filters)->toContain('country_id:=132');
});

test('country filter alone does not force database fallback', function () {
    $service = new class extends EventSearchService
    {
        /**
         * @param  array<string, mixed>  $filters
         */
        public function exposedRequiresDatabaseFiltering(array $filters): bool
        {
            return $this->requiresDatabaseFiltering($filters);
        }
    };

    expect($service->exposedRequiresDatabaseFiltering([
        'country_id' => 132,
    ]))->toBeFalse();
});

test('typesense filters include domain tag ids constraint when provided', function () {
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
        'domain_tag_ids' => ['tag-1', 'tag-2'],
    ]);

    expect($filters)->toContain('domain_tag_ids:[tag-1,tag-2]');
});

test('typesense filters include source, issue, and reference constraints when provided', function () {
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
        'source_tag_ids' => ['source-1'],
        'issue_tag_ids' => ['issue-1'],
        'reference_ids' => ['ref-1'],
    ]);

    expect($filters)
        ->toContain('source_tag_ids:[source-1]')
        ->toContain('issue_tag_ids:[issue-1]')
        ->toContain('reference_ids:[ref-1]');
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
