<?php

namespace Database\Seeders;

use App\Models\District;
use App\Models\State;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DistrictSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $districtsByState = [
            'johor' => [
                'Johor Bahru',
                'Batu Pahat',
                'Muar',
                'Kluang',
                'Kota Tinggi',
                'Pontian',
            ],
            'kedah' => [
                'Kota Setar',
                'Kuala Muda',
                'Kulim',
                'Kubang Pasu',
                'Langkawi',
            ],
            'kelantan' => [
                'Kota Bharu',
                'Pasir Mas',
                'Bachok',
                'Tanah Merah',
            ],
            'melaka' => [
                'Melaka Tengah',
                'Alor Gajah',
                'Jasin',
            ],
            'negeri-sembilan' => [
                'Seremban',
                'Port Dickson',
                'Tampin',
                'Jempol',
            ],
            'pahang' => [
                'Kuantan',
                'Temerloh',
                'Bentong',
                'Pekan',
            ],
            'perak' => [
                'Kinta',
                'Manjung',
                'Kuala Kangsar',
                'Hilir Perak',
                'Larut Matang dan Selama',
            ],
            'perlis' => [
                'Kangar',
            ],
            'pulau-pinang' => [
                'Timur Laut',
                'Barat Daya',
                'Seberang Perai Utara',
                'Seberang Perai Tengah',
                'Seberang Perai Selatan',
            ],
            'sabah' => [
                'Kota Kinabalu',
                'Sandakan',
                'Tawau',
                'Lahad Datu',
                'Keningau',
            ],
            'sarawak' => [
                'Kuching',
                'Miri',
                'Sibu',
                'Bintulu',
                'Samarahan',
            ],
            'selangor' => [
                'Petaling',
                'Gombak',
                'Hulu Langat',
                'Klang',
                'Kuala Langat',
                'Sepang',
            ],
            'terengganu' => [
                'Kuala Terengganu',
                'Kemaman',
                'Dungun',
                'Besut',
            ],
            'wp-kuala-lumpur' => [
                'Kuala Lumpur',
            ],
            'wp-putrajaya' => [
                'Putrajaya',
            ],
            'wp-labuan' => [
                'Labuan',
            ],
        ];

        foreach ($districtsByState as $stateSlug => $districts) {
            $state = State::query()->where('slug', $stateSlug)->first();

            if ($state === null) {
                continue;
            }

            foreach ($districts as $districtName) {
                District::query()->updateOrCreate([
                    'state_id' => $state->id,
                    'slug' => Str::slug($districtName),
                ], [
                    'name' => $districtName,
                ]);
            }
        }
    }
}
