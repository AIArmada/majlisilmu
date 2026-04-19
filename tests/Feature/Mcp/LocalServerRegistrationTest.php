<?php

use Laravel\Mcp\Facades\Mcp;

it('registers local MCP server handles for testing', function (): void {
    $servers = Mcp::servers();

    expect(array_key_exists('majlisilmu-admin-local', $servers))->toBeTrue();
    expect(array_key_exists('majlisilmu-member-local', $servers))->toBeTrue();

    expect(Mcp::getLocalServer('majlisilmu-admin-local'))->not->toBeNull();
    expect(Mcp::getLocalServer('majlisilmu-member-local'))->not->toBeNull();

    expect(Mcp::getWebServer('mcp/admin'))->not->toBeNull();
    expect(Mcp::getWebServer('mcp/member'))->not->toBeNull();
});
