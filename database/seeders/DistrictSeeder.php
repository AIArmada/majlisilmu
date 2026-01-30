<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\State;
use Illuminate\Database\Seeder;
use Nnjeim\World\Models\Country;

class DistrictSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get Malaysia country ID
        $malaysia = Country::where('iso2', 'MY')->first();

        if (! $malaysia) {
            $this->command->warn('Malaysia country not found. Make sure WorldSeeder has been run.');

            return;
        }

        $districtsByState = [
            'Johor' => [
                'Johor Bahru',
                'Batu Pahat',
                'Muar',
                'Kluang',
                'Kota Tinggi',
                'Pontian',
            ],
            'Kedah' => [
                'Kota Setar',
                'Kuala Muda',
                'Kulim',
                'Kubang Pasu',
                'Langkawi',
            ],
            'Kelantan' => [
                'Kota Bharu',
                'Pasir Mas',
                'Bachok',
                'Tanah Merah',
            ],
            'Malacca' => [
                'Melaka Tengah',
                'Alor Gajah',
                'Jasin',
            ],
            'Negeri Sembilan' => [
                'Seremban',
                'Port Dickson',
                'Tampin',
                'Jempol',
            ],
            'Pahang' => [
                'Kuantan',
                'Temerloh',
                'Bentong',
                'Pekan',
            ],
            'Perak' => [
                'Kinta',
                'Manjung',
                'Kuala Kangsar',
                'Hilir Perak',
                'Larut Matang dan Selama',
            ],
            'Perlis' => [
                'Kangar',
            ],
            'Penang' => [
                'Timur Laut',
                'Barat Daya',
                'Seberang Perai Utara',
                'Seberang Perai Tengah',
                'Seberang Perai Selatan',
            ],
            'Sabah' => [
                'Kota Kinabalu',
                'Sandakan',
                'Tawau',
                'Lahad Datu',
                'Keningau',
            ],
            'Sarawak' => [
                'Kuching',
                'Miri',
                'Sibu',
                'Bintulu',
                'Samarahan',
            ],
            'Selangor' => [
                'Petaling',
                'Gombak',
                'Hulu Langat',
                'Klang',
                'Kuala Langat',
                'Sepang',
            ],
            'Terengganu' => [
                'Kuala Terengganu',
                'Kemaman',
                'Dungun',
                'Besut',
            ],
            'Kuala Lumpur' => [
                'Kuala Lumpur',
            ],
            'Putrajaya' => [
                'Putrajaya',
            ],
            'Labuan' => [
                'Labuan',
            ],
        ];

        foreach ($districtsByState as $stateName => $districts) {
            $state = State::query()
                ->where('country_id', $malaysia->id)
                ->where('name', $stateName)
                ->first();

            if ($state === null) {
                $this->command->warn("State not found: {$stateName}");

                continue;
            }

            foreach ($districts as $districtName) {
                District::query()->updateOrCreate([
                    'state_id' => $state->id,
                    'name' => $districtName,
                ], [
                    'country_id' => $malaysia->id,
                    'country_code' => 'MY',
                ]);
            }
        }
    }
}
