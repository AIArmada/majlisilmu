<?php

namespace Database\Seeders;

use App\Support\Authz\ScopedMemberRoleSeeder;
use Illuminate\Database\Seeder;

class ScopedMemberRolesSeeder extends Seeder
{
    /**
     * Seed shared member role templates for institution, speaker, and event membership scopes.
     */
    public function run(): void
    {
        /** @var ScopedMemberRoleSeeder $scopedRoleSeeder */
        $scopedRoleSeeder = app(ScopedMemberRoleSeeder::class);

        $scopedRoleSeeder->ensureForInstitution();
        $scopedRoleSeeder->ensureForSpeaker();
        $scopedRoleSeeder->ensureForEvent();
    }
}
