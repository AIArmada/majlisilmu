<?php

declare(strict_types=1);

namespace App\Support\Mcp;

use Illuminate\Support\Facades\Cache;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Contracts\Transport;
use Laravel\Mcp\Server\Transport\FakeTransporter;

class McpDocumentationPreflight
{
    public const GUIDE_DOCUMENT_ID = 'docs-admin-mcp-guide';

    public const GUIDE_RESOURCE_URI = 'file://docs/MAJLISILMU_MCP_ADMIN_AGENT_GUIDE.md';

    private const CACHE_KEY_PREFIX = 'mcp:documentation-preflight:admin:';

    public function shouldBlockOperationalToolCall(Request $request, string $toolName, ?Transport $transport = null): bool
    {
        if ($this->isDocumentationTool($toolName)) {
            return false;
        }

        if ($this->hasGuideInContext($request->sessionId())) {
            return false;
        }

        return ! ($transport instanceof FakeTransporter);
    }

    public function markGuideInContext(?string $sessionId): void
    {
        $normalizedSessionId = $this->normalizeSessionId($sessionId);

        if ($normalizedSessionId === null) {
            return;
        }

        Cache::put($this->cacheKey($normalizedSessionId), true, now()->addHours(12));
    }

    public function hasGuideInContext(?string $sessionId): bool
    {
        $normalizedSessionId = $this->normalizeSessionId($sessionId);

        if ($normalizedSessionId === null) {
            return false;
        }

        return Cache::get($this->cacheKey($normalizedSessionId), false) === true;
    }

    public function isDocumentationTool(string $toolName): bool
    {
        return in_array($toolName, ['search', 'fetch'], true);
    }

    public function blockedToolResponse(string $toolName): ResponseFactory
    {
        $message = sprintf(
            'Documentation preflight required before calling [%s]. Fetch `docs-admin-mcp-guide` or read `%s` in the current initialized MCP session, then retry the operational tool call.',
            $toolName,
            self::GUIDE_RESOURCE_URI,
        );

        return Response::make(Response::error($message))
            ->withStructuredContent([
                'error' => [
                    'code' => 'documentation_preflight_required',
                    'message' => $message,
                ],
                'required_documentation' => [
                    'document_id' => self::GUIDE_DOCUMENT_ID,
                    'resource_uri' => self::GUIDE_RESOURCE_URI,
                    'preferred_tool' => 'fetch',
                    'preferred_arguments' => [
                        'id' => self::GUIDE_DOCUMENT_ID,
                    ],
                    'alternative_method' => 'resources/read',
                    'alternative_arguments' => [
                        'uri' => self::GUIDE_RESOURCE_URI,
                    ],
                    'routing_prompt' => 'documentation-tool-routing',
                ],
                'retry' => [
                    'tool_name' => $toolName,
                    'after_documentation' => true,
                ],
                'can_retry' => true,
                'requires_initialized_session' => true,
            ]);
    }

    private function normalizeSessionId(?string $sessionId): ?string
    {
        $normalizedSessionId = is_string($sessionId) ? trim($sessionId) : '';

        return $normalizedSessionId !== '' ? $normalizedSessionId : null;
    }

    private function cacheKey(string $sessionId): string
    {
        return self::CACHE_KEY_PREFIX.$sessionId;
    }
}
