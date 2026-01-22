<?php

namespace Database\Seeders;

use App\Models\Donation;
use App\Models\Institution;
use App\Models\Speaker;
use Illuminate\Database\Seeder;

class DonationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Donation::query()->exists()) {
            return;
        }

        // Create donations for institutions
        $institutions = Institution::query()->get();

        if ($institutions->isEmpty()) {
            $institutions = Institution::factory()->count(3)->create();
        }

        $institutions->each(function (Institution $institution): void {
            // Create a default bank account
            Donation::factory()->bankAccount()->default()->create([
                'donatable_type' => Institution::class,
                'donatable_id' => $institution->id,
                'status' => $institution->status === 'verified' ? 'verified' : 'unverified',
            ]);

            // Optionally add DuitNow
            if (fake()->boolean(60)) {
                Donation::factory()->duitnow()->create([
                    'donatable_type' => Institution::class,
                    'donatable_id' => $institution->id,
                    'status' => $institution->status === 'verified' ? 'verified' : 'unverified',
                ]);
            }

            // Optionally add e-wallet
            if (fake()->boolean(30)) {
                Donation::factory()->ewallet()->create([
                    'donatable_type' => Institution::class,
                    'donatable_id' => $institution->id,
                    'status' => 'unverified',
                ]);
            }
        });

        // Create donations for some speakers
        $speakers = Speaker::query()->take(5)->get();

        $speakers->each(function (Speaker $speaker): void {
            if (fake()->boolean(30)) { // 30% of speakers have donation accounts
                Donation::factory()->bankAccount()->create([
                    'donatable_type' => Speaker::class,
                    'donatable_id' => $speaker->id,
                    'status' => $speaker->status === 'verified' ? 'verified' : 'unverified',
                    'is_default' => true,
                ]);
            }
        });
    }
}
