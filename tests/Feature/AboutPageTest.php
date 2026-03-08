<?php

use function Pest\Laravel\get;

it('loads the about page through the primary and legacy routes', function (): void {
    get(route('about'))
        ->assertOk()
        ->assertSee('Malaysia bukan kurang majlis ilmu. Yang selalu kurang ialah jalan untuk orang menemuinya.');

    get('/about')
        ->assertOk()
        ->assertSee('Malaysia bukan kurang majlis ilmu. Yang selalu kurang ialah jalan untuk orang menemuinya.');
});

it('renders the about page in each supported locale', function (string $locale, string $expected): void {
    $this->withSession(['locale' => $locale])
        ->get(route('about'))
        ->assertOk()
        ->assertSee($expected);
})->with([
    ['en', 'Malaysia is not lacking majlis ilmu. What people need is a clearer way to find them.'],
    ['ms', 'Malaysia bukan kurang majlis ilmu. Yang selalu kurang ialah jalan untuk orang menemuinya.'],
    ['zh', '马来西亚不缺 majlis ilmu，缺的是一条让人找得到的路。'],
    ['ta', 'மலேசியாவில் majlis ilmu குறைவில்லை; அதை மக்கள் கண்டுபிடிக்கும் பாதைய்தான் குறைவு.'],
    ['jv', 'Malaysia dudu kekurangan majlis ilmu. Sing kerep kurang kuwi dalan supaya wong bisa nemokake.'],
]);

it('links to the about page from the public layout', function (): void {
    get(route('home'))
        ->assertOk()
        ->assertSee(route('about'), false)
        ->assertSee(__('About Us'));
});
