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

        $users = User::query()->get();

        foreach ($realSpeakers as $name) {
            $speaker = Speaker::firstOrCreate(
                ['name' => $name],
                [
                    'slug' => Str::slug($name),
                    'bio' => fake()->paragraph(),
                    'status' => 'verified',
                ]
            );

            // Create contacts
            $speaker->contacts()->firstOrCreate(
                ['category' => 'email'],
                ['value' => Str::slug($name).'@example.com', 'type' => 'work']
            );

            $speaker->contacts()->firstOrCreate(
                ['category' => 'phone'],
                ['value' => '01'.fake()->numberBetween(10000000, 99999999), 'type' => 'work']
            );

            $speaker->ensureAuthzScope();
            $this->seedSpeakerRoles($speaker);

            if ($users->isNotEmpty()) {
                $owner = $users->random();
                $speaker->members()->syncWithoutDetaching([$owner->id]);
                $this->syncMemberRoles($speaker, $owner, ['owner']);
            }
        }

        // Add some filler fake speakers if needed
        $currentCount = Speaker::count();
        if ($currentCount < 30) {
            $speakers = Speaker::factory()->count(30 - $currentCount)->create();

            if ($users->isNotEmpty()) {
                $speakers->each(function (Speaker $speaker) use ($users): void {
                    $owner = $users->random();

                    $speaker->ensureAuthzScope();
                    $this->seedSpeakerRoles($speaker);
                    $speaker->members()->syncWithoutDetaching([$owner->id]);
                    $this->syncMemberRoles($speaker, $owner, ['owner']);
                });
            }
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
