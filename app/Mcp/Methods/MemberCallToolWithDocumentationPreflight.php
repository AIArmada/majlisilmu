<?php

declare(strict_types=1);

namespace App\Mcp\Methods;

use App\Support\Mcp\MemberMcpDocumentationPreflight;
use Generator;
use Illuminate\Container\Container;
use Laravel\Mcp\Request;
use Laravel\Mcp\Server\Contracts\Errable;
use Laravel\Mcp\Server\Contracts\Method;
use Laravel\Mcp\Server\Exceptions\JsonRpcException;
use Laravel\Mcp\Server\Methods\CallTool;
use Laravel\Mcp\Server\ServerContext;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use Laravel\Mcp\Server\Transport\JsonRpcResponse;

class MemberCallToolWithDocumentationPreflight extends CallTool implements Errable, Method
{
    /**
     * @return JsonRpcResponse|Generator<JsonRpcResponse>
     *
     * @throws JsonRpcException
     */
    #[\Override]
    public function handle(JsonRpcRequest $request, ServerContext $context): Generator|JsonRpcResponse
    {
        if (is_null($request->get('name'))) {
            throw new JsonRpcException(
                'Missing [name] parameter.',
                -32602,
                $request->id,
            );
        }

        $tool = $context
            ->tools()
            ->first(
                fn ($tool): bool => $tool->name() === $request->params['name'],
                fn () => throw new JsonRpcException(
                    "Tool [{$request->params['name']}] not found.",
                    -32602,
                    $request->id,
                ));

        $container = Container::getInstance();

        /** @var Request $mcpRequest */
        $mcpRequest = $container->make('mcp.request');

        /** @var MemberMcpDocumentationPreflight $documentationPreflight */
        $documentationPreflight = $container->make(MemberMcpDocumentationPreflight::class);
        $transport = $container->bound('mcp.transport')
            ? $container->make('mcp.transport')
            : null;

        if ($documentationPreflight->shouldBlockOperationalToolCall($mcpRequest, $tool->name(), $transport)) {
            return $this->toJsonRpcResponse(
                $request,
                $documentationPreflight->blockedToolResponse($tool->name()),
                $this->serializable($tool),
            );
        }

        return parent::handle($request, $context);
    }
}
