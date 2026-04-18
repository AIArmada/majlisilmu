<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\Admin\AdminCreateRecordTool;
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
#[Instructions('Authenticated admin MCP server with parity to the Filament admin resource API for listing resources, reading records, traversing relations, discovering MCP write schemas, and writing supported non-media fields for event, institution, speaker, venue, reference, and subdistrict records.')]
class AdminServer extends Server
{
    protected array $tools = [
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
