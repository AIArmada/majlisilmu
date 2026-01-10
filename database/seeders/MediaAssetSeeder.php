<?php

namespace Database\Seeders;

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Seeder;

class MediaAssetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (MediaAsset::query()->exists()) {
            return;
        }

        $users = User::query()->get();

        MediaAsset::factory()
            ->count(12)
            ->make()
            ->each(function (MediaAsset $asset) use ($users): void {
                $asset->uploaded_by = $users->isNotEmpty()
                    ? $users->random()->id
                    : null;
                $asset->save();
            });
    }
}
