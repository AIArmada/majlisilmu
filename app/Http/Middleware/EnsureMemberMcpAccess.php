<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Mcp\McpTokenManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureMemberMcpAccess
{
    public function __construct(
        private readonly McpTokenManager $tokenManager,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user instanceof User
                && $user->hasMemberMcpAccess()
                && $this->tokenManager->allowsServer($user, McpTokenManager::MEMBER_SERVER),
            403,
        );

        return $next($request);
    }
}
