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
    protected string $name = 'docs-mcp-guide';

    protected string $title = 'MajlisIlmu MCP Guide';

    protected string $description = 'Verified markdown guide for MajlisIlmu MCP auth, transport rules, discovery primitives, capability matrix, media rules, and current admin/member write behavior.';

    protected string $uri = 'file://docs/MAJLISILMU_MCP_GUIDE.md';

    protected function documentRelativePath(): string
    {
        return 'docs/MAJLISILMU_MCP_GUIDE.md';
    }
}
