<?php

declare(strict_types=1);

use App\Http\Controllers\Mcp\AdminMcpController;
use App\Http\Middleware\EnsureAdminApiAccess;
use App\Http\Middleware\NormalizeMcpAcceptHeader;
use App\Mcp\Servers\AdminServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;
use Laravel\Mcp\Server\Middleware\AddWwwAuthenticateHeader;

Mcp::web('/mcp/admin', AdminServer::class)
    ->middleware([
        NormalizeMcpAcceptHeader::class,
        'auth:sanctum',
        EnsureAdminApiAccess::class,
    ]);

Route::middleware([
    NormalizeMcpAcceptHeader::class,
    AddWwwAuthenticateHeader::class,
    'auth:sanctum',
    EnsureAdminApiAccess::class,
])->group(function (): void {
    Route::get('/mcp/admin', [AdminMcpController::class, 'stream']);
    Route::delete('/mcp/admin', [AdminMcpController::class, 'destroy']);
});
