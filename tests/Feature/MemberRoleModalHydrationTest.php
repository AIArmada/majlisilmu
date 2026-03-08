<?php

use AIArmada\FilamentAuthz\Facades\Authz;
use AIArmada\FilamentAuthz\Models\Role;
use App\Filament\Resources\Events\Pages\EditEvent;
use App\Filament\Resources\Events\RelationManagers\EventUsersRelationManager;
use App\Filament\Resources\Institutions\Pages\EditInstitution;
use App\Filament\Resources\Institutions\RelationManagers\MembersRelationManager as InstitutionMembersRelationManager;
use App\Filament\Resources\Speakers\Pages\EditSpeaker;
use App\Filament\Resources\Speakers\RelationManagers\MembersRelationManager as SpeakerMembersRelationManager;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\User;
use App\Support\Authz\MemberRoleScopes;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\ScopedMemberRolesSeeder::class);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function assignGlobalSuperAdmin(User $user): void
{
    setPermissionsTeamId(null);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->assignRole('super_admin');
}

it('prepopulates existing institution member roles in the manage roles modal', function () {
    $administrator = User::factory()->create();
    assignGlobalSuperAdmin($administrator);

    $institution = Institution::factory()->create();
    $member = User::factory()->create();

    $institution->members()->syncWithoutDetaching([$member->getKey()]);

    $scope = app(MemberRoleScopes::class)->institution();

    $ownerRoleId = Authz::withScope($scope, function () use ($member): string {
        $member->syncRoles(['owner']);

        return (string) Role::findByName('owner', 'web')->getKey();
    }, $member);

    Livewire::actingAs($administrator)
        ->test(InstitutionMembersRelationManager::class, [
            'ownerRecord' => $institution,
            'pageClass' => EditInstitution::class,
        ])
        ->mountTableAction('manageRoles', $member->getKey())
        ->assertTableActionDataSet([
            'role_ids' => [$ownerRoleId],
        ]);
});

it('prepopulates existing speaker member roles in the manage roles modal', function () {
    $administrator = User::factory()->create();
    assignGlobalSuperAdmin($administrator);

    $speaker = Speaker::factory()->create();
    $member = User::factory()->create();

    $speaker->members()->syncWithoutDetaching([$member->getKey()]);

    $scope = app(MemberRoleScopes::class)->speaker();

    $adminRoleId = Authz::withScope($scope, function () use ($member): string {
        $member->syncRoles(['admin']);

        return (string) Role::findByName('admin', 'web')->getKey();
    }, $member);

    Livewire::actingAs($administrator)
        ->test(SpeakerMembersRelationManager::class, [
            'ownerRecord' => $speaker,
            'pageClass' => EditSpeaker::class,
        ])
        ->mountTableAction('manageRoles', $member->getKey())
        ->assertTableActionDataSet([
            'role_ids' => [$adminRoleId],
        ]);
});

it('prepopulates existing event member roles in the manage roles modal', function () {
    $administrator = User::factory()->create();
    assignGlobalSuperAdmin($administrator);

    $event = Event::factory()->create();
    $member = User::factory()->create();

    $event->members()->syncWithoutDetaching([
        $member->getKey() => ['joined_at' => now()],
    ]);

    $scope = app(MemberRoleScopes::class)->event();

    $organizerRoleId = Authz::withScope($scope, function () use ($member): string {
        $member->syncRoles(['organizer']);

        return (string) Role::findByName('organizer', 'web')->getKey();
    }, $member);

    Livewire::actingAs($administrator)
        ->test(EventUsersRelationManager::class, [
            'ownerRecord' => $event,
            'pageClass' => EditEvent::class,
        ])
        ->mountTableAction('manageRoles', $member->getKey())
        ->assertTableActionDataSet([
            'role_ids' => [$organizerRoleId],
        ]);
});
