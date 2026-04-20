<?php

declare(strict_types=1);

use App\Support\Api\Admin\AdminResourceMutationService;
use App\Support\Api\Member\MemberResourceMutationService;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Tests\TestCase;

uses(TestCase::class);

it('keeps the MCP guide aligned with the verified admin and member write-capable resource sets', function () {
    $markdown = file_get_contents(base_path('docs/MAJLISILMU_MCP_GUIDE.md')) ?: '';

    $adminWriteKeys = collect(Filament::getPanel('admin')->getResources())
        ->filter(fn (string $resourceClass): bool => app(AdminResourceMutationService::class)->supports($resourceClass))
        ->map(fn (string $resourceClass): string => Str::kebab(Str::pluralStudly(class_basename($resourceClass::getModel()))))
        ->sort()
        ->values()
        ->all();

    $memberWriteKeys = collect(Filament::getPanel('ahli')->getResources())
        ->filter(fn (string $resourceClass): bool => app(MemberResourceMutationService::class)->supports($resourceClass))
        ->map(fn (string $resourceClass): string => Str::kebab(Str::pluralStudly(class_basename($resourceClass::getModel()))))
        ->sort()
        ->values()
        ->all();

    expect(documentedMcpGuideBulletList($markdown, 'Current structurally write-capable admin resources include:'))
        ->toEqual($adminWriteKeys)
        ->and(documentedMcpGuideBulletList($markdown, 'Current member-write-capable resources include:'))
        ->toEqual($memberWriteKeys)
        ->and($markdown)->toContain('file://docs/MAJLISILMU_MCP_GUIDE.md')
        ->toContain('file://docs/MAJLISILMU_API_MCP_FILAMENT_CRUD_COMPARISON.md');
});

it('keeps the member update tool appendix aligned with the live member MCP schema', function () {
    $markdown = file_get_contents(base_path('docs/MAJLISILMU_MCP_GUIDE.md')) ?: '';

    preg_match('/\| `member-update-record` \|[^\n]+\|([^\n]+)\|/', $markdown, $matches);

    expect($matches[1] ?? null)
        ->toBeString()
        ->toContain('payload')
        ->not->toContain('validate_only');
});

/**
 * @return list<string>
 */
function documentedMcpGuideBulletList(string $markdown, string $lead): array
{
    $quotedLead = preg_quote($lead, '/');

    preg_match('/'.$quotedLead.'\n\n((?:- `[^`]+`\n)+)/', $markdown, $matches);

    expect($matches[1] ?? null)->toBeString();

    preg_match_all('/- `([^`]+)`/', (string) $matches[1], $keys);

    return collect($keys[1] ?? [])->sort()->values()->all();
}
