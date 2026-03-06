<?php

use AIArmada\FilamentAuthz\Facades\Authz;
use App\Filament\Resources\Institutions\Pages\EditInstitution;
use App\Filament\Resources\Speakers\Pages\EditSpeaker;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\User;
use App\Support\Authz\MemberRoleScopes;
use App\Support\Submission\PublicSubmissionLockService;
use Illuminate\Support\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\ScopedMemberRolesSeeder::class);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function assignGlobalRole(User $user, string $role): void
{
    setPermissionsTeamId(null);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->assignRole($role);
}

it('disables institution lock action when credible precondition fails', function () {
    $admin = User::factory()->create();
    assignGlobalRole($admin, 'super_admin');

    $institution = Institution::factory()->create([
        'allow_public_event_submission' => true,
    ]);

    $member = User::factory()->create([
        'phone' => null,
        'phone_verified_at' => null,
    ]);

    $institution->members()->syncWithoutDetaching([$member->id]);

    $scope = app(MemberRoleScopes::class)->institution();
    Authz::withScope($scope, function () use ($member): void {
        $member->syncRoles(['owner']);
    }, $member);

    $this->actingAs($admin);

    Livewire::test(EditInstitution::class, ['record' => $institution->id])
        ->assertActionVisible('lock_public_submission')
        ->assertActionDisabled('lock_public_submission');

    expect($institution->fresh()->allow_public_event_submission)->toBeTrue();
});

it('enables institution lock action when precondition passes without changing state', function () {
    $admin = User::factory()->create();
    assignGlobalRole($admin, 'moderator');

    $institution = Institution::factory()->create([
        'allow_public_event_submission' => true,
    ]);

    $member = User::factory()->create([
        'phone' => '+60112223344',
        'phone_verified_at' => Carbon::now(),
    ]);

    $institution->members()->syncWithoutDetaching([$member->id]);

    $scope = app(MemberRoleScopes::class)->institution();
    Authz::withScope($scope, function () use ($member): void {
        $member->syncRoles(['admin']);
    }, $member);

    $this->actingAs($admin);

    Livewire::test(EditInstitution::class, ['record' => $institution->id])
        ->assertActionVisible('lock_public_submission')
        ->assertActionEnabled('lock_public_submission');

    expect($institution->fresh()->allow_public_event_submission)->toBeTrue();
});

it('locks institution submission explicitly and stores lock metadata', function () {
    $admin = User::factory()->create();
    assignGlobalRole($admin, 'admin');

    $institution = Institution::factory()->create([
        'allow_public_event_submission' => true,
    ]);

    $member = User::factory()->create([
        'phone' => '+60112223344',
        'phone_verified_at' => Carbon::now(),
    ]);

    $institution->members()->syncWithoutDetaching([$member->id]);

    $scope = app(MemberRoleScopes::class)->institution();
    Authz::withScope($scope, function () use ($member): void {
        $member->syncRoles(['owner']);
    }, $member);

    $this->actingAs($admin);

    Livewire::test(EditInstitution::class, ['record' => $institution->id])
        ->callAction('lock_public_submission', ['reason' => 'Institution team is now fully responsible.'])
        ->assertNotified();

    $institution->refresh();

    expect($institution->allow_public_event_submission)->toBeFalse()
        ->and($institution->public_submission_locked_by)->toBe($admin->id)
        ->and($institution->public_submission_locked_at)->not->toBeNull()
        ->and($institution->public_submission_lock_reason)->toBe('Institution team is now fully responsible.');
});

it('shows lock actions only to global admin, admin, and moderator', function () {
    $institution = Institution::factory()->create([
        'allow_public_event_submission' => true,
    ]);

    $member = User::factory()->create([
        'phone' => '+60117778899',
        'phone_verified_at' => Carbon::now(),
    ]);

    $institution->members()->syncWithoutDetaching([$member->id]);

    $scope = app(MemberRoleScopes::class)->institution();
    Authz::withScope($scope, function () use ($member): void {
        $member->syncRoles(['owner']);
    }, $member);

    $superAdmin = User::factory()->create();
    assignGlobalRole($superAdmin, 'super_admin');

    $admin = User::factory()->create();
    assignGlobalRole($admin, 'admin');

    $moderator = User::factory()->create();
    assignGlobalRole($moderator, 'moderator');

    $editor = User::factory()->create();
    assignGlobalRole($editor, 'editor');

    $this->actingAs($superAdmin);
    Livewire::test(EditInstitution::class, ['record' => $institution->id])
        ->assertActionVisible('lock_public_submission');

    $this->actingAs($admin);
    Livewire::test(EditInstitution::class, ['record' => $institution->id])
        ->assertActionVisible('lock_public_submission');

    $this->actingAs($moderator);
    Livewire::test(EditInstitution::class, ['record' => $institution->id])
        ->assertActionVisible('lock_public_submission');

    expect(fn () => app(PublicSubmissionLockService::class)->lockInstitution(
        $institution->fresh(),
        $editor,
        'Unauthorized lock attempt',
    ))->toThrow(AuthorizationException::class);
});

it('auto-reopens institution submission when lock credibility drifts', function () {
    $admin = User::factory()->create();
    assignGlobalRole($admin, 'super_admin');

    $institution = Institution::factory()->create([
        'allow_public_event_submission' => true,
    ]);

    $member = User::factory()->create([
        'phone' => '+60119990000',
        'phone_verified_at' => Carbon::now(),
    ]);

    $institution->members()->syncWithoutDetaching([$member->id]);

    $scope = app(MemberRoleScopes::class)->institution();
    Authz::withScope($scope, function () use ($member): void {
        $member->syncRoles(['owner']);
    }, $member);

    $this->actingAs($admin);

    Livewire::test(EditInstitution::class, ['record' => $institution->id])
        ->callAction('lock_public_submission')
        ->assertNotified();

    $institution->refresh();
    expect($institution->allow_public_event_submission)->toBeFalse();

    $member->update([
        'phone_verified_at' => null,
    ]);

    $institution->refresh();
    expect($institution->allow_public_event_submission)->toBeTrue();
});

it('supports explicit lock and unlock actions on speaker records', function () {
    $admin = User::factory()->create();
    assignGlobalRole($admin, 'super_admin');

    $speaker = Speaker::factory()->create([
        'allow_public_event_submission' => true,
    ]);

    $member = User::factory()->create([
        'phone' => '+60112220000',
        'phone_verified_at' => Carbon::now(),
    ]);

    $speaker->members()->syncWithoutDetaching([$member->id]);

    $scope = app(MemberRoleScopes::class)->speaker();
    Authz::withScope($scope, function () use ($member): void {
        $member->syncRoles(['admin']);
    }, $member);

    $this->actingAs($admin);

    Livewire::test(EditSpeaker::class, ['record' => $speaker->id])
        ->callAction('lock_public_submission', ['reason' => 'Speaker team self-manages submissions'])
        ->assertNotified();

    $speaker->refresh();
    expect($speaker->allow_public_event_submission)->toBeFalse();

    Livewire::test(EditSpeaker::class, ['record' => $speaker->id])
        ->callAction('unlock_public_submission')
        ->assertNotified();

    expect($speaker->fresh()->allow_public_event_submission)->toBeTrue();
});
