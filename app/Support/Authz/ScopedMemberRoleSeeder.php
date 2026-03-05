<?php

namespace App\Support\Authz;

use AIArmada\FilamentAuthz\Facades\Authz;
use AIArmada\FilamentAuthz\Models\AuthzScope;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;

class ScopedMemberRoleSeeder
{
    public function __construct(
        private readonly MemberRoleScopes $memberRoleScopes,
    ) {}

    /**
     * @var array<string, list<string>>
     */
    private const array INSTITUTION_ROLE_PERMISSIONS = [
        'owner' => [
            'institution.view',
            'institution.update',
            'institution.delete',
            'institution.manage-members',
            'institution.manage-donation-channels',
            'event.view',
            'event.create',
            'event.update',
            'event.delete',
            'event.manage-members',
            'event.view-registrations',
            'event.export-registrations',
        ],
        'admin' => [
            'institution.view',
            'institution.update',
            'institution.manage-members',
            'institution.manage-donation-channels',
            'event.view',
            'event.create',
            'event.update',
            'event.delete',
            'event.manage-members',
            'event.view-registrations',
            'event.export-registrations',
        ],
        'editor' => [
            'institution.view',
            'event.view',
            'event.create',
            'event.update',
        ],
        'viewer' => [
            'institution.view',
            'event.view',
        ],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const array SPEAKER_ROLE_PERMISSIONS = [
        'owner' => [
            'speaker.view',
            'speaker.update',
            'speaker.delete',
            'speaker.manage-members',
        ],
        'admin' => [
            'speaker.view',
            'speaker.update',
            'speaker.manage-members',
        ],
        'editor' => [
            'speaker.view',
            'speaker.update',
        ],
        'viewer' => [
            'speaker.view',
        ],
    ];

    /**
     * @var array<string, list<string>>
     */
    private const array EVENT_ROLE_PERMISSIONS = [
        'organizer' => [
            'event.view',
            'event.update',
            'event.delete',
            'event.manage-members',
            'event.view-registrations',
            'event.export-registrations',
        ],
        'co-organizer' => [
            'event.view',
            'event.update',
            'event.manage-members',
            'event.view-registrations',
            'event.export-registrations',
        ],
        'editor' => [
            'event.view',
            'event.update',
        ],
        'viewer' => [
            'event.view',
        ],
    ];

    public function ensureForInstitution(): void
    {
        $this->seedRolesForScope($this->memberRoleScopes->institution(), self::INSTITUTION_ROLE_PERMISSIONS, false);
    }

    public function ensureForSpeaker(): void
    {
        $this->seedRolesForScope($this->memberRoleScopes->speaker(), self::SPEAKER_ROLE_PERMISSIONS, false);
    }

    public function ensureForEvent(): void
    {
        $this->seedRolesForScope($this->memberRoleScopes->event(), self::EVENT_ROLE_PERMISSIONS, false);
    }

    /**
     * @param  array<string, list<string>>  $rolePermissions
     */
    private function seedRolesForScope(AuthzScope $scope, array $rolePermissions, bool $syncExisting): void
    {
        $allPermissions = collect($rolePermissions)
            ->flatten()
            ->unique()
            ->values()
            ->all();

        $this->ensurePermissions($allPermissions);

        Authz::withScope($scope, function () use ($rolePermissions, $syncExisting): void {
            foreach ($rolePermissions as $roleName => $permissions) {
                $role = Role::findOrCreate($roleName, 'web');

                if (! $syncExisting && $role->permissions()->exists()) {
                    continue;
                }

                $role->syncPermissions($permissions);
            }
        });
    }

    /**
     * @param  list<string>  $permissions
     */
    private function ensurePermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }
}
