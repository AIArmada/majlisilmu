<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Registration;
use App\Models\User;
use Illuminate\Database\Seeder;

class RegistrationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (Registration::query()->exists()) {
            return;
        }

        $events = Event::query()
            ->where('registration_required', true)
            ->get();
        $users = User::query()->get();

        $events->each(function (Event $event) use ($users): void {
            $count = random_int(3, 8);
            $availableUsers = $users->shuffle()->values();
            $usedEmails = [];

            for ($i = 0; $i < $count; $i++) {
                $user = null;
                if ($availableUsers->isNotEmpty() && random_int(0, 1) === 1) {
                    $user = $availableUsers->shift();
                }

                $email = $user?->email ?? fake()->safeEmail();
                while (in_array($email, $usedEmails, true)) {
                    $user = null;
                    $email = fake()->safeEmail();
                }

                $usedEmails[] = $email;

                Registration::factory()->create([
                    'event_id' => $event->id,
                    'user_id' => $user?->id,
                    'name' => $user?->name ?? fake()->name(),
                    'email' => $email,
                    'phone' => $user?->phone ?? fake()->optional()->phoneNumber(),
                    'status' => 'registered',
                ]);
            }

            $event->update([
                'registrations_count' => $event->registrations()->count(),
            ]);
        });
    }
}
