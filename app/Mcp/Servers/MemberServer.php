<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Prompts\DocumentationToolRoutingPrompt;
use App\Mcp\Resources\Docs\McpGuideResource;
use App\Mcp\Tools\Member\MemberApproveContributionRequestTool;
use App\Mcp\Tools\Member\MemberCancelContributionRequestTool;
use App\Mcp\Tools\Member\MemberCancelMembershipClaimTool;
use App\Mcp\Tools\Member\MemberCreateGitHubIssueTool;
use App\Mcp\Tools\Member\MemberDocumentationFetchTool;
use App\Mcp\Tools\Member\MemberDocumentationSearchTool;
use App\Mcp\Tools\Member\MemberGetRecordActionsTool;
use App\Mcp\Tools\Member\MemberGetRecordTool;
use App\Mcp\Tools\Member\MemberGetResourceMetaTool;
use App\Mcp\Tools\Member\MemberGetWriteSchemaTool;
use App\Mcp\Tools\Member\MemberListContributionRequestsTool;
use App\Mcp\Tools\Member\MemberListMembershipClaimsTool;
use App\Mcp\Tools\Member\MemberListRecordsTool;
use App\Mcp\Tools\Member\MemberListRelatedRecordsTool;
use App\Mcp\Tools\Member\MemberListResourcesTool;
use App\Mcp\Tools\Member\MemberRejectContributionRequestTool;
use App\Mcp\Tools\Member\MemberSubmitMembershipClaimTool;
use App\Mcp\Tools\Member\MemberUpdateRecordTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('majlisilmu-member')]
#[Version('1.0.0')]
#[Instructions('Authenticated member MCP server for Ahli-scoped institutions, speakers, references, and related events. Supports scoped resource discovery, record reads, record-specific next-step MCP actions, schema-guided updates for writable Ahli records when the authenticated member has the matching scoped permissions, plus member-side workflow tools for contribution-request queues and membership claims that mirror the Ahli dashboard. Media fields use JSON base64 file descriptors when advertised by the schema. Entity-selection hint for record search: institution-type nouns (`masjid`, `surau`, `madrasah`, `maahad`, `pondok`, `sekolah`, `kolej`, `universiti`) should be searched as `institutions` first; venue-type nouns (`dewan`, `auditorium`, `stadium`, `perpustakaan`, `padang`, `hotel`) should be searched as `venues` first; `spaces` are finer-grained sublocations inside institutions and should not be the default first lookup for named mosques or surau. The server exposes one verified MCP guide as a raw markdown resource, read-only `search` / `fetch` documentation tools for tool-centric clients such as ChatGPT and the OpenAI Responses MCP integration, plus a `documentation-tool-routing` prompt that explains when to use the guide and the documentation tools. Designed for bearer-token clients such as VS Code, ChatGPT, Gemini, Grok, Claude, and Opencode.')]
class MemberServer extends Server
{
    public int $defaultPaginationLength = 50;

    protected array $resources = [
        McpGuideResource::class,
    ];

    protected array $prompts = [
        DocumentationToolRoutingPrompt::class,
    ];

    protected array $tools = [
        MemberDocumentationSearchTool::class,
        MemberDocumentationFetchTool::class,
        MemberListResourcesTool::class,
        MemberGetResourceMetaTool::class,
        MemberListRecordsTool::class,
        MemberListRelatedRecordsTool::class,
        MemberGetRecordTool::class,
        MemberGetRecordActionsTool::class,
        MemberGetWriteSchemaTool::class,
        MemberUpdateRecordTool::class,
        MemberListContributionRequestsTool::class,
        MemberApproveContributionRequestTool::class,
        MemberRejectContributionRequestTool::class,
        MemberCancelContributionRequestTool::class,
        MemberListMembershipClaimsTool::class,
        MemberSubmitMembershipClaimTool::class,
        MemberCancelMembershipClaimTool::class,
        MemberCreateGitHubIssueTool::class,
    ];
}
