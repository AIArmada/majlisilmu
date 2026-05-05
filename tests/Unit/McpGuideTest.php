<?php

declare(strict_types=1);

it('documents the app MCP usage guide', function (): void {
    $guide = file_get_contents(dirname(__DIR__, 2).'/docs/MAJLISILMU_MCP_GUIDE.md') ?: '';

    expect($guide)
        ->toContain('/mcp/admin')
        ->toContain('/mcp/member')
        ->toContain('php artisan mcp:token someone@example.com "VS Code Admin MCP" --server=admin')
        ->toContain('php artisan mcp:token someone@example.com "VS Code Member MCP" --server=member')
        ->toContain('php artisan mcp:inspector majlisilmu-admin-local')
        ->toContain('php artisan mcp:inspector majlisilmu-member-local')
        ->toContain('MCP_REDIRECT_DOMAINS')
        ->toContain('MCP_CUSTOM_SCHEMES')
        ->toContain('docs/MAJLISILMU_MCP_ADMIN_AGENT_GUIDE.md')
        ->toContain('docs/MAJLISILMU_MCP_MEMBER_AGENT_GUIDE.md')
        ->toContain('docs/MAJLISILMU_MCP_EVENT_CSV_JSON_CREATION_GUIDE.md')
        ->toContain('docs-admin-mcp-guide')
        ->toContain('docs-admin-event-csv-json-create-guide')
        ->toContain('docs-member-mcp-guide')
        ->not->toContain('MAJLISILMU_MCP_AGENT_GUIDE.md')
        ->not->toContain('docs-mcp-guide');
});
