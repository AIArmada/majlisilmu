<?php

use App\Models\Institution;
use App\Models\Speaker;
use App\Support\Search\InstitutionSearchService;
use App\Support\Search\SpeakerSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('falls back to the local speaker search index when typesense lookup fails', function () {
    $speaker = Speaker::factory()->create([
        'name' => 'Nurul Akma',
        'honorific' => null,
        'pre_nominal' => ['ustazah'],
        'post_nominal' => [],
        'qualifications' => [],
        'status' => 'verified',
        'is_active' => true,
    ]);

    $baseService = app(SpeakerSearchService::class);
    $baseService->syncSpeakerRecord($speaker);

    $service = new class extends SpeakerSearchService
    {
        protected function shouldUseScoutSearch(): bool
        {
            return true;
        }

        protected function searchIdsWithScout(string $search, array $options = []): array
        {
            throw new RuntimeException('Typesense unavailable');
        }

        protected function logScoutFallback(string $message, Throwable $exception, string $search): void {}
    };

    $ids = $service->publicSearchIds('ustazah');
    $queryIds = $service->applyIndexedSearch(Speaker::query()->where('status', 'verified'), 'ustazah')
        ->pluck('speakers.id')
        ->map(static fn (mixed $id): string => (string) $id)
        ->all();

    expect($ids)->toContain((string) $speaker->id)
        ->and($queryIds)->toContain((string) $speaker->id);
});

it('falls back to local speaker fuzzy search when typesense lookup fails', function () {
    $speaker = Speaker::factory()->create([
        'name' => 'Samad Al-Bakri',
        'honorific' => null,
        'pre_nominal' => [],
        'post_nominal' => [],
        'qualifications' => [],
        'status' => 'verified',
        'is_active' => true,
    ]);

    app(SpeakerSearchService::class)->syncSpeakerRecord($speaker);

    $service = new class extends SpeakerSearchService
    {
        protected function shouldUseScoutSearch(): bool
        {
            return true;
        }

        protected function searchIdsWithScout(string $search, array $options = []): array
        {
            throw new RuntimeException('Typesense unavailable');
        }

        protected function logScoutFallback(string $message, Throwable $exception, string $search): void {}
    };

    expect($service->publicFuzzySearchIds('Smad'))->toContain((string) $speaker->id);
});

it('falls back to database institution search when typesense lookup fails', function () {
    $institution = Institution::factory()->create([
        'name' => 'Masjid Sultan Salahuddin Abdul Aziz Shah',
        'nickname' => 'Masjid Biru',
        'description' => 'Pusat komuniti',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $service = new class extends InstitutionSearchService
    {
        protected function shouldUseScoutSearch(): bool
        {
            return true;
        }

        protected function searchIdsWithScout(string $search, array $options = []): array
        {
            throw new RuntimeException('Typesense unavailable');
        }

        protected function logScoutFallback(string $message, Throwable $exception, string $search): void {}
    };

    $ids = $service->publicSearchIds('Masjid Biru');
    $queryIds = $service->applySearch(Institution::query()->where('status', 'verified'), 'Masjid Biru')
        ->pluck('institutions.id')
        ->map(static fn (mixed $id): string => (string) $id)
        ->all();

    expect($ids)->toContain((string) $institution->id)
        ->and($queryIds)->toContain((string) $institution->id);
});

it('falls back to database institution fuzzy search when typesense lookup fails', function () {
    $institution = Institution::factory()->create([
        'name' => 'Masjid Al Hidayah',
        'description' => 'Kuliah dan komuniti',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $service = new class extends InstitutionSearchService
    {
        protected function shouldUseScoutSearch(): bool
        {
            return true;
        }

        protected function searchIdsWithScout(string $search, array $options = []): array
        {
            throw new RuntimeException('Typesense unavailable');
        }

        protected function logScoutFallback(string $message, Throwable $exception, string $search): void {}
    };

    expect($service->publicFuzzySearchIds('Hidayh'))->toContain((string) $institution->id);
});

it('uses scout database search for speakers when the database driver is configured', function () {
    config()->set('scout.driver', 'database');

    $speaker = Speaker::factory()->create([
        'name' => 'Nurul Akma',
        'honorific' => null,
        'pre_nominal' => ['ustazah'],
        'post_nominal' => [],
        'qualifications' => [],
        'status' => 'verified',
        'is_active' => true,
    ]);

    $hiddenSpeaker = Speaker::factory()->create([
        'name' => 'Nurul Akma Hidden',
        'honorific' => null,
        'pre_nominal' => ['ustazah'],
        'post_nominal' => [],
        'qualifications' => [],
        'status' => 'rejected',
        'is_active' => true,
    ]);

    $service = app(SpeakerSearchService::class);

    expect($service->publicSearchIds('ustazah'))->toContain((string) $speaker->id)
        ->not->toContain((string) $hiddenSpeaker->id);
});

it('keeps local fuzzy speaker search when the database driver is configured', function () {
    config()->set('scout.driver', 'database');

    $speaker = Speaker::factory()->create([
        'name' => 'Samad Al-Bakri',
        'honorific' => null,
        'pre_nominal' => [],
        'post_nominal' => [],
        'qualifications' => [],
        'status' => 'verified',
        'is_active' => true,
    ]);

    $hiddenSpeaker = Speaker::factory()->create([
        'name' => 'Samad Hidden',
        'honorific' => null,
        'pre_nominal' => [],
        'post_nominal' => [],
        'qualifications' => [],
        'status' => 'rejected',
        'is_active' => true,
    ]);

    $service = app(SpeakerSearchService::class);

    expect($service->publicFuzzySearchIds('Smad'))->toContain((string) $speaker->id)
        ->not->toContain((string) $hiddenSpeaker->id);
});

it('uses scout database search for institutions when the database driver is configured', function () {
    config()->set('scout.driver', 'database');

    $institution = Institution::factory()->create([
        'name' => 'Masjid Sultan Salahuddin Abdul Aziz Shah',
        'nickname' => 'Masjid Biru',
        'description' => 'Pusat komuniti',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $hiddenInstitution = Institution::factory()->create([
        'name' => 'Masjid Sultan Salahuddin Hidden',
        'nickname' => 'Masjid Biru',
        'description' => 'Pusat komuniti',
        'status' => 'rejected',
        'is_active' => true,
    ]);

    $service = app(InstitutionSearchService::class);

    expect($service->publicSearchIds('Masjid Biru'))->toContain((string) $institution->id)
        ->not->toContain((string) $hiddenInstitution->id);
});

it('keeps local fuzzy institution search when the database driver is configured', function () {
    config()->set('scout.driver', 'database');

    $institution = Institution::factory()->create([
        'name' => 'Masjid Al Hidayah',
        'description' => 'Kuliah dan komuniti',
        'status' => 'verified',
        'is_active' => true,
    ]);

    $hiddenInstitution = Institution::factory()->create([
        'name' => 'Masjid Hidden',
        'description' => 'Kuliah dan komuniti',
        'status' => 'rejected',
        'is_active' => true,
    ]);

    $service = app(InstitutionSearchService::class);

    expect($service->publicFuzzySearchIds('Hidayh'))->toContain((string) $institution->id)
        ->not->toContain((string) $hiddenInstitution->id);
});
