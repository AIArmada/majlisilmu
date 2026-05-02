<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Admin;

use App\Mcp\Tools\Concerns\ReadsDebugLog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class AdminReadDebugLogTool extends AbstractAdminTool
{
    use ReadsDebugLog;

    protected string $name = 'admin-read-debug-log';

    protected string $title = 'Read Debug Log';

    protected string $description = 'Read recent filtered lines from the application debug log. Defaults to showing only mcp.image_upload entries so you can diagnose image upload failures immediately after an upload attempt without leaving the MCP session.';

    public function handle(Request $request): ResponseFactory|Response
    {
        return $this->structuredResponse(function () use ($request): array {
            $this->authorizeAdmin($request);

            $validated = $this->validateArguments($request, [
                'filter' => ['nullable', 'string', 'min:3'],
                'lines' => ['nullable', 'integer', 'min:1', 'max:200'],
            ]);

            $filter = is_string($validated['filter'] ?? null) ? $validated['filter'] : 'mcp.image_upload';
            $lines = is_int($validated['lines'] ?? null) ? $validated['lines'] : 50;

            return $this->readFilteredDebugLog($filter, $lines);
        });
    }

    /**
     * @return array<string, Type>
     */
    #[\Override]
    public function schema(JsonSchema $schema): array
    {
        return $this->debugLogInputSchema($schema);
    }

    /**
     * @return array<string, Type>
     */
    #[\Override]
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->debugLogOutputSchema($schema);
    }
}
