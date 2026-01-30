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

        Venue::unsetEventDispatcher();

        \Illuminate\Support\Facades\DB::transaction(function (): void {
            $institutions = Institution::query()
                ->with('address')
                ->limit(50)
                ->get();

            if ($institutions->isEmpty()) {
                $institutions = Institution::factory()->count(3)->create();
                $institutions->load('address');
            }

            $venuesToCreate = [];
            $addressesToUpdate = [];

            foreach ($institutions as $institution) {
                $count = random_int(1, 2);

                for ($i = 0; $i < $count; $i++) {
                    $venue = Venue::factory()->make([
                        'institution_id' => $institution->id,
                    ]);
                    $venueData = $venue->toArray();
                    // JSON encode array fields for raw insert
                    if (isset($venueData['facilities']) && is_array($venueData['facilities'])) {
                        $venueData['facilities'] = json_encode($venueData['facilities']);
                    }
                    $venuesToCreate[] = array_merge($venueData, [
                        'id' => (string) \Illuminate\Support\Str::uuid(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            // Bulk insert venues
            foreach (array_chunk($venuesToCreate, 100) as $chunk) {
                Venue::insert($chunk);
            }

            // Create addresses for venues
            $venues = Venue::query()->with('institution.address')->doesntHave('address')->get();
            $addressesToInsert = [];

            foreach ($venues as $venue) {
                $instAddress = $venue->institution?->address;
                $addressesToInsert[] = [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'addressable_type' => 'venue',
                    'addressable_id' => $venue->id,
                    'line1' => fake()->streetAddress(),
                    'line2' => fake()->optional()->secondaryAddress(),
                    'postcode' => $instAddress?->postcode ?? fake()->postcode(),
                    'lat' => fake()->randomFloat(7, 1.0, 7.0),
                    'lng' => fake()->randomFloat(7, 99.0, 119.0),
                    'google_place_id' => fake()->optional()->numerify('ChI###########'),
                    'waze_url' => fake()->optional()->url(),
                    'state_id' => $instAddress?->state_id,
                    'district_id' => $instAddress?->district_id,
                    'city_id' => $instAddress?->city_id,
                    'country_id' => $instAddress?->country_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // Bulk insert addresses
            foreach (array_chunk($addressesToInsert, 100) as $chunk) {
                \Illuminate\Support\Facades\DB::table('addresses')->insert($chunk);
            }
        });

        Venue::setEventDispatcher(app('events'));
    }
}
