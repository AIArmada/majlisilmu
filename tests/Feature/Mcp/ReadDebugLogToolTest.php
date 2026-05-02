<?php

use App\Actions\Membership\AddMemberToSubject;
use App\Mcp\Servers\AdminServer;
use App\Mcp\Servers\MemberServer;
use App\Mcp\Tools\Admin\AdminReadDebugLogTool;
use App\Mcp\Tools\Member\MemberReadDebugLogTool;
use App\Models\Institution;
use App\Models\User;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    // Ensure log file is cleaned and wait for filesystem to reflect changes
    $logPath = storage_path('logs/laravel.log');
    @unlink($logPath);
    // Double-check deletion in case of filesystem delays
    usleep(10000); // 10ms delay to allow filesystem to fully process deletion
});

it('returns mcp.image_upload log lines for admins', function (): void {
    $admin = debugLogAdminUser();

    $logPath = storage_path('logs/laravel.log');

    file_put_contents($logPath, implode(PHP_EOL, [
        '[2026-05-02 12:00:00] local.DEBUG: mcp.image_upload: start {"event":"test-event"}',
        '[2026-05-02 12:00:01] local.DEBUG: Some other log entry',
        '[2026-05-02 12:00:02] local.DEBUG: mcp.image_upload: complete {"media_id":"abc-123"}',
    ]).PHP_EOL);

    AdminServer::actingAs($admin)
        ->tool(AdminReadDebugLogTool::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('filter', 'mcp.image_upload')
            ->where('total_matched', 2)
            ->where('lines.0', '[2026-05-02 12:00:00] local.DEBUG: mcp.image_upload: start {"event":"test-event"}')
            ->where('lines.1', '[2026-05-02 12:00:02] local.DEBUG: mcp.image_upload: complete {"media_id":"abc-123"}')
            ->etc());
})->afterEach(fn () => @unlink(storage_path('logs/laravel.log')));

it('respects the lines limit', function (): void {
    $admin = debugLogAdminUser();

    $logPath = storage_path('logs/laravel.log');
    $allLines = array_map(
        fn (int $i): string => "[2026-05-02 12:00:{$i}] local.DEBUG: mcp.image_upload: entry {$i}",
        range(0, 9),
    );
    file_put_contents($logPath, implode(PHP_EOL, $allLines).PHP_EOL);

    AdminServer::actingAs($admin)
        ->tool(AdminReadDebugLogTool::class, ['lines' => 3])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('total_matched', 10)
            ->count('lines', 3)
            ->etc());
})->afterEach(fn () => @unlink(storage_path('logs/laravel.log')));

it('returns an empty result when no log file exists', function (): void {
    $admin = debugLogAdminUser();

    @unlink(storage_path('logs/laravel.log'));

    AdminServer::actingAs($admin)
        ->tool(AdminReadDebugLogTool::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('lines', [])
            ->where('total_matched', 0)
            ->where('filter', 'mcp.image_upload')
            ->etc());
});

it('accepts a custom filter string', function (): void {
    $admin = debugLogAdminUser();

    $logPath = storage_path('logs/laravel.log');
    file_put_contents($logPath, implode(PHP_EOL, [
        '[2026-05-02 12:00:00] local.DEBUG: mcp.image_upload: start',
        '[2026-05-02 12:00:01] local.DEBUG: custom.channel: my entry',
    ]).PHP_EOL);

    AdminServer::actingAs($admin)
        ->tool(AdminReadDebugLogTool::class, ['filter' => 'custom.channel'])
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('filter', 'custom.channel')
            ->where('total_matched', 1)
            ->etc());
})->afterEach(fn () => @unlink(storage_path('logs/laravel.log')));

it('returns mcp.image_upload log lines for members', function (): void {
    [$member] = debugLogMemberContext();

    $logPath = storage_path('logs/laravel.log');
    file_put_contents($logPath, implode(PHP_EOL, [
        '[2026-05-02 12:00:00] local.DEBUG: mcp.image_upload: start {"event":"member-event"}',
        '[2026-05-02 12:00:01] local.DEBUG: Some other entry',
    ]).PHP_EOL);

    MemberServer::actingAs($member)
        ->tool(MemberReadDebugLogTool::class)
        ->assertOk()
        ->assertStructuredContent(fn ($json) => $json
            ->where('filter', 'mcp.image_upload')
            ->where('total_matched', 1)
            ->etc());
})->afterEach(fn () => @unlink(storage_path('logs/laravel.log')));

it('tool is registered and marked read-only on both servers', function (): void {
    $adminTool = app(AdminReadDebugLogTool::class)->toArray();
    $memberTool = app(MemberReadDebugLogTool::class)->toArray();

    expect($adminTool['annotations'] ?? [])->toMatchArray(['readOnlyHint' => true])
        ->and($memberTool['annotations'] ?? [])->toMatchArray(['readOnlyHint' => true]);
});

function debugLogAdminUser(): User
{
    $role = 'super_admin';

    if (! Role::query()->where('name', $role)->where('guard_name', 'web')->exists()) {
        $roleRecord = new Role;
        $roleRecord->forceFill([
            'id' => (string) Str::uuid(),
            'name' => $role,
            'guard_name' => 'web',
        ])->save();
    }

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

/**
 * @return array{0: User}
 */
function debugLogMemberContext(): array
{
    $member = User::factory()->create([
        'phone' => '+60113334455',
        'phone_verified_at' => now(),
    ]);

    // Assign an institution membership so hasMemberMcpAccess() is satisfied
    $institution = Institution::factory()->create([
        'status' => 'verified',
        'is_active' => true,
    ]);
    app(AddMemberToSubject::class)->handle($institution, $member, 'admin');

    return [$member];
}
