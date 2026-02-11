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

        SavedSearch::unsetEventDispatcher();

        try {
            \Illuminate\Support\Facades\DB::transaction(function (): void {
                $userIds = User::query()->pluck('id')->toArray();

                $searchesToInsert = [];

                foreach ($userIds as $userId) {
                    for ($i = 0; $i < 2; $i++) {
                        $searchData = SavedSearch::factory()->make([
                            'user_id' => $userId,
                        ])->toArray();

                        if (isset($searchData['filters']) && is_array($searchData['filters'])) {
                            $searchData['filters'] = json_encode($searchData['filters']);
                        }

                        $searchesToInsert[] = array_merge(
                            $searchData,
                            [
                                'id' => (string) \Illuminate\Support\Str::uuid(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]
                        );
                    }
                }

                foreach (array_chunk($searchesToInsert, 200) as $chunk) {
                    SavedSearch::insert($chunk);
                }
            });
        } finally {
            SavedSearch::setEventDispatcher(app('events'));
        }
    }
}
