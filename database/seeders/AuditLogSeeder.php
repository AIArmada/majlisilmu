<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuditLogSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (AuditLog::query()->exists()) {
            return;
        }

        $actors = User::query()->get();
        $events = Event::query()->take(10)->get();

        $events->each(function (Event $event) use ($actors): void {
            AuditLog::factory()->create([
                'actor_id' => $actors->isNotEmpty() ? $actors->random()->id : null,
                'entity_type' => 'event',
                'entity_id' => $event->id,
                'action' => fake()->randomElement(['created', 'updated', 'approved']),
                'before' => ['status' => 'pending'],
                'after' => ['status' => $event->status],
            ]);
        });
    }
}
