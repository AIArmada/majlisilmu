<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Speaker>
 */
class SpeakerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $maleFirstNames = [
            'Ahmad',
            'Muhammad',
            'Mohd',
            'Syafiq',
            'Faris',
            'Zaid',
            'Imran',
            'Harith',
            'Irfan',
            'Aiman',
            'Azlan',
            'Haziq',
            'Hakim',
            'Hilmi',
            'Faiz',
            'Iskandar',
            'Khairol',
            'Ridzuan',
            'Zulkifli',
            'Afiq',
            'Azim',
            'Firdaus',
            'Kamal',
            'Nazri',
            'Asyraf',
            'Hafiz',
            'Naufal',
            'Arif',
            'Syahmi',
            'Aqil',
        ];
        $femaleFirstNames = [
            'Nur',
            'Siti',
            'Aisyah',
            'Hannah',
            'Nabila',
            'Sofea',
            'Farah',
            'Atiqah',
            'Zulaikha',
            'Maryam',
            'Amina',
            'Nurin',
            'Syuhada',
            'Alya',
            'Husna',
            'Izzah',
            'Nadia',
            'Sakinah',
            'Raihana',
            'Balqis',
            'Marwa',
            'Asma',
            'Najwa',
            'Mariam',
            'Nadiah',
            'Sofiah',
            'Ain',
            'Irdina',
            'Qistina',
            'Hawa',
        ];
        $maleSecondNames = [
            'Hassan',
            'Husain',
            'Hamzah',
            'Khalid',
            'Yusof',
            'Rahman',
            'Rashid',
            'Salleh',
            'Saifuddin',
            'Syed',
            'Fadhil',
            'Anwar',
            'Zaki',
            'Rafiq',
        ];
        $femaleSecondNames = [
            'Husna',
            'Nabila',
            'Azzahra',
            'Salsabila',
            'Khadijah',
            'Halimah',
            'Amirah',
            'Safiyyah',
            'Ruqayyah',
            'Zainab',
            'Nadhirah',
            'Izzati',
        ];
        $parentNames = [
            'Ismail',
            'Hassan',
            'Rahman',
            'Yusof',
            'Salleh',
            'Mahmud',
            'Hamzah',
            'Zulkifli',
            'Halim',
            'Kamal',
            'Salim',
            'Jaafar',
            'Rashid',
            'Abdullah',
            'Othman',
            'Ibrahim',
            'Khalid',
            'Ariffin',
            'Nasir',
            'Abdul Rahman',
            'Abdul Aziz',
            'Abdul Wahid',
            'Abdul Karim',
        ];
        // Pre-nominals (Professional/Religious titles)
        $preNominalsMale = ['Ustaz', 'Dr.', 'Prof.', 'Ir.', 'Tuan Guru'];
        $preNominalsFemale = ['Ustazah', 'Dr.', 'Prof.', 'Ir.'];

        // Honorifics (State awards)
        $honorificsMale = ['Dato\'', 'Datuk', 'Tan Sri', 'Tun'];
        $honorificsFemale = ['Datin', 'Datin Paduka', 'Puan Sri', 'Toh Puan'];

        // Post-nominals (Academic qualifications)
        $postNominals = ['PhD', 'MSc', 'MA', 'BSc', 'BA', 'HONS'];

        $isFemale = fake()->boolean(45);

        // Generate Name
        $firstName = $isFemale
            ? fake()->randomElement($femaleFirstNames)
            : fake()->randomElement($maleFirstNames);
        $secondName = fake()->boolean(65)
            ? fake()->randomElement($isFemale ? $femaleSecondNames : $maleSecondNames)
            : null;
        $givenName = trim(implode(' ', array_filter([$firstName, $secondName])));
        $connector = $isFemale ? 'binti' : 'bin';
        $parentName = fake()->randomElement($parentNames);
        $name = $givenName.' '.$connector.' '.$parentName;

        // Populate new fields
        $preNominal = fake()->boolean(30)
            ? fake()->randomElement($isFemale ? $preNominalsFemale : $preNominalsMale)
            : null;

        $honorific = fake()->boolean(10)
            ? fake()->randomElement($isFemale ? $honorificsFemale : $honorificsMale)
            : null;

        $postNominal = fake()->boolean(20)
            ? fake()->randomElement($postNominals)
            : null;

        $universities = [
            'Universiti Az-Zaitunah',
            'Al-Azhar University',
            'Universiti Islam Madinah',
            'Universiti Malaya',
            'Universiti Kebangsaan Malaysia',
            'Universiti Islam Antarabangsa Malaysia',
            'Universiti Sains Islam Malaysia',
            'Universiti Yarmouk',
            'Kolej Universiti Islam Antarabangsa Selangor',
        ];

        $degrees = ['Bachelor', 'Masters', 'PhD', 'Diploma'];
        $fields = ['Syariah', 'Usuluddin', 'Dakwah', 'Islamic Finance', 'Fiqh Fatwa', 'Tafsir', 'Hadith'];

        $qualifications = [];
        if (fake()->boolean(70)) {
            $count = fake()->numberBetween(1, 3);
            for ($i = 0; $i < $count; $i++) {
                $qualifications[] = [
                    'institution' => fake()->randomElement($universities),
                    'degree' => fake()->randomElement($degrees),
                    'field' => fake()->randomElement($fields),
                    'year' => fake()->numberBetween(1990, 2023),
                ];
            }
        }

        return [
            'name' => $name,
            'gender' => $isFemale ? 'female' : 'male',
            'honorific' => $honorific,
            'pre_nominal' => $preNominal,
            'post_nominal' => $postNominal,
            'is_freelance' => fake()->boolean(20),
            'qualifications' => $qualifications,
            'slug' => Str::slug($name.'-'.Str::random(8)),
            'bio' => fake()->optional()->paragraph(),
            'status' => 'verified',
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\Speaker $speaker) {
            // Create Address
            $state = \App\Models\State::inRandomOrder()->first();
            if ($state) {
                $speaker->address()->create([
                    'state_id' => $state->id,
                    'district_id' => $state->districts()->inRandomOrder()->first()?->id,
                ]);
            }

            $speaker->contacts()->create([
                'category' => 'email',
                'value' => fake()->safeEmail(),
                'type' => 'work',
            ]);

            $speaker->contacts()->create([
                'category' => 'phone',
                'value' => fake()->phoneNumber(),
                'type' => 'work',
            ]);

            // Attach Topics
            $topics = \App\Models\Topic::inRandomOrder()->limit(rand(1, 5))->pluck('id');
            $speaker->topics()->attach($topics);

            // Attach Languages
            if (class_exists(\Nnjeim\World\Models\Language::class)) {
                $languages = \Nnjeim\World\Models\Language::inRandomOrder()->limit(rand(1, 3))->pluck('id');
                $speaker->languages()->attach($languages);
            }

            // Attach Institutions
            if (! $speaker->is_freelance) {
                $institutions = \App\Models\Institution::inRandomOrder()->limit(rand(1, 2))->get();
                foreach ($institutions as $institution) {
                    $speaker->institutions()->attach($institution->id, [
                        'position' => fake()->randomElement(['Imam', 'Lecturer', 'Guest Speaker', 'Advisor']),
                        'is_primary' => fake()->boolean(30),
                        'joined_at' => fake()->date(),
                    ]);
                }
            } else {
                $speaker->update([
                    'job_title' => fake()->randomElement(['Freelance Da\'i', 'Independent Scholar', 'Religious Columnist', 'Motivation Speaker']),
                ]);
            }
        });
    }
}
