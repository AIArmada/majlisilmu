<?php

namespace Database\Seeders;

use App\Models\Speaker;
use App\Models\User;
use Illuminate\Database\Seeder;

class SpeakerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Speaker::query()->exists()) {
            return;
        }

        $users = User::query()->get();
        $speakers = Speaker::factory()->count(12)->create();

        if ($users->isEmpty()) {
            return;
        }

        $speakers->each(function (Speaker $speaker) use ($users): void {
            $owner = $users->random();

            $speaker->members()->syncWithoutDetaching([
                $owner->id => ['role' => 'owner'],
            ]);
        });
    }
}
