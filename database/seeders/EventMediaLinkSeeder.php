<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventMediaLink;
use Illuminate\Database\Seeder;

class EventMediaLinkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (EventMediaLink::query()->exists()) {
            return;
        }

        $events = Event::query()->get();

        $events->each(function (Event $event): void {
            EventMediaLink::factory()->create([
                'event_id' => $event->id,
                'is_primary' => true,
            ]);

            if (random_int(0, 1) === 1) {
                EventMediaLink::factory()->create([
                    'event_id' => $event->id,
                    'is_primary' => false,
                ]);
            }
        });
    }
}
