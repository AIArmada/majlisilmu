<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    /**
     * Seed only deterministic bootstrap data that is safe for production.
     */
    public function run(): void
    {
        $this->call([
            WorldSeeder::class,
            MalaysiaCitySeeder::class,
            DistrictSeeder::class,
            SubdistrictSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            ScopedMemberRolesSeeder::class,
            TagSeeder::class,
            SpaceSeeder::class,
            ReferenceSeeder::class,
            InspirationSeeder::class,
        ]);
    }
}
