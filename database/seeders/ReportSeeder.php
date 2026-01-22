<?php

namespace Database\Seeders;

use App\Models\Donation;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Report;
use App\Models\Speaker;
use App\Models\User;
use Illuminate\Database\Seeder;

class ReportSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Report::query()->exists()) {
            return;
        }

        $reporters = User::query()->get();

        if ($reporters->isEmpty()) {
            return;
        }

        $categories = [
            'wrong_info',
            'cancelled_not_updated',
            'fake_speaker',
            'inappropriate_content',
            'donation_scam',
            'other',
        ];

        $events = Event::query()->take(4)->get();
        foreach ($events as $event) {
            Report::factory()->create([
                'entity_type' => 'event',
                'entity_id' => $event->id,
                'category' => fake()->randomElement($categories),
                'reporter_id' => $reporters->random()->id,
            ]);
        }

        $institutions = Institution::query()->take(2)->get();
        foreach ($institutions as $institution) {
            Report::factory()->create([
                'entity_type' => 'institution',
                'entity_id' => $institution->id,
                'category' => fake()->randomElement($categories),
                'reporter_id' => $reporters->random()->id,
            ]);
        }

        $speakers = Speaker::query()->take(1)->get();
        foreach ($speakers as $speaker) {
            Report::factory()->create([
                'entity_type' => 'speaker',
                'entity_id' => $speaker->id,
                'category' => fake()->randomElement($categories),
                'reporter_id' => $reporters->random()->id,
            ]);
        }

        $donations = Donation::query()->take(1)->get();
        foreach ($donations as $donation) {
            Report::factory()->create([
                'entity_type' => 'donation',
                'entity_id' => $donation->id,
                'category' => 'donation_scam',
                'reporter_id' => $reporters->random()->id,
            ]);
        }
    }
}
