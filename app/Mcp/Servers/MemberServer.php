<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Prompts\DocumentationToolRoutingPrompt;
use App\Mcp\Resources\Docs\McpGuideResource;
use App\Mcp\Tools\Member\MemberDocumentationFetchTool;
use App\Mcp\Tools\Member\MemberDocumentationSearchTool;
use App\Mcp\Tools\Member\MemberGetRecordTool;
use App\Mcp\Tools\Member\MemberGetResourceMetaTool;
use App\Mcp\Tools\Member\MemberGetWriteSchemaTool;
use App\Mcp\Tools\Member\MemberListRecordsTool;
use App\Mcp\Tools\Member\MemberListResourcesTool;
use App\Mcp\Tools\Member\MemberUpdateRecordTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('majlisilmu-member')]
#[Version('1.0.0')]
#[Instructions('Authenticated member MCP server for Ahli-scoped institutions, speakers, references, and related events. Supports scoped resource discovery, record reads, and schema-guided updates for writable Ahli records when the authenticated member has the matching scoped permissions. Media fields use JSON base64 file descriptors when advertised by the schema. The server exposes one verified MCP guide as a raw markdown resource, read-only `search` / `fetch` documentation tools for tool-centric clients such as ChatGPT and the OpenAI Responses MCP integration, plus a `documentation-tool-routing` prompt that explains when to use the guide and the documentation tools. Designed for bearer-token clients such as VS Code, ChatGPT, Gemini, Grok, Claude, and Opencode.')]
class MemberServer extends Server
{
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
        MemberGetRecordTool::class,
        MemberGetWriteSchemaTool::class,
        MemberUpdateRecordTool::class,
    ];
}
