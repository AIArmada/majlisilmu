<?php

declare(strict_types=1);

namespace App\Mcp\Resources\Docs;

class CrudComparisonResource extends MarkdownDocumentResource
{
    protected string $name = 'docs-crud-capability-matrix';

    protected string $title = 'MajlisIlmu API / MCP / Filament Capability Matrix';

    protected string $description = 'Verified markdown capability matrix for public/authenticated API workflows, generic admin HTTP API, admin/member MCP, and Filament panel CRUD boundaries.';

    protected string $uri = 'file://docs/MAJLISILMU_API_MCP_FILAMENT_CRUD_COMPARISON.md';

    protected function documentRelativePath(): string
    {
        return 'docs/MAJLISILMU_API_MCP_FILAMENT_CRUD_COMPARISON.md';
    }
}
