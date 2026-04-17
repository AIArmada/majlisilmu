<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Member;

use App\Support\Api\Member\MemberResourceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

class MemberUpdateRecordTool extends AbstractMemberWriteTool
{
    protected string $name = 'member-update-record';

    protected string $description = 'Update one writable Ahli-scoped member resource record.';

    public function __construct(
        private readonly MemberResourceService $resourceService,
    ) {}

    public function handle(Request $request): ResponseFactory|Response
    {
        return $this->structuredResponse(function () use ($request): array {
            $actor = $this->authorizeMember($request);

            $validated = $this->validateArguments($request, [
                'resource_key' => ['required', 'string'],
                'record_key' => ['required', 'string'],
                'payload' => ['required', 'array'],
            ]);

            /** @var array<string, mixed> $payload */
            $payload = $validated['payload'];
            $resourceKey = (string) $validated['resource_key'];

            $this->ensureMediaUploadsAreUnsupported($payload);
            $payload = $this->normalizePayloadForWriteTool($resourceKey, $payload);

            return $this->resourceService->updateRecord(
                resourceKey: $resourceKey,
                recordKey: (string) $validated['record_key'],
                payload: $payload,
                actor: $actor,
            );
        });
    }

    /**
     * @return array<string, Type>
     */
    #[\Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'resource_key' => $schema->string()->required()->min(1),
            'record_key' => $schema->string()->required()->min(1),
            'payload' => $schema->object()->required(),
        ];
    }
}
