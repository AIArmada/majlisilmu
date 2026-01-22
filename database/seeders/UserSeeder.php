<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Super Admin - Full access to everything
        $superAdmin = User::query()->updateOrCreate(
            ['email' => 'superadmin@majlisilmu.my'],
            [
                'name' => 'Super Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $superAdmin->syncRoles(['super_admin']);

        // Admin - Administrative access
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@majlisilmu.my'],
            [
                'name' => 'Administrator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $admin->syncRoles(['admin']);

        // Moderator - Can moderate content
        $moderator = User::query()->updateOrCreate(
            ['email' => 'moderator@majlisilmu.my'],
            [
                'name' => 'Content Moderator',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $moderator->syncRoles(['moderator']);

        // Editor - Can create and edit content
        $editor = User::query()->updateOrCreate(
            ['email' => 'editor@majlisilmu.my'],
            [
                'name' => 'Content Editor',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $editor->syncRoles(['editor']);

        // Viewer - Read-only access
        $viewer = User::query()->updateOrCreate(
            ['email' => 'viewer@majlisilmu.my'],
            [
                'name' => 'Report Viewer',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );
        $viewer->syncRoles(['viewer']);

        // Regular user without admin access
        User::query()->updateOrCreate(
            ['email' => 'user@majlisilmu.my'],
            [
                'name' => 'Regular User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('User accounts seeded successfully!');
        $this->command->newLine();
        $this->command->table(
            ['Email', 'Password', 'Role'],
            [
                ['superadmin@majlisilmu.my', 'password', 'super_admin'],
                ['admin@majlisilmu.my', 'password', 'admin'],
                ['moderator@majlisilmu.my', 'password', 'moderator'],
                ['editor@majlisilmu.my', 'password', 'editor'],
                ['viewer@majlisilmu.my', 'password', 'viewer'],
                ['user@majlisilmu.my', 'password', '(none)'],
            ]
        );
    }
}
