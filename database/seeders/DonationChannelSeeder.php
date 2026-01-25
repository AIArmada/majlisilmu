<?php

namespace Database\Seeders;

use App\Models\DonationChannel;
use App\Models\Institution;
use App\Models\Speaker;
use Illuminate\Database\Seeder;

class DonationChannelSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (DonationChannel::query()->exists()) {
            return;
        }

        // Create donation channels for institutions
        $institutions = Institution::query()->get();

        if ($institutions->isEmpty()) {
            $institutions = Institution::factory()->count(3)->create();
        }

        $institutions->each(function (Institution $institution): void {
            // Create a default bank account
            DonationChannel::factory()->bankAccount()->default()->create([
                'donatable_type' => Institution::class,
                'donatable_id' => $institution->id,
                'status' => $institution->status === 'verified' ? 'verified' : 'unverified',
            ]);

            // Optionally add DuitNow
            if (fake()->boolean(60)) {
                DonationChannel::factory()->duitnow()->create([
                    'donatable_type' => Institution::class,
                    'donatable_id' => $institution->id,
                    'status' => $institution->status === 'verified' ? 'verified' : 'unverified',
                ]);
            }

            // Optionally add e-wallet
            if (fake()->boolean(30)) {
                DonationChannel::factory()->ewallet()->create([
                    'donatable_type' => Institution::class,
                    'donatable_id' => $institution->id,
                    'status' => 'unverified',
                ]);
            }
        });

        // Create donation channels for some speakers
        $speakers = Speaker::query()->take(5)->get();

        $speakers->each(function (Speaker $speaker): void {
            if (fake()->boolean(30)) { // 30% of speakers have donation channels
                DonationChannel::factory()->bankAccount()->create([
                    'donatable_type' => Speaker::class,
                    'donatable_id' => $speaker->id,
                    'status' => $speaker->status === 'verified' ? 'verified' : 'unverified',
                    'is_default' => true,
                ]);
            }
        });
    }
}
