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
        ->toContain('data.record.attributes.active_change_notice')
        ->toContain('data.record.attributes.change_announcements')
        ->toContain('data.record.attributes.replacement_event')
        ->toContain('omit stale unreachable replacements rather than exposing dead public links')
        ->toContain('Tool-centric clients like ChatGPT and the OpenAI Responses MCP integration import tools from `tools/list`, not raw resources from `resources/list`.')
        ->toContain('the public `GET /api/v1/references` directory now exists for native reference browsing, but MCP still uses the existing generic `admin-list-records` and `member-list-records` flows for the `references` resource')
        ->toContain('| `search` | Search the verified MCP/docs pages exposed by this server | `query` |')
        ->toContain('| `fetch` | Fetch the full text of one verified docs page | `id` |')
        ->toContain('| `documentation-tool-routing` | Short guidance for deciding when to use `search` vs `fetch` for the verified docs pages | `topic?` |')
        ->toContain('fetch `docs-mcp-guide` directly when the question is clearly about MajlisIlmu MCP docs')
        ->toContain('optionally accept a `topic` hint such as `crud`, `auth`, `media uploads`, `runtime records`, `search`, or `fetch` for more targeted guidance')
        ->toContain('The broader internal cross-surface parity docs (`MAJLISILMU_API_MCP_FILAMENT_CRUD_COMPARISON.*`) are intentionally not exposed through MCP.')
        ->toContain('### MCP capability matrix')
        ->toContain('| Related-record traversal | `admin-list-related-records` | `member-list-related-records` |')
        ->toContain('| Record action guidance | `admin-get-record-actions` | `member-get-record-actions` |')
        ->toContain('| Explicit workflow schema discovery | `admin-get-event-moderation-schema`, `admin-get-report-triage-schema`, `admin-get-contribution-request-review-schema`, `admin-get-membership-claim-review-schema` | Not exposed |')
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
        ->toContain('### Record action guidance')
        ->toContain('Use `admin-get-record-actions` or `member-get-record-actions` when you already have a specific record and want the shortest model-visible list of next MCP calls.')
        ->toContain('### Explicit workflow schema tools')
        ->toContain('Current explicit workflow schema tools are `admin-get-event-moderation-schema`, `admin-get-report-triage-schema`, `admin-get-contribution-request-review-schema`, and `admin-get-membership-claim-review-schema`.')
        ->toContain('| `admin-get-record-actions` | Get focused next-step MCP actions for one admin record | `resource_key`, `record_key` |')
        ->toContain('| `admin-list-records` | Search and paginate records for one admin resource | `resource_key`, `search?`, `filters?`, `starts_after?`, `starts_before?`, `starts_on_local_date?`, `page?`, `per_page?` |')
        ->toContain('| `admin-get-event-moderation-schema` | Fetch the explicit moderation schema for one event | `record_key` |')
        ->toContain('| `admin-get-report-triage-schema` | Fetch the explicit triage schema for one report | `record_key` |')
        ->toContain('| `admin-get-contribution-request-review-schema` | Fetch the explicit review schema for one contribution request | `record_key` |')
        ->toContain('| `admin-get-membership-claim-review-schema` | Fetch the explicit review schema for one membership claim | `record_key` |')
        ->toContain('| `member-get-record-actions` | Get focused next-step MCP actions for one member record | `resource_key`, `record_key` |')
        ->toContain('| `admin-moderate-event` | Run one explicit moderation action on an event | `record_key`, `action`, `reason_code?`, `note?` |')
        ->toContain('| `admin-triage-report` | Run one explicit triage action on a report | `record_key`, `action`, `resolution_note?` |')
        ->toContain('| `admin-review-contribution-request` | Approve or reject one pending contribution request | `record_key`, `action`, `reason_code?`, `reviewer_note?` |')
        ->toContain('| `member-list-contribution-requests` | List the authenticated member\'s contribution queue and pending approvals | none |')
        ->toContain('| `member-submit-membership-claim` | Submit a membership claim with evidence uploads | `subject_type`, `subject`, `justification`, `evidence` |')
        ->toContain('Institution write schemas now expose additional field semantics for `nickname`, `address`, `contacts`, and `social_media`.')
        ->toContain('For institutions, omitted `contacts` / `social_media` preserve the existing collection, `null` or `[]` clear it, and any submitted array replaces the stored collection.')
        ->toContain('On direct MCP institution writes, `nickname: null` preserves the current stored nickname while `nickname: ""` reaches the mutation layer and clears the stored nickname to `null`.')
        ->toContain('Speaker write schemas now expose additional field semantics for `address`, `honorific`, `pre_nominal`, `post_nominal`, `qualifications`, `language_ids`, `contacts`, and `social_media`.')
        ->toContain('If you send `address`, include `address.country_id`; `address: {}` is invalid on the MCP write path just like the raw admin HTTP API.')
        ->toContain('Venue write schemas now expose additional field semantics for `address`, `facilities`, `contacts`, and `social_media`.')
        ->toContain('Enum fields and filters use enum backing values, not display labels.')
        ->toContain('Event enum filters and payload values must be backing values, for example `filter[event_type]=kuliah_ceramah` and `filter[timing_mode]=prayer_relative`.')
        ->toContain('`address: {}` deletes the stored venue address on the shared save path.')
        ->toContain('Reference write schemas now expose additional field semantics for `author`, `publication_year`, `publisher`, and `social_media`.')
        ->toContain('Event write schemas now expose additional field semantics for `event_url`, `live_url`, `recording_url`, `languages`, `references`, `series`, `domain_tags`, `discipline_tags`, `source_tags`, `issue_tags`, `speakers`, `other_key_people`, `organizer_type`, and `registration_mode`.')
        ->toContain('For events, update schemas are sparse: omitted scalar and relation fields preserve the current value via server-side form-state merge')
        ->toContain('Event record detail payloads also expose the public change-surface projection fields `active_change_notice`, `change_announcements`, and `replacement_event`')
        ->toContain('Member event update schemas inherit the same event semantics because the member MCP surface delegates to the shared admin write service.')
        ->toContain('Series write schemas now expose additional field semantics for `description`, `languages`, and `slug`.')
        ->toContain('For series, `title`, `slug`, and `visibility` remain required on update; `description` clears on `null` / trimmed empty input')
        ->toContain('Donation channel write schemas now expose additional field semantics for `donatable_type`, `method`, `label`, `reference_note`, and the method-specific bank / DuitNow / ewallet fields.')
        ->toContain('For donation channels, owner-type aliases normalize to canonical stored morph values, method switches clear unrelated field groups, and destructive QR clear flags remain unsupported through MCP.')
        ->toContain('Inspiration write schemas now expose additional field semantics for `content`, `source`, and `main`.')
        ->toContain('For inspirations, `category`, `locale`, `title`, and `content` remain required on update; `source` clears on `null` / trimmed empty input')
        ->toContain('Space write schemas now expose additional field semantics for `slug`, `capacity`, and `institutions`.')
        ->toContain('For spaces, `name` and `slug` remain required on update, `capacity` clears to `null`, and `institutions` follows omit-preserve / null-clear / array-replace relation sync semantics.')
        ->toContain('Report write schemas now expose additional field semantics for `entity_type`, `entity_id`, `category`, `description`, `reporter_id`, `handled_by`, `resolution_note`, and `evidence`.')
        ->toContain('For reports, `entity_type`, `entity_id`, `category`, and `status` remain required on update')
        ->toContain('`evidence` preserves on omission or `null` but clears on `[]`.')
        ->toContain('Tag write schemas now expose additional field semantics for `name`, `name.ms`, `name.en`, and `order_column`.')
        ->toContain('For tags, `name.en` falls back to `name.ms` when it is omitted, `null`, or trimmed empty input, and blank / null `order_column` values trigger sortable recomputation instead of storing `null`.')
        ->toContain('Subdistrict write schemas now expose additional field semantics for `country_id`, `state_id`, `district_id`, and `name`.')
        ->toContain('For subdistricts, `country_id`, `state_id`, and `name` remain required on update, `name` is trimmed')
        ->toContain('`admin-list-records` accepts a `filters` object keyed by the resource metadata filter keys, for example `{ "status": "approved", "is_active": true }` for `events`.')
        ->toContain('For date-aware resources, `starts_after`, `starts_before`, and `starts_on_local_date` are date-only `YYYY-MM-DD` strings interpreted in the resolved request timezone. Do not send ISO 8601 timestamps to those MCP arguments.')
        ->toContain('validated MCP payloads should still use the canonical platform value `twitter`, not `x`.');

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
