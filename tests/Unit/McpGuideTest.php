<?php

declare(strict_types=1);

it('documents the app MCP usage guide', function (): void {
    $guide = file_get_contents(dirname(__DIR__, 2).'/docs/MAJLISILMU_MCP_GUIDE.md');

    expect($guide)->toBeString()
        ->toContain(
            '/mcp/admin',
            '/mcp/member',
            'php artisan mcp:token',
            'php artisan mcp:inspector',
            'MCP_REDIRECT_DOMAINS',
            'MCP_CUSTOM_SCHEMES',
            'Dynamic Client Registration',
            'mcp:use',
            'OpenID support',
        );
});
