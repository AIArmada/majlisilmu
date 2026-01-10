<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class VenueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Venue::query()->exists()) {
            return;
        }

        $institutions = Institution::query()->get();

        if ($institutions->isEmpty()) {
            $institutions = Institution::factory()->count(3)->create();
        }

        $institutions->each(function (Institution $institution): void {
            $count = random_int(1, 2);

            Venue::factory()
                ->count($count)
                ->create([
                    'institution_id' => $institution->id,
                    'state_id' => $institution->state_id,
                    'district_id' => $institution->district_id,
                ]);
        });
    }
}
