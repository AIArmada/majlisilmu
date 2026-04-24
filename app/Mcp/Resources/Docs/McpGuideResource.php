<?php

declare(strict_types=1);

namespace App\Mcp\Resources\Docs;

use Laravel\Mcp\Enums\Role;
use Laravel\Mcp\Server\Annotations\Audience;
use Laravel\Mcp\Server\Annotations\Priority;

#[Audience([Role::User, Role::Assistant])]
#[Priority(1.0)]
class McpGuideResource extends MarkdownDocumentResource
{
    protected string $name = 'docs-admin-mcp-guide';

    protected string $title = 'MajlisIlmu Admin MCP Agent Guide';

    protected string $description = 'Verified markdown guide for admin MCP agent consumption: auth, transport, discovery primitives, capability matrix, writable resources, and workflow guidance.';

    protected string $uri = 'file://docs/MAJLISILMU_MCP_ADMIN_AGENT_GUIDE.md';

    protected function documentRelativePath(): string
    {
        return 'docs/MAJLISILMU_MCP_ADMIN_AGENT_GUIDE.md';
    }
}
