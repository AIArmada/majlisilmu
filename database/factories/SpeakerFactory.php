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
        $honorificsMale = ['Ustaz', 'Dr.', 'Prof.', 'Tuan Haji'];
        $honorificsFemale = ['Ustazah', 'Dr.', 'Prof.', 'Puan Hajah'];

        $isFemale = fake()->boolean(45);
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

        if (fake()->boolean(25)) {
            $honorific = $isFemale
                ? fake()->randomElement($honorificsFemale)
                : fake()->randomElement($honorificsMale);
            $name = $honorific.' '.$name;
        }

        return [
            'name' => $name,
            'slug' => Str::slug($name.'-'.fake()->unique()->numerify('###')),
            'bio' => fake()->optional()->paragraph(),
            'avatar_url' => null,
            'status' => fake()->randomElement(['unverified', 'pending', 'verified']),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (\App\Models\Speaker $speaker) {
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
        });
    }
}
