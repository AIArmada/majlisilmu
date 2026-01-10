<?php

namespace Database\Seeders;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Database\Seeder;

class SavedSearchSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (SavedSearch::query()->exists()) {
            return;
        }

        $users = User::query()->get();

        $users->each(function (User $user): void {
            SavedSearch::factory()
                ->count(2)
                ->create([
                    'user_id' => $user->id,
                ]);
        });
    }
}
