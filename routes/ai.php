<?php

declare(strict_types=1);

use App\Http\Controllers\Mcp\AdminMcpController;
use App\Http\Controllers\Mcp\MemberMcpController;
use App\Http\Middleware\EnsureAdminMcpAccess;
use App\Http\Middleware\EnsureMemberMcpAccess;
use App\Http\Middleware\NormalizeMcpAcceptHeader;
use App\Mcp\Servers\AdminServer;
use App\Mcp\Servers\MemberServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;

Mcp::web('/mcp/admin', AdminServer::class)
    ->middleware([
        NormalizeMcpAcceptHeader::class,
        'auth:sanctum',
        EnsureAdminMcpAccess::class,
    ]);

Mcp::web('/mcp/member', MemberServer::class)
    ->middleware([
        NormalizeMcpAcceptHeader::class,
        'auth:sanctum',
        EnsureMemberMcpAccess::class,
    ]);

Route::middleware([
    NormalizeMcpAcceptHeader::class,
    AddWwwAuthenticateHeader::class,
    'auth:sanctum',
    EnsureAdminMcpAccess::class,
])->group(function (): void {
    Route::get('/mcp/admin', [AdminMcpController::class, 'stream']);
    Route::delete('/mcp/admin', [AdminMcpController::class, 'destroy']);
});

Route::middleware([
    NormalizeMcpAcceptHeader::class,
    AddWwwAuthenticateHeader::class,
    'auth:sanctum',
    EnsureMemberMcpAccess::class,
])->group(function (): void {
    Route::get('/mcp/member', [MemberMcpController::class, 'stream']);
    Route::delete('/mcp/member', [MemberMcpController::class, 'destroy']);
});
