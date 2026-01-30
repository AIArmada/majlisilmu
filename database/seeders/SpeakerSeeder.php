<?php

namespace Database\Seeders;

use AIArmada\FilamentAuthz\Facades\Authz;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use App\Models\Speaker;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SpeakerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable model events for faster seeding
        Speaker::unsetEventDispatcher();

        \Illuminate\Support\Facades\DB::transaction(function (): void {
            $this->seedSpeakers();
        });

        Speaker::setEventDispatcher(app('events'));
    }

    private function seedSpeakers(): void
    {
        $realSpeakers = [
            'Ustaz Azhar Idrus',
            'Dr. MAZA (Dr. Mohd Asri Zainul Abidin)',
            'Ustaz Wadi Annuar',
            'Ustaz Don Daniyal',
            'Habib Ali Zaenal Abidin',
            'Ustaz Kazim Elias',
            'Ustaz Ebit Lew',
            'Dr. Rozaimi Ramle',
            'Ustaz Auni Mohamed',
            'Ustaz Fawwaz Mat Jan',
            'Ustaz Jafri Abu Bakar',
            'Ustaz Abdullah Khairi',
            'Ustaz Haslin Baharim (Bollywood)',
            'Ustaz Syamsul Debat',
            'Prof. Dr. Muhaya Mohamad',
        ];

        $userIds = User::query()->pluck('id')->toArray();
        $contactsToInsert = [];
        $memberAttachments = [];

        // Create real speakers
        foreach ($realSpeakers as $name) {
            $speaker = Speaker::firstOrCreate(
                ['name' => $name],
                [
                    'slug' => Str::slug($name),
                    'bio' => fake()->paragraph(),
                    'status' => 'verified',
                    'is_active' => true,
                ]
            );

            $contactsToInsert[] = [
                'id' => (string) Str::uuid(),
                'contactable_type' => 'speaker',
                'contactable_id' => $speaker->id,
                'category' => 'email',
                'value' => Str::slug($name).'@example.com',
                'type' => 'work',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            $contactsToInsert[] = [
                'id' => (string) Str::uuid(),
                'contactable_type' => 'speaker',
                'contactable_id' => $speaker->id,
                'category' => 'phone',
                'value' => '01'.fake()->numberBetween(10000000, 99999999),
                'type' => 'work',
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (!empty($userIds)) {
                $memberAttachments[] = [
                    'speaker_id' => $speaker->id,
                    'user_id' => $userIds[array_rand($userIds)],
                ];
            }
        }

        // Add filler speakers if needed
        $currentCount = Speaker::count();
        if ($currentCount < 30) {
            $speakers = Speaker::factory()->count(30 - $currentCount)->create();

            foreach ($speakers as $speaker) {
                if (!empty($userIds)) {
                    $memberAttachments[] = [
                        'speaker_id' => $speaker->id,
                        'user_id' => $userIds[array_rand($userIds)],
                    ];
                }
            }
        }

        // Bulk insert contacts
        if (!empty($contactsToInsert)) {
            \Illuminate\Support\Facades\DB::table('contacts')->insert($contactsToInsert);
        }

        // Bulk insert member attachments
        if (!empty($memberAttachments)) {
            \Illuminate\Support\Facades\DB::table('speaker_members')->insertOrIgnore($memberAttachments);
        }
    }

    /**
     * @return array<string, list<string>>
     */
    protected function getSpeakerRolePermissions(): array
    {
        return [
            'owner' => [
                'speaker.view',
                'speaker.update',
                'speaker.delete',
                'speaker.manage-members',
            ],
            'admin' => [
                'speaker.view',
                'speaker.update',
                'speaker.manage-members',
            ],
            'editor' => [
                'speaker.view',
                'speaker.update',
            ],
            'viewer' => [
                'speaker.view',
            ],
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    protected function ensurePermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            Permission::findOrCreate($permission, 'web');
        }
    }

    protected function seedSpeakerRoles(Speaker $speaker): void
    {
        $rolePermissions = $this->getSpeakerRolePermissions();
        $allPermissions = array_values(array_unique(array_merge(...array_values($rolePermissions))));

        $this->ensurePermissions($allPermissions);

        Authz::withScope($speaker, function () use ($rolePermissions): void {
            foreach ($rolePermissions as $roleName => $permissions) {
                $role = Role::findOrCreate($roleName, 'web');
                $role->syncPermissions($permissions);
            }
        });
    }

    /**
     * @param  list<string>  $roles
     */
    protected function syncMemberRoles(Speaker $speaker, User $user, array $roles): void
    {
        Authz::withScope($speaker, function () use ($user, $roles): void {
            $user->syncRoles($roles);
        }, $user);
    }
}
