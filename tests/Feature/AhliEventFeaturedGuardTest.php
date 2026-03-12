<?php

use AIArmada\FilamentAuthz\Facades\Authz;
use App\Filament\Ahli\Resources\Events\Pages\EditEvent as AhliEditEvent;
use App\Filament\Ahli\Resources\Events\Pages\ListEvents as AhliListEvents;
use App\Filament\Resources\Events\Pages\EditEvent as AdminEditEvent;
use App\Filament\Resources\Events\Pages\ListEvents as AdminListEvents;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\User;
use App\Services\EventKeyPersonSyncService;
use App\Support\Authz\MemberRoleScopes;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $this->seed(\Database\Seeders\PermissionSeeder::class);
    $this->seed(\Database\Seeders\RoleSeeder::class);
    $this->seed(\Database\Seeders\ScopedMemberRolesSeeder::class);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function assignGlobalRoleForFeaturedGuard(User $user, string $role): void
{
    setPermissionsTeamId(null);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->assignRole($role);
}

function createAhliInstitutionAdmin(): array
{
    $user = User::factory()->create();
    $institution = Institution::factory()->create();
    $speaker = Speaker::factory()->create();
    $startsAt = now('Asia/Kuala_Lumpur')->addDays(2)->setTime(20, 0)->utc();
    $endsAt = now('Asia/Kuala_Lumpur')->addDays(2)->setTime(22, 0)->utc();

    $event = Event::factory()->for($institution)->create([
        'organizer_type' => Institution::class,
        'organizer_id' => $institution->id,
        'is_featured' => false,
        'timezone' => 'Asia/Kuala_Lumpur',
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
    ]);

    app(EventKeyPersonSyncService::class)->sync($event, [$speaker->id], []);

    $institution->members()->syncWithoutDetaching([$user->id]);

    $scope = app(MemberRoleScopes::class)->institution();

    Authz::withScope($scope, function () use ($user): void {
        $user->syncRoles(['admin']);
    }, $user);

    return [$user, $event];
}

it('hides the featured field and column from ahli event surfaces', function () {
    [$member, $event] = createAhliInstitutionAdmin();

    Livewire::actingAs($member)
        ->test(AhliEditEvent::class, ['record' => $event->id])
        ->assertFormFieldDoesNotExist('is_priority')
        ->assertFormFieldDoesNotExist('escalated_at')
        ->assertFormFieldDoesNotExist('is_featured');

    Livewire::actingAs($member)
        ->test(AhliListEvents::class)
        ->assertTableColumnHidden('is_featured');
});

it('keeps the featured field hidden on ahli surfaces even for application admins', function () {
    [$member, $event] = createAhliInstitutionAdmin();

    assignGlobalRoleForFeaturedGuard($member, 'super_admin');

    Livewire::actingAs($member)
        ->test(AhliEditEvent::class, ['record' => $event->id])
        ->assertFormFieldDoesNotExist('is_priority')
        ->assertFormFieldDoesNotExist('escalated_at')
        ->assertFormFieldDoesNotExist('is_featured');

    Livewire::actingAs($member)
        ->test(AhliListEvents::class)
        ->assertTableColumnHidden('is_featured');
});

it('shows the featured field and column to application admins', function () {
    $administrator = User::factory()->create();
    assignGlobalRoleForFeaturedGuard($administrator, 'super_admin');

    $event = Event::factory()->create([
        'is_featured' => false,
    ]);

    Livewire::actingAs($administrator)
        ->test(AdminEditEvent::class, ['record' => $event->id])
        ->assertFormFieldExists('is_priority')
        ->assertFormFieldExists('escalated_at')
        ->assertFormFieldExists('is_featured');

    Livewire::actingAs($administrator)
        ->test(AdminListEvents::class)
        ->assertTableColumnExists('is_featured');
});

it('allows application admins to save events without mass assigning speakers', function () {
    $administrator = User::factory()->create();
    assignGlobalRoleForFeaturedGuard($administrator, 'super_admin');

    $speaker = Speaker::factory()->create();
    $event = Event::factory()->create([
        'is_featured' => false,
    ]);

    app(EventKeyPersonSyncService::class)->sync($event, [$speaker->id], []);

    Livewire::actingAs($administrator)
        ->test(AdminEditEvent::class, ['record' => $event->id])
        ->set('data.speakers', [$speaker->id])
        ->call('save')
        ->assertHasNoErrors();

    expect($event->fresh()->speakers->pluck('id')->all())->toBe([$speaker->id]);
});

it('ignores crafted ahli payloads that try to set featured on save', function () {
    [$member, $event] = createAhliInstitutionAdmin();

    Livewire::actingAs($member)
        ->test(AhliEditEvent::class, ['record' => $event->id])
        ->set('data.is_featured', true)
        ->set('data.escalated_at', now()->addDay()->toDateTimeString())
        ->call('save')
        ->assertHasNoErrors();

    expect($event->fresh()->is_featured)->toBeFalse()
        ->and($event->fresh()->escalated_at)->toBeNull();
});
