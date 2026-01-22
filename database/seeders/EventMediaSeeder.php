<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventMedia;
use Illuminate\Database\Seeder;

class EventMediaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (EventMedia::query()->exists()) {
            return;
        }

        $events = Event::query()->get();

        $events->each(function (Event $event): void {
            EventMedia::factory()->create([
                'mediable_type' => Event::class,
                'mediable_id' => $event->id,
                'is_primary' => true,
            ]);

            if (random_int(0, 1) === 1) {
                EventMedia::factory()->create([
                    'mediable_type' => Event::class,
                    'mediable_id' => $event->id,
                    'is_primary' => false,
                ]);
            }
        });
    }
}
