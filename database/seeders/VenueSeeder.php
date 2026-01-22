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

        $institutions = Institution::query()->with('address')->get();

        if ($institutions->isEmpty()) {
            $institutions = Institution::factory()->count(3)->create();
            $institutions->load('address');
        }

        $institutions->each(function (Institution $institution): void {
            $count = random_int(1, 2);

            $venues = Venue::factory()
                ->count($count)
                ->create([
                    'institution_id' => $institution->id,
                ]);

            // Optionally align venue address with institution address
            if ($institution->address) {
                $venues->each(function (Venue $venue) use ($institution) {
                    $venue->address()->update([
                        'state_id' => $institution->address->state_id,
                        'district_id' => $institution->address->district_id,
                        'city_id' => $institution->address->city_id,
                        'country_id' => $institution->address->country_id,
                        'postcode' => $institution->address->postcode,
                        // Keep specific address1 random or same? Random is fine for now as sub-venue
                    ]);
                });
            }
        });
    }
}
