<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            StateSeeder::class,
            DistrictSeeder::class,
            TopicSeeder::class,
        ]);

        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'superadmin@example.com',
        ]);

        $moderator = User::factory()->create([
            'name' => 'Moderator',
            'email' => 'moderator@example.com',
        ]);

        User::factory()->count(8)->create();

        $roles = Role::query()
            ->whereIn('name', ['super_admin', 'moderator'])
            ->get()
            ->keyBy('name');

        if ($roles->has('super_admin')) {
            $superAdmin->roles()->syncWithoutDetaching([$roles->get('super_admin')->id]);
        }

        if ($roles->has('moderator')) {
            $moderator->roles()->syncWithoutDetaching([$roles->get('moderator')->id]);
        }

        $this->call([
            MediaAssetSeeder::class,
            InstitutionSeeder::class,
            VenueSeeder::class,
            SpeakerSeeder::class,
            DonationAccountSeeder::class,
            SeriesSeeder::class,
            EventSeeder::class,
            EventMediaLinkSeeder::class,
            EventSubmissionSeeder::class,
            ModerationReviewSeeder::class,
            ReportSeeder::class,
            SavedSearchSeeder::class,
            RegistrationSeeder::class,
            AuditLogSeeder::class,
        ]);
    }
}
