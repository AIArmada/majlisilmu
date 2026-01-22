<?php

namespace Database\Seeders;

use AIArmada\FilamentAuthz\Facades\Authz;
use AIArmada\FilamentAuthz\Models\Permission;
use AIArmada\FilamentAuthz\Models\Role;
use App\Models\Country;
use App\Models\Institution;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class InstitutionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $realInstitutions = [
            [
                'name' => 'Masjid Wilayah Persekutuan',
                'type' => 'masjid',
                'address1' => 'Jalan Duta',
                'city' => 'Kuala Lumpur',
                'state_name' => 'Kuala Lumpur',
                'lat' => 3.1614755,
                'lng' => 101.6701549,
            ],
            [
                'name' => 'Masjid Tuanku Mizan Zainal Abidin (Masjid Besi)',
                'type' => 'masjid',
                'address1' => 'Presint 3',
                'city' => 'Putrajaya',
                'state_name' => 'Putrajaya',
                'lat' => 2.9221376,
                'lng' => 101.6841203,
            ],
            [
                'name' => 'Masjid Putra',
                'type' => 'masjid',
                'address1' => 'Presint 1',
                'city' => 'Putrajaya',
                'state_name' => 'Putrajaya',
                'lat' => 2.9360156,
                'lng' => 101.6891044,
            ],
            [
                'name' => 'Masjid Negeri Shah Alam',
                'type' => 'masjid',
                'address1' => 'Seksyen 14',
                'city' => 'Shah Alam',
                'state_name' => 'Selangor',
                'lat' => 3.078864,
                'lng' => 101.5186083,
            ],
            [
                'name' => 'Masjid Al-Hasanah',
                'type' => 'masjid',
                'address1' => 'Bandar Baru Bangi',
                'city' => 'Bangi',
                'state_name' => 'Selangor',
                'lat' => 2.9592534,
                'lng' => 101.7588031,
            ],
            [
                'name' => 'Masjid Bandar Tun Hussein Onn',
                'type' => 'masjid',
                'address1' => 'Cheras',
                'city' => 'Cheras',
                'state_name' => 'Selangor',
                'lat' => 3.0378953,
                'lng' => 101.7602058,
            ],
            [
                'name' => 'Pusat Islam Petaling Jaya',
                'type' => 'educational_center',
                'address1' => 'Jalan Gasing',
                'city' => 'Petaling Jaya',
                'state_name' => 'Selangor',
                'lat' => 3.1026,
                'lng' => 101.6521,
            ],
            [
                'name' => 'Surau Ar-Raudhah',
                'type' => 'surau',
                'address1' => 'Seksyen 7',
                'city' => 'Shah Alam',
                'state_name' => 'Selangor',
                'lat' => 3.0746,
                'lng' => 101.4883,
            ],
        ];

        $countries = Country::query()->get();
        $states = State::query()->with(['districts', 'cities'])->get();
        $users = User::query()->get();

        $malaysia = $countries->where('iso2', 'MY')->first() ?? $countries->first();

        // 1. Seed Real Institutions
        foreach ($realInstitutions as $data) {
            $stateMatch = $states->filter(function ($s) use ($data) {
                return Str::contains(strtolower($s->name), strtolower($data['state_name']));
            })->first();

            // Fallback to random if not found, or skip? better to random.
            $state = $stateMatch ?? $states->random();
            $district = collect($state->districts)->isNotEmpty() ? collect($state->districts)->random() : null;
            $city = collect($state->cities)->isNotEmpty() ? collect($state->cities)->random() : null;

            $inst = Institution::firstOrCreate(
                ['name' => $data['name']],
                [
                    'slug' => Str::slug($data['name']),
                    'type' => $data['type'],
                    'status' => 'verified',
                ]
            );

            // Create contacts
            $inst->contacts()->firstOrCreate(
                ['category' => 'email'],
                ['value' => Str::slug($data['name']).'@example.com', 'type' => 'work']
            );

            $inst->contacts()->firstOrCreate(
                ['category' => 'phone'],
                ['value' => '03-'.fake()->numberBetween(1000000, 9999999), 'type' => 'work']
            );

            // Create or update address
            $inst->address()->updateOrCreate([], [
                'address1' => $data['address1'],
                'postcode' => fake()->postcode(),
                'country_id' => $malaysia?->id,
                'state_id' => $state->id,
                'district_id' => $district?->id,
                'city_id' => $city?->id,
                'lat' => $data['lat'] ?? null,
                'lng' => $data['lng'] ?? null,
            ]);

            $inst->ensureAuthzScope();
            $this->seedInstitutionRoles($inst);

            // Attach random owner
            if ($users->isNotEmpty()) {
                $owner = $users->random();

                $inst->members()->syncWithoutDetaching([$owner->id]);
                $this->syncMemberRoles($inst, $owner, ['owner']);
            }
        }

        // 2. Seed Fake Institutions (if we need more filler)
        // We only create if total count is low, or just add some random ones regardless.
        // Let's ensure we have at least 20 institutions.
        $currentCount = Institution::query()->count();
        if ($currentCount < 20) {
            $institutions = Institution::factory()->count(20 - $currentCount)->create();

            $institutions->each(function (Institution $institution, int $index) use ($malaysia, $states, $users): void {
                if ($states->isNotEmpty()) {
                    $state = $states->random();
                    $district = collect($state->districts)->isNotEmpty() ? collect($state->districts)->random() : null;
                    $city = collect($state->cities)->isNotEmpty() ? collect($state->cities)->random() : null;

                    $institution->address()->update([
                        'country_id' => $malaysia?->id,
                        'state_id' => $state->id,
                        'district_id' => $district?->id,
                        'city_id' => $city?->id,
                    ]);
                }

                $status = $index < 2 ? 'verified' : 'unverified';

                $institution->forceFill(['status' => $status])->save();

                $institution->ensureAuthzScope();
                $this->seedInstitutionRoles($institution);

                if ($users->isNotEmpty()) {
                    $owner = $users->random();
                    $institution->members()->syncWithoutDetaching([$owner->id]);
                    $this->syncMemberRoles($institution, $owner, ['owner']);
                }
            });
        }
    }

    /**
     * @return array<string, list<string>>
     */
    protected function getInstitutionRolePermissions(): array
    {
        return [
            'owner' => [
                'institution.view',
                'institution.update',
                'institution.delete',
                'institution.manage-members',
                'institution.manage-donation-accounts',
                'event.view',
                'event.create',
                'event.update',
                'event.delete',
                'event.view-registrations',
                'event.export-registrations',
            ],
            'admin' => [
                'institution.view',
                'institution.update',
                'institution.manage-members',
                'institution.manage-donation-accounts',
                'event.view',
                'event.create',
                'event.update',
                'event.delete',
                'event.view-registrations',
                'event.export-registrations',
            ],
            'editor' => [
                'institution.view',
                'event.view',
                'event.create',
                'event.update',
            ],
            'viewer' => [
                'institution.view',
                'event.view',
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

    protected function seedInstitutionRoles(Institution $institution): void
    {
        $rolePermissions = $this->getInstitutionRolePermissions();
        $allPermissions = array_values(array_unique(array_merge(...array_values($rolePermissions))));

        $this->ensurePermissions($allPermissions);

        Authz::withScope($institution, function () use ($rolePermissions): void {
            foreach ($rolePermissions as $roleName => $permissions) {
                $role = Role::findOrCreate($roleName, 'web');
                $role->syncPermissions($permissions);
            }
        });
    }

    /**
     * @param  list<string>  $roles
     */
    protected function syncMemberRoles(Institution $institution, User $user, array $roles): void
    {
        Authz::withScope($institution, function () use ($user, $roles): void {
            $user->syncRoles($roles);
        }, $user);
    }
}
