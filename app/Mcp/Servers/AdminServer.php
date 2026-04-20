<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Prompts\DocumentationToolRoutingPrompt;
use App\Mcp\Resources\Docs\McpGuideResource;
use App\Mcp\Tools\Admin\AdminCreateRecordTool;
use App\Mcp\Tools\Admin\AdminDocumentationFetchTool;
use App\Mcp\Tools\Admin\AdminDocumentationSearchTool;
use App\Mcp\Tools\Admin\AdminGetRecordTool;
use App\Mcp\Tools\Admin\AdminGetResourceMetaTool;
use App\Mcp\Tools\Admin\AdminGetWriteSchemaTool;
use App\Mcp\Tools\Admin\AdminListRecordsTool;
use App\Mcp\Tools\Admin\AdminListRelatedRecordsTool;
use App\Mcp\Tools\Admin\AdminListResourcesTool;
use App\Mcp\Tools\Admin\AdminUpdateRecordTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('majlisilmu-admin')]
#[Version('1.0.0')]
#[Instructions('Authenticated admin MCP server with parity to the Filament admin resource API for listing resources, reading records, traversing relations, discovering MCP write schemas, and writing supported fields for event, institution, speaker, venue, reference, and subdistrict records. Media fields use JSON base64 file descriptors when advertised by the schema. The server exposes one verified MCP guide as a raw markdown resource, read-only `search` / `fetch` documentation tools for tool-centric clients such as ChatGPT and the OpenAI Responses MCP integration, plus a `documentation-tool-routing` prompt that explains when to use the guide and the documentation tools.')]
class AdminServer extends Server
{
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
        AdminGetWriteSchemaTool::class,
        AdminCreateRecordTool::class,
        AdminUpdateRecordTool::class,
    ];
}
