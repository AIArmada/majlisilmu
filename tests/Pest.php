<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->beforeEach(function () {
        config()->set('services.turnstile.enabled', false);
        config()->set('services.turnstile.site_key', null);
        config()->set('services.turnstile.secret_key', null);

        // Clear tag option caches to prevent stale data in tests
        foreach (['domain', 'discipline', 'source', 'issue'] as $type) {
            \Illuminate\Support\Facades\Cache::forget("submit_tags_{$type}_ms");
            \Illuminate\Support\Facades\Cache::forget("submit_tags_{$type}_en");
        }

        // Seed common languages for tests that use the submit event form
        $languages = [
            ['id' => 7, 'code' => 'ar', 'name' => 'Arabic', 'name_native' => 'العربية', 'dir' => 'rtl'],
            ['id' => 30, 'code' => 'zh', 'name' => 'Chinese', 'name_native' => '中文', 'dir' => 'ltr'],
            ['id' => 40, 'code' => 'en', 'name' => 'English', 'name_native' => 'English', 'dir' => 'ltr'],
            ['id' => 64, 'code' => 'id', 'name' => 'Indonesian', 'name_native' => 'Bahasa Indonesia', 'dir' => 'ltr'],
            ['id' => 74, 'code' => 'jv', 'name' => 'Javanese', 'name_native' => 'ꦧꦱꦗꦮ', 'dir' => 'ltr'],
            ['id' => 101, 'code' => 'ms', 'name' => 'Malay', 'name_native' => 'bahasa Melayu', 'dir' => 'ltr'],
            ['id' => 154, 'code' => 'ta', 'name' => 'Tamil', 'name_native' => 'தமிழ்', 'dir' => 'ltr'],
        ];

        \Illuminate\Support\Facades\DB::table('languages')->insertOrIgnore($languages);
    })
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function fakePrayerTimesApi(): void
{
    \Illuminate\Support\Facades\Http::fake([
        'api.aladhan.com/*' => \Illuminate\Support\Facades\Http::response([
            'code' => 200,
            'status' => 'OK',
            'data' => [
                'timings' => [
                    'Fajr' => '05:50',
                    'Dhuhr' => '13:15',
                    'Asr' => '16:40',
                    'Maghrib' => '19:25',
                    'Isha' => '20:35',
                ],
            ],
        ], 200),
    ]);
}
