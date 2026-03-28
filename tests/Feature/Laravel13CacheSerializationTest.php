<?php

use App\Enums\EventAgeGroup;
use App\Enums\TagType;
use App\Livewire\Pages\Events\Index;
use App\Models\Event;
use App\Models\Tag;
use App\Services\EventSearchService;
use App\Services\PrayerTimeService;
use App\Support\Location\PublicCountryFilterVisibility;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Nnjeim\World\Models\Language;

it('hydrates the events index state cache into the current safe payload format', function () {
    config()->set('cache.default', 'database');
    app('cache')->setDefaultDriver('database');
    Cache::flush();

    DB::table('states')->insert([
        'id' => 999,
        'country_id' => 132,
        'name' => 'Selangor',
        'country_code' => 'MY',
    ]);

    Livewire::withCookie(PublicCountryFilterVisibility::COOKIE_NAME, '1')
        ->test(Index::class)
        ->call('toggleAdvancedFiltersPanel')
        ->assertSet('showAdvancedFiltersPanel', true);

    expect(Cache::get('states_all_v1'))
        ->toBeArray()
        ->and(Cache::get('states_all_v1'))
        ->toHaveCount(1);
});

it('updates the submit event livewire component even when legacy select option caches exist', function () {
    config()->set('cache.default', 'database');
    app('cache')->setDefaultDriver('database');
    Cache::flush();
    app()->setLocale('ms');

    Tag::factory()->domain()->create([
        'status' => 'verified',
    ]);

    Cache::put(
        'submit_languages_v2',
        Language::query()
            ->whereIn('code', ['ms', 'en'])
            ->get(),
        now()->addHour(),
    );

    Cache::put(
        'submit_tags_domain_ms',
        Tag::query()
            ->where('type', TagType::Domain)
            ->whereIn('status', ['verified', 'pending'])
            ->orderBy('order_column')
            ->get()
            ->mapWithKeys(fn (Tag $tag): array => [(string) $tag->id => $tag->getTranslation('name', 'ms')]),
        now()->addHour(),
    );

    Livewire::test('pages.submit-event.create')
        ->set('data.age_group', [EventAgeGroup::Children->value])
        ->assertSet('data.age_group', [EventAgeGroup::Children->value]);

    expect(Cache::get('submit_languages_safe_v1'))->toBeArray()
        ->and(Cache::get('submit_tags_domain_ms_safe_v1'))->toBeArray();
});

it('rehydrates the default events search cache safely from the database cache store', function () {
    config()->set('cache.default', 'database');
    app('cache')->setDefaultDriver('database');
    Cache::flush();
    config()->set('scout.driver', 'database');

    Event::factory()->count(2)->create([
        'status' => 'approved',
        'visibility' => 'public',
        'published_at' => now(),
        'starts_at' => now()->addDay(),
    ]);

    $service = app(EventSearchService::class);

    Cache::put(
        'default_events_search',
        $service->search(null, [], 12, 'time'),
        now()->addMinute(),
    );

    $paginator = $service->search(null, [], 12, 'time');

    expect($paginator->total())->toBe(2)
        ->and($paginator->items())->toHaveCount(2)
        ->and(Cache::get('default_events_search_v2'))->toMatchArray([
            'total' => 2,
        ]);
});

it('rehydrates cached prayer times safely from the database cache store', function () {
    config()->set('cache.default', 'database');
    app('cache')->setDefaultDriver('database');
    Cache::flush();

    $date = Carbon::parse('2026-07-15');
    $timezone = 'America/New_York';
    $service = new PrayerTimeService;

    Http::fake([
        'api.aladhan.com/*' => Http::response([
            'data' => [
                'timings' => [
                    'Fajr' => '05:55',
                    'Dhuhr' => '13:15',
                    'Asr' => '16:40',
                    'Maghrib' => '19:20',
                    'Isha' => '20:35',
                ],
            ],
        ]),
    ]);

    $first = $service->getPrayerTimes($date, 40.7128, -74.0060, $timezone);

    expect($first)->not->toBeNull()
        ->and($first['Maghrib'])->toBeInstanceOf(Carbon::class)
        ->and($first['Maghrib']->format('H:i'))->toBe('19:20')
        ->and($first['Maghrib']->timezoneName)->toBe($timezone);

    Http::fake([
        'api.aladhan.com/*' => Http::response(null, 500),
    ]);

    $second = $service->getPrayerTimes($date, 40.7128, -74.0060, $timezone);

    expect($second)->not->toBeNull()
        ->and($second['Maghrib'])->toBeInstanceOf(Carbon::class)
        ->and($second['Maghrib']->format('H:i'))->toBe('19:20')
        ->and($second['Maghrib']->timezoneName)->toBe($timezone);
});
