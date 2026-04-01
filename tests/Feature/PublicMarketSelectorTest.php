<?php

use App\Support\Location\PublicMarketPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders a separate market selector beside the language selector with coming soon placeholders', function () {
    $response = $this->withSession(['locale' => 'en'])
        ->get(route('about'));

    $response->assertOk()
        ->assertSee('aria-label="Market"', false)
        ->assertSee(route('market.switch', 'malaysia'), false)
        ->assertDontSee(route('market.switch', 'brunei'), false)
        ->assertDontSee(route('market.switch', 'singapore'), false)
        ->assertDontSee(route('market.switch', 'indonesia'), false)
        ->assertSee('Country')
        ->assertSee('Malaysia')
        ->assertSee('Brunei')
        ->assertSee('Singapore')
        ->assertSee('Indonesia')
        ->assertSee('title="Coming soon"', false)
        ->assertSee(route('locale.switch', 'en'), false)
        ->assertSee('English');
});

it('stores the selected market in session and cookie', function () {
    $response = $this->from(route('about'))
        ->get(route('market.switch', 'malaysia'));

    $response->assertRedirect(route('about'))
        ->assertSessionHas(PublicMarketPreference::SESSION_KEY, 'malaysia')
        ->assertPlainCookie(PublicMarketPreference::COOKIE_NAME, 'malaysia');
});
