<?php

use AIArmada\FilamentAuthz\Models\Role;
use App\Filament\Resources\Authz\UserResource;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

it('shows email and phone verification timestamps on the authz user edit page', function () {
    setPermissionsTeamId(null);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    if (! Role::where('name', 'super_admin')->whereNull(app(PermissionRegistrar::class)->teamsKey)->exists()) {
        Role::create(['name' => 'super_admin', 'guard_name' => 'web']);
    }

    $administrator = User::factory()->create();
    $administrator->assignRole('super_admin');

    $targetUser = User::factory()->create([
        'email_verified_at' => now(),
        'phone_verified_at' => now(),
    ]);

    $this->actingAs($administrator)
        ->get(UserResource::getUrl('edit', ['record' => $targetUser], panel: 'admin'))
        ->assertSuccessful()
        ->assertSee('Email Verified At')
        ->assertSee('Phone Verified At');
});
