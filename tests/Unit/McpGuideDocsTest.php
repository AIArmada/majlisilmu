<?php

declare(strict_types=1);

use App\Enums\InstitutionType;
use App\Enums\VenueType;
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
        ->toContain('Tool-centric clients like ChatGPT and the OpenAI Responses MCP integration import tools from `tools/list`, not raw resources from `resources/list`.')
        ->toContain('| `search` | Search the verified MCP/docs pages exposed by this server | `query` |')
        ->toContain('| `fetch` | Fetch the full text of one verified docs page | `id` |')
        ->toContain('| `documentation-tool-routing` | Short guidance for deciding when to use `search` vs `fetch` for the verified docs pages | `topic?` |')
        ->toContain('fetch `docs-mcp-guide` directly when the question is clearly about MajlisIlmu MCP docs')
        ->toContain('optionally accept a `topic` hint such as `crud`, `auth`, `media uploads`, `runtime records`, `search`, or `fetch` for more targeted guidance')
        ->toContain('The broader internal cross-surface parity docs (`MAJLISILMU_API_MCP_FILAMENT_CRUD_COMPARISON.*`) are intentionally not exposed through MCP.')
        ->toContain('### MCP capability matrix')
        ->toContain('| Related-record traversal | `admin-list-related-records` | `member-list-related-records` |')
        ->toContain('| Event moderation | `admin-moderate-event` | Not exposed |')
        ->toContain('| Report triage | `admin-triage-report` | Not exposed |')
        ->toContain('| Contribution-request workflows | `admin-review-contribution-request` | `member-list-contribution-requests`, `member-approve-contribution-request`, `member-reject-contribution-request`, `member-cancel-contribution-request` |')
        ->toContain('| Membership-claim workflows | `admin-review-membership-claim` | `member-list-membership-claims`, `member-submit-membership-claim`, `member-cancel-membership-claim` |')
        ->toContain('| `donation-channels` | list/get/meta + schema + create + update + preview | Not exposed |')
        ->toContain('| `inspirations` | list/get/meta + schema + create + update + preview | Not exposed |')
        ->toContain('| `reports` | list/get/meta + schema + create + update + preview | Not exposed |')
        ->toContain('| `series` | list/get/meta + schema + create + update + preview | Not exposed |')
        ->toContain('| `spaces` | list/get/meta + schema + create + update + preview | Not exposed |')
        ->toContain('| `venues` | list/get/meta + schema + create + update + preview | Not exposed |')
        ->toContain('### Entity selection heuristics for record search')
        ->toContain('`spaces` as finer-grained sublocations inside an institution')
        ->toContain('`Masjid Abidin` should be searched in `institutions` first.')
        ->toContain('### Quick search playbook')
        ->toContain('Mosque, surau, school, campus, madrasah, maahad, pondok, or university-style names → search `institutions` first.')
        ->toContain('Standalone hall/dewan, auditorium, stadium, library, field, or hotel-style names → search `venues` first.')
        ->toContain('resolve the parent `institution` first, then use `spaces` for the internal location when needed.')
        ->toContain('| `admin-moderate-event` | Run one explicit moderation action on an event | `record_key`, `action`, `reason_code?`, `note?` |')
        ->toContain('| `admin-triage-report` | Run one explicit triage action on a report | `record_key`, `action`, `resolution_note?` |')
        ->toContain('| `admin-review-contribution-request` | Approve or reject one pending contribution request | `record_key`, `action`, `reason_code?`, `reviewer_note?` |')
        ->toContain('| `member-list-contribution-requests` | List the authenticated member\'s contribution queue and pending approvals | none |')
        ->toContain('| `member-submit-membership-claim` | Submit a membership claim with evidence uploads | `subject_type`, `subject`, `justification`, `evidence` |');

    foreach (InstitutionType::cases() as $type) {
        expect($markdown)->toContain('`'.$type->value.'`');
    }

    foreach (VenueType::cases() as $type) {
        expect($markdown)->toContain('`'.$type->value.'`');
    }
});

it('keeps the member update tool appendix aligned with the live member MCP schema', function () {
    $markdown = file_get_contents(base_path('docs/MAJLISILMU_MCP_GUIDE.md')) ?: '';

    preg_match('/\| `member-update-record` \|[^\n]+\|([^\n]+)\|/', $markdown, $matches);

    expect($matches[1] ?? null)
        ->toBeString()
        ->toContain('payload')
        ->toContain('validate_only')
        ->and($markdown)->toContain('Member update tools support `validate_only=true` for preview-only member writes.')
        ->toContain('When a write tool supports `validate_only=true` (currently admin create/update and member update), previews normalize descriptors into file summaries without persisting media.')
        ->toContain('apply_defaults=true')
        ->toContain('schema-driven `feedback` issues with suggested values, defaults, and conditional `required_because` context.')
        ->toContain('Validation failures in validate-only mode now include `fix_plan`, `remaining_blockers`, `normalized_payload_preview`, and `can_retry` so tool clients can recover in one retry loop.');
});

/**
 * @return list<string>
 */
function documentedMcpGuideBulletList(string $markdown, string $lead): array
{
    $quotedLead = preg_quote($lead, '/');

    preg_match('/'.$quotedLead.'\n\n((?:- `[^`]+`\n)+)/', $markdown, $matches);

    expect($matches[1] ?? null)->toBeString();

    preg_match_all('/- `([^`]+)`/', $matches[1], $keys);

    return collect($keys[1] ?? [])->sort()->values()->all();
}
