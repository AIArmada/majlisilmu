<?php

namespace Database\Seeders;

use App\Models\DonationAccount;
use App\Models\Institution;
use Illuminate\Database\Seeder;

class DonationAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (DonationAccount::query()->exists()) {
            return;
        }

        $institutions = Institution::query()->get();

        if ($institutions->isEmpty()) {
            $institutions = Institution::factory()->count(3)->create();
        }

        $institutions->each(function (Institution $institution): void {
            DonationAccount::factory()->create([
                'institution_id' => $institution->id,
                'verification_status' => $institution->verification_status === 'verified'
                    ? 'verified'
                    : 'unverified',
            ]);
        });
    }
}
