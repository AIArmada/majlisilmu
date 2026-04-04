<?php

use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

it('issues a bearer token for an admin-capable user', function () {
    $user = tokenCommandUser('super_admin');

    $this->artisan('mcp:token', [
        'email' => $user->email,
        'name' => 'copilot-mcp',
    ])->expectsOutputToContain('Bearer ')
        ->assertSuccessful();
});

it('rejects users without application admin access', function () {
    $user = User::factory()->create();

    $this->artisan('mcp:token', [
        'email' => $user->email,
        'name' => 'copilot-mcp',
    ])->expectsOutputToContain('does not have application admin access')
        ->assertFailed();
});

function tokenCommandUser(string $role): User
{
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
