<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Admin;

use App\Support\Api\Admin\AdminResourceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class AdminListResourcesTool extends AbstractAdminTool
{
    protected string $name = 'admin-list-resources';

    protected string $description = 'List Filament admin resources the authenticated admin can access.';

    public function __construct(
        private readonly AdminResourceService $resourceService,
    ) {}

    public function handle(Request $request): ResponseFactory|Response
    {
        return $this->structuredResponse(function () use ($request): array {
            $this->authorizeAdmin($request);

            return $this->resourceService->manifest();
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
