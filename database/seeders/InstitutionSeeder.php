<?php

namespace Database\Seeders;

use App\Models\Institution;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Seeder;

class InstitutionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Institution::query()->exists()) {
            return;
        }

        $states = State::query()->with('districts')->get();
        $users = User::query()->get();

        $institutions = Institution::factory()->count(6)->create();

        $institutions->each(function (Institution $institution, int $index) use ($states, $users): void {
            if ($states->isNotEmpty()) {
                $state = $states->random();
                $district = $state->districts->isNotEmpty() ? $state->districts->random() : null;

                $institution->update([
                    'state_id' => $state->id,
                    'district_id' => $district?->id,
                ]);
            }

            $verificationStatus = $index < 2 ? 'verified' : 'unverified';
            $trustScore = $verificationStatus === 'verified'
                ? fake()->numberBetween(80, 95)
                : fake()->numberBetween(10, 40);

            $institution->update([
                'verification_status' => $verificationStatus,
                'trust_score' => $trustScore,
            ]);

            if ($users->isNotEmpty()) {
                $owner = $users->random();
                $institution->members()->syncWithoutDetaching([
                    $owner->id => ['role' => 'owner'],
                ]);
            }
        });
    }
}
