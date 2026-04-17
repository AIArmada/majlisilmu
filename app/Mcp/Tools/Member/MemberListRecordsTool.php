<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Member;

use App\Support\Api\Member\MemberResourceService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class MemberListRecordsTool extends AbstractMemberTool
{
    protected string $name = 'member-list-records';

    protected string $description = 'List records for one Ahli-scoped member resource with optional search and pagination.';

    public function __construct(
        private readonly MemberResourceService $resourceService,
    ) {}

    public function handle(Request $request): ResponseFactory|Response
    {
        return $this->structuredResponse(function () use ($request): array {
            $this->authorizeMember($request);

            $validated = $this->validateArguments($request, [
                'resource_key' => ['required', 'string'],
                'search' => ['sometimes', 'nullable', 'string'],
                'page' => ['sometimes', 'nullable', 'integer', 'min:1'],
                'per_page' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:100'],
            ]);

            return $this->resourceService->listRecords(
                resourceKey: (string) $validated['resource_key'],
                search: (string) ($validated['search'] ?? ''),
                page: (int) ($validated['page'] ?? 1),
                perPage: (int) ($validated['per_page'] ?? 15),
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
            'search' => $schema->string()->nullable(),
            'page' => $schema->integer()->min(1)->default(1)->nullable(),
            'per_page' => $schema->integer()->min(1)->max(100)->default(15)->nullable(),
        ];
    }
}
