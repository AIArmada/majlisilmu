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
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MasjidSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = database_path('seeders/senarai_masjid.csv');

        if (! File::exists($csvPath)) {
            $this->command->warn('CSV file not found: '.$csvPath);

            return;
        }

        // Create additional users for mosque personnel if needed
        $existingUserCount = User::count();
        if ($existingUserCount < 300) {
            $this->command->info('Creating '.(300 - $existingUserCount).' additional users for mosque personnel...');
            User::factory()->count(300 - $existingUserCount)->create();
        }

        $countries = Country::query()->get();
        $states = State::query()->with(['districts'])->get();
        $users = User::query()->get();

        $malaysia = $countries->where('iso2', 'MY')->first() ?? $countries->first();

        // Read CSV file
        $handle = fopen($csvPath, 'r');
        $header = fgetcsv($handle); // Skip header row

        $count = 0;
        $skipped = 0;
        // Process all mosques from CSV

        while (($row = fgetcsv($handle)) !== false) {
            // CSV columns: No., Nama, Alamat, Negeri, Daerah, No. Tel, Fax
            [, $nama, $alamat, $negeri, $daerah, $noTel] = $row;

            // Skip if no name
            if (empty($nama) || trim($nama) === '') {
                continue;
            }

            // Clean the name
            $nama = trim($nama);
            $alamat = trim($alamat);
            $negeri = trim($negeri);
            $daerah = trim($daerah);
            $noTel = trim($noTel);

            // Find matching state
            $state = $this->findState($states, $negeri);
            if (! $state) {
                // If we can't find the state, skip this mosque
                $skipped++;

                continue;
            }

            // Find matching district
            $district = null;
            if ($daerah && $state->districts) {
                $district = collect($state->districts)->first(function ($d) use ($daerah) {
                    return Str::contains(strtolower($d->name), strtolower($daerah)) ||
                           Str::contains(strtolower($daerah), strtolower($d->name));
                });
            }

            // Create or update institution
            // Create unique slug by combining name + district/state to handle duplicate names
            $slugBase = Str::slug($nama);
            $locationPart = $daerah ?: $negeri;
            $slug = $slugBase.'-'.Str::slug($locationPart);

            // Ensure uniqueness by appending counter if needed
            $originalSlug = $slug;
            $counter = 1;
            while (Institution::where('slug', $slug)->exists()) {
                $slug = $originalSlug.'-'.$counter;
                $counter++;
            }

            $inst = Institution::create([
                'name' => $nama,
                'slug' => $slug,
                'type' => 'masjid',
                'status' => 'verified',
            ]);

            // Create contacts
            if ($noTel && $noTel !== '0' && $noTel !== '') {
                // Clean phone number
                $cleanedPhone = $this->cleanPhoneNumber($noTel);
                if ($cleanedPhone) {
                    try {
                        $inst->contacts()->create([
                            'category' => 'phone',
                            'value' => $cleanedPhone,
                            'type' => 'work',
                        ]);
                    } catch (\Exception $e) {
                        $this->command->warn("Failed to create contact for {$nama}: ".$e->getMessage());
                    }
                }
            }

            // Create address
            try {
                $inst->address()->create([
                    'address1' => $alamat ?: null,
                    'postcode' => null,
                    'country_id' => $malaysia?->id,
                    'state_id' => $state->id,
                    'district_id' => $district?->id,
                    'city_id' => null,
                    'lat' => null,
                    'lng' => null,
                ]);
            } catch (\Exception $e) {
                $this->command->warn("Failed to create address for {$nama}: ".$e->getMessage());
            }

            // Skip authorization setup to speed up seeding
            // Authorization will be set up when institution is first accessed
            // $inst->ensureAuthzScope();
            // $this->seedInstitutionRoles($inst);

            // Attach random owner (skip for performance)
            // if ($users->isNotEmpty()) {
            //     $owner = $users->random();
            //     $inst->members()->syncWithoutDetaching([$owner->id]);
            //     $this->syncMemberRoles($inst, $owner, ['owner']);
            // }

            $count++;

            if ($count % 100 === 0) {
                $this->command->info("Seeded {$count} mosques...");
            }
        }

        fclose($handle);

        $this->command->info("Successfully seeded {$count} mosques from CSV (skipped {$skipped} due to missing state matches).");
    }

    /**
     * Find state from CSV state name
     */
    protected function findState($states, string $negeri): ?object
    {
        // State name mappings
        $stateMap = [
            'W.P. KUALA LUMPUR' => 'Kuala Lumpur',
            'W.P. PUTRAJAYA' => 'Putrajaya',
            'W.P. LABUAN' => 'Labuan',
            'N. SEMBILAN' => 'Negeri Sembilan',
            'PULAU PINANG' => 'Penang',
            'MELAKA' => 'Malacca',
            'MELAKA TENGAH' => 'Malacca',
        ];

        // Use mapping if available
        $searchName = $stateMap[strtoupper($negeri)] ?? $negeri;

        return $states->first(function ($s) use ($searchName, $negeri) {
            $stateLower = strtolower($s->name);
            $searchLower = strtolower($searchName);
            $negeriLower = strtolower($negeri);

            return Str::contains($stateLower, $searchLower) ||
                   Str::contains($searchLower, $stateLower) ||
                   Str::contains($stateLower, $negeriLower) ||
                   Str::contains($negeriLower, $stateLower);
        });
    }

    /**
     * Clean phone number
     */
    protected function cleanPhoneNumber(string $phone): ?string
    {
        // Remove all non-digit characters
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        // Must have at least 7 digits
        if (strlen($cleaned) < 7) {
            return null;
        }

        // Format Malaysian phone numbers
        if (strlen($cleaned) === 7) {
            // Landline without area code, add 03 (KL area code)
            $cleaned = '03'.$cleaned;
        }

        // Add + if it starts with country code
        if (strlen($cleaned) > 10 && substr($cleaned, 0, 2) === '60') {
            $cleaned = '+'.$cleaned;
        } elseif (strlen($cleaned) === 9 || strlen($cleaned) === 10) {
            // Add Malaysian country code for mobile numbers
            if (substr($cleaned, 0, 1) === '0') {
                $cleaned = '+6'.substr($cleaned, 1);
            } else {
                $cleaned = '+60'.$cleaned;
            }
        }

        return $cleaned;
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
