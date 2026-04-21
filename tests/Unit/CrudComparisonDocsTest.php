<?php

declare(strict_types=1);

use App\Support\Api\Admin\AdminResourceMutationService;
use App\Support\Api\Member\MemberResourceMutationService;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

it('keeps the CRUD comparison JSON aligned with runtime panel resources and write support', function () {
    /** @var array<string, mixed> $document */
    $document = json_decode(
        file_get_contents(base_path('docs/MAJLISILMU_API_MCP_FILAMENT_CRUD_COMPARISON.json')) ?: '[]',
        true,
        512,
        JSON_THROW_ON_ERROR,
    );

    $adminMutationService = app(AdminResourceMutationService::class);
    $memberMutationService = app(MemberResourceMutationService::class);

    $runtimeAdminResources = collect(Filament::getPanel('admin')->getResources())
        ->map(function (string $resourceClass) use ($adminMutationService): array {
            $modelClass = $resourceClass::getModel();
            $path = (new ReflectionClass($resourceClass))->getFileName() ?: '';

            return [
                'resource_key' => Str::kebab(Str::pluralStudly(class_basename($modelClass))),
                'resource_class' => $resourceClass,
                'model_class' => $modelClass,
                'source' => str_starts_with($path, app_path())
                    ? 'app'
                    : (str_starts_with($path, base_path('vendor')) ? 'vendor' : 'other'),
                'page_keys' => array_values(array_keys($resourceClass::getPages())),
                'generic_admin_write' => $adminMutationService->supports($resourceClass),
            ];
        })
        ->sortBy('resource_key')
        ->values()
        ->all();

    $runtimeAhliResources = collect(Filament::getPanel('ahli')->getResources())
        ->map(function (string $resourceClass) use ($memberMutationService): array {
            $modelClass = $resourceClass::getModel();

            return [
                'resource_key' => Str::kebab(Str::pluralStudly(class_basename($modelClass))),
                'resource_class' => $resourceClass,
                'model_class' => $modelClass,
                'page_keys' => array_values(array_keys($resourceClass::getPages())),
                'generic_member_write' => $memberMutationService->supports($resourceClass),
            ];
        })
        ->sortBy('resource_key')
        ->values()
        ->all();

    $documentedAdminResources = collect(data_get($document, 'runtime_inventory.admin_panel.resources', []))
        ->sortBy('resource_key')
        ->values()
        ->all();

    $documentedAhliResources = collect(data_get($document, 'runtime_inventory.ahli_panel.resources', []))
        ->sortBy('resource_key')
        ->values()
        ->all();

    expect($document['schema_version'])->toBe('2.1.0')
        ->and($document['markdown_companion'])->toBe('docs/MAJLISILMU_API_MCP_FILAMENT_CRUD_COMPARISON.md')
        ->and(data_get($document, 'runtime_inventory.admin_panel.resource_count'))->toBe(count($runtimeAdminResources))
        ->and(data_get($document, 'runtime_inventory.ahli_panel.resource_count'))->toBe(count($runtimeAhliResources))
        ->and($documentedAdminResources)->toEqual($runtimeAdminResources)
        ->and($documentedAhliResources)->toEqual($runtimeAhliResources)
        ->and(data_get($document, 'generic_admin_write_support.resource_keys'))->toEqual(
            collect($runtimeAdminResources)
                ->filter(fn (array $resource): bool => $resource['generic_admin_write'])
                ->pluck('resource_key')
                ->values()
                ->all()
        )
        ->and(data_get($document, 'generic_member_write_support.resource_keys'))->toEqual(
            collect($runtimeAhliResources)
                ->filter(fn (array $resource): bool => $resource['generic_member_write'])
                ->pluck('resource_key')
                ->values()
                ->all()
        )
        ->and(data_get($document, 'transport_rules.admin_mcp.media_upload_transport'))->toBe('json_base64_descriptor')
        ->and(data_get($document, 'transport_rules.admin_mcp.destructive_clear_flags_supported'))->toBeFalse()
        ->and(data_get($document, 'transport_rules.admin_api.apply_defaults_preview'))->toBeTrue()
        ->and(data_get($document, 'transport_rules.admin_api.validation_feedback'))->toBeTrue()
        ->and(data_get($document, 'transport_rules.admin_mcp.apply_defaults'))->toBeTrue()
        ->and(data_get($document, 'transport_rules.admin_mcp.validation_feedback'))->toBeTrue()
        ->and(data_get($document, 'transport_rules.member_mcp.validate_only'))->toBeFalse();
});

it('keeps the markdown companion anchored to the verified runtime model', function () {
    $markdown = file_get_contents(base_path('docs/MAJLISILMU_API_MCP_FILAMENT_CRUD_COMPARISON.md')) ?: '';

    expect($markdown)
        ->toContain('Runtime panel registration wins.')
        ->toContain('Runtime admin resource inventory (30 registered resources)')
        ->toContain('Runtime Ahli resource inventory (4 registered resources)')
        ->toContain('MCP media and preview semantics')
        ->toContain('apply_defaults=1 is supported during preview requests')
        ->toContain('apply_defaults is supported on admin preview tools')
        ->toContain('json_base64_descriptor')
        ->toContain('No `validate_only` preview path today.');
});
