<?php

namespace Database\Seeders;

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
            WorldSeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            DistrictSeeder::class,
            EventTypeSeeder::class,
            TagSeeder::class,
            TopicSeeder::class,
            UserSeeder::class,
        ]);

        // Create additional random users
        User::factory()->count(50)->create();

        $this->call([
            MasjidSeeder::class,
            InstitutionSeeder::class,
            VenueSeeder::class,
            SpeakerSeeder::class,
            DonationChannelSeeder::class,
            SeriesSeeder::class,
            EventSeeder::class,
            MediaLinkSeeder::class,
            EventSubmissionSeeder::class,
            ModerationReviewSeeder::class,
            ReportSeeder::class,
            SavedSearchSeeder::class,
            RegistrationSeeder::class,
        ]);
    }
}
