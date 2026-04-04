<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Admin;

use App\Support\Api\Admin\AdminResourceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class AdminGetWriteSchemaTool extends AbstractAdminWriteTool
{
    protected string $name = 'admin-get-write-schema';

    protected string $description = 'Get the supported write schema for a writable admin resource.';

    public function __construct(
        private readonly AdminResourceService $resourceService,
    ) {}

    public function handle(Request $request): ResponseFactory|Response
    {
        return $this->structuredResponse(function () use ($request): array {
            $actor = $this->authorizeAdmin($request);

            $validated = $request->validate([
                'resource_key' => ['required', 'string'],
                'operation' => ['required', 'string', 'in:create,update'],
                'record_key' => ['nullable', 'string', 'required_if:operation,update'],
            ]);

            return $this->resourceService->writeSchema(
                resourceKey: (string) $validated['resource_key'],
                operation: (string) $validated['operation'],
                recordKey: isset($validated['record_key']) ? (string) $validated['record_key'] : null,
                actor: $actor,
            );
        });
    }

    /**
     * @return array<string, Type>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'resource_key' => $schema->string()->required()->min(1),
            'operation' => $schema->string()->required()->enum(['create', 'update']),
            'record_key' => $schema->string()->nullable(),
        ];
    }
}
