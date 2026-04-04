<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Admin;

use App\Support\Api\Admin\AdminResourceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class AdminCreateRecordTool extends AbstractAdminWriteTool
{
    protected string $name = 'admin-create-record';

    protected string $description = 'Create a supported writable admin resource record.';

    public function __construct(
        private readonly AdminResourceService $resourceService,
    ) {}

    public function handle(Request $request): ResponseFactory|Response
    {
        return $this->structuredResponse(function () use ($request): array {
            $actor = $this->authorizeAdmin($request);

            $validated = $this->validateArguments($request, [
                'resource_key' => ['required', 'string'],
                'payload' => ['required', 'array'],
            ]);

            /** @var array<string, mixed> $payload */
            $payload = $validated['payload'];

            $this->ensureMediaUploadsAreUnsupported($payload);

            return $this->resourceService->storeRecord(
                resourceKey: (string) $validated['resource_key'],
                payload: $payload,
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
            'payload' => $schema->object()->required(),
        ];
    }
}
