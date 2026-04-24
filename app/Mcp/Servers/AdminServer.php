<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Methods\CallToolWithDocumentationPreflight;
use App\Mcp\Methods\ReadResourceWithDocumentationPreflight;
use App\Mcp\Prompts\DocumentationToolRoutingPrompt;
use App\Mcp\Resources\Docs\McpGuideResource;
use App\Mcp\Tools\Admin\AdminCreateGitHubIssueTool;
use App\Mcp\Tools\Admin\AdminCreateRecordTool;
use App\Mcp\Tools\Admin\AdminDocumentationFetchTool;
use App\Mcp\Tools\Admin\AdminDocumentationSearchTool;
use App\Mcp\Tools\Admin\AdminGetContributionRequestReviewSchemaTool;
use App\Mcp\Tools\Admin\AdminGetEventModerationSchemaTool;
use App\Mcp\Tools\Admin\AdminGetMembershipClaimReviewSchemaTool;
use App\Mcp\Tools\Admin\AdminGetRecordActionsTool;
use App\Mcp\Tools\Admin\AdminGetRecordTool;
use App\Mcp\Tools\Admin\AdminGetReportTriageSchemaTool;
use App\Mcp\Tools\Admin\AdminGetResourceMetaTool;
use App\Mcp\Tools\Admin\AdminGetWriteSchemaTool;
use App\Mcp\Tools\Admin\AdminListRecordsTool;
use App\Mcp\Tools\Admin\AdminListRelatedRecordsTool;
use App\Mcp\Tools\Admin\AdminListResourcesTool;
use App\Mcp\Tools\Admin\AdminModerateEventTool;
use App\Mcp\Tools\Admin\AdminReviewContributionRequestTool;
use App\Mcp\Tools\Admin\AdminReviewMembershipClaimTool;
use App\Mcp\Tools\Admin\AdminTriageReportTool;
use App\Mcp\Tools\Admin\AdminUpdateRecordTool;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('majlisilmu-admin')]
#[Version('1.0.0')]
#[Instructions('Authenticated admin MCP server with parity to the Filament admin resource API for listing resources, reading records, traversing relations, getting record-specific next-step MCP actions, discovering MCP write schemas, getting explicit workflow schema descriptors for event moderation, report triage, contribution-request review, and membership-claim review, writing supported fields for donation-channel, event, inspiration, institution, report, speaker, venue, reference, subdistrict, tag, series, and space records, and running explicit workflow actions for those admin review surfaces. Media fields use JSON base64 file descriptors when advertised by the schema. Entity-selection hint for record search: institution-type nouns (`masjid`, `surau`, `madrasah`, `maahad`, `pondok`, `sekolah`, `kolej`, `universiti`) should be searched as `institutions` first; venue-type nouns (`dewan`, `auditorium`, `stadium`, `perpustakaan`, `padang`, `hotel`) should be searched as `venues` first; `spaces` are finer-grained sublocations inside institutions and should not be the default first lookup for named mosques or surau. The server exposes one verified admin MCP guide as a raw markdown resource, read-only `search` / `fetch` documentation tools for tool-centric clients such as ChatGPT and the OpenAI Responses MCP integration, plus a `documentation-tool-routing` prompt that explains when to use the guide and the documentation tools. Operational MCP tool calls are rejected until `docs-admin-mcp-guide` has been fetched or the guide resource has been read in the current initialized MCP session.')]
class AdminServer extends MajlisIlmuServer
{
    public int $defaultPaginationLength = 50;

    #[\Override]
    protected function boot(): void
    {
        $this->addCapability(self::CAPABILITY_COMPLETIONS);
        $this->addMethod('tools/call', CallToolWithDocumentationPreflight::class);
        $this->addMethod('resources/read', ReadResourceWithDocumentationPreflight::class);
    }

    protected array $resources = [
        McpGuideResource::class,
    ];

    protected array $prompts = [
        DocumentationToolRoutingPrompt::class,
    ];

    protected array $tools = [
        AdminDocumentationSearchTool::class,
        AdminDocumentationFetchTool::class,
        AdminListResourcesTool::class,
        AdminGetResourceMetaTool::class,
        AdminListRecordsTool::class,
        AdminListRelatedRecordsTool::class,
        AdminGetRecordTool::class,
        AdminGetRecordActionsTool::class,
        AdminGetWriteSchemaTool::class,
        AdminGetEventModerationSchemaTool::class,
        AdminGetReportTriageSchemaTool::class,
        AdminGetContributionRequestReviewSchemaTool::class,
        AdminGetMembershipClaimReviewSchemaTool::class,
        AdminCreateRecordTool::class,
        AdminCreateGitHubIssueTool::class,
        AdminModerateEventTool::class,
        AdminTriageReportTool::class,
        AdminReviewContributionRequestTool::class,
        AdminReviewMembershipClaimTool::class,
        AdminUpdateRecordTool::class,
    ];
}
