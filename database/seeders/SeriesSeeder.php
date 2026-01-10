<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\Series;
use Illuminate\Database\Seeder;

class SeriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Series::query()->exists()) {
            return;
        }

        $institutions = Institution::query()->with('venues')->get();

        if ($institutions->isEmpty()) {
            return;
        }

        $institutions->each(function (Institution $institution): void {
            $venue = $institution->venues->first();

            Series::factory()
                ->count(1)
                ->create([
                    'institution_id' => $institution->id,
                    'venue_id' => $venue?->id,
                    'visibility' => 'public',
                ]);
        });
    }
}
