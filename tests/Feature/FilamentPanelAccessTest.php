<?php

use AIArmada\FilamentAuthz\Facades\Authz;
use AIArmada\FilamentAuthz\Models\Role;
use App\Models\User;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('allows any authenticated user to access the ahli panel', function () {
    $user = User::factory()->create();

    expect($user->canAccessPanel(Panel::make()->id('ahli')))->toBeTrue();
});

it('denies admin panel access when user has no global role assignment', function () {
    $user = User::factory()->create();

    expect($user->canAccessPanel(Panel::make()->id('admin')))->toBeFalse();
});

it('denies admin panel access for users with scoped roles only', function () {
    $user = User::factory()->create();
    $scopeId = (string) Str::uuid();

    Authz::withScope($scopeId, function () use ($user): void {
        Role::findOrCreate('institution-admin', 'web');
        $user->syncRoles(['institution-admin']);
    }, $user);

    expect($user->canAccessPanel(Panel::make()->id('admin')))->toBeFalse();
});

it('allows admin panel access when user has a global role assignment', function () {
    $user = User::factory()->create();

    Authz::withScope(null, function () use ($user): void {
        Role::findOrCreate('super_admin', 'web');
        $user->syncRoles(['super_admin']);
    }, $user);

    expect($user->canAccessPanel(Panel::make()->id('admin')))->toBeTrue();
});

