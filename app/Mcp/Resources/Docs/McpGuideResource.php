<?php

declare(strict_types=1);

namespace App\Mcp\Resources\Docs;

class McpGuideResource extends MarkdownDocumentResource
{
    protected string $name = 'docs-mcp-guide';

    protected string $title = 'MajlisIlmu MCP Guide';

    protected string $description = 'Verified markdown guide for MajlisIlmu MCP auth, transport rules, resource discovery, and current admin/member CRUD behavior.';

    protected string $uri = 'file://docs/MAJLISILMU_MCP_GUIDE.md';

    protected function documentRelativePath(): string
    {
        return 'docs/MAJLISILMU_MCP_GUIDE.md';
    }
}
