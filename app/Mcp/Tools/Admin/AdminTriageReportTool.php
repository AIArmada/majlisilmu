<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Admin;

use App\Models\Report;
use App\Support\Api\Admin\AdminReportTriageService;
use App\Support\Mcp\McpAuthenticatedUserResolver;
use App\Support\Moderation\ReportTriageWorkflow;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly(false)]
#[IsIdempotent(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class AdminTriageReportTool extends AbstractAdminTool
{
    protected string $name = 'admin-triage-report';

    protected string $description = 'Run one explicit triage action on a report, such as triage, resolve, dismiss, or reopen.';

    public function __construct(
        private readonly AdminReportTriageService $triageService,
    ) {}

    public function handle(Request $request): ResponseFactory|Response
    {
        return $this->structuredResponse(function () use ($request): array {
            $actor = $this->authorizeAdmin($request);

            $validated = $this->validateArguments($request, [
                'record_key' => ['required', 'string'],
                'action' => ['required', 'string'],
                'resolution_note' => ['sometimes', 'nullable', 'string'],
            ]);

            return $this->triageService->triage(
                recordKey: (string) $validated['record_key'],
                payload: $validated,
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
            'record_key' => $schema->string()->required()->min(1),
            'action' => $schema->string()->required()->enum(array_keys(ReportTriageWorkflow::availableActions(new Report(['status' => 'open'])))),
            'resolution_note' => $schema->string()->nullable(),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        $user = app(McpAuthenticatedUserResolver::class)->resolve($request->user());

        return $this->triageService->canTriage($user);
    }
}
