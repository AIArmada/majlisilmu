<?php

namespace Database\Seeders;

use AIArmada\FilamentAuthz\Models\Permission;
use Illuminate\Database\Seeder;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $permissions = [
            'institution.view',
            'institution.update',
            'institution.delete',
            'institution.manage-members',
            'institution.manage-donation-channels',
            'event.view',
            'event.create',
            'event.update',
            'event.delete',
            'event.view-registrations',
            'event.export-registrations',
        ];

        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }
}
