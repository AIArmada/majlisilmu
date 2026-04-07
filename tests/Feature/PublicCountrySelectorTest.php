<?php

use App\Support\Location\PublicCountryPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders a separate country selector beside the language selector with coming soon placeholders', function () {
    $response = $this->withSession(['locale' => 'en'])
        ->get(route('about'));

    $response->assertOk()
        ->assertSee('aria-label="Country"', false)
        ->assertSee(route('country.switch', 'malaysia'), false)
        ->assertDontSee(route('country.switch', 'brunei'), false)
        ->assertDontSee(route('country.switch', 'singapore'), false)
        ->assertDontSee(route('country.switch', 'indonesia'), false)
        ->assertSee('Country')
        ->assertSee('Malaysia')
        ->assertSee('Brunei')
        ->assertSee('Singapore')
        ->assertSee('Indonesia')
        ->assertSee('title="Coming soon"', false)
        ->assertSee(route('locale.switch', 'en'), false)
        ->assertSee('English');
});

it('translates the country switcher label in Malay', function () {
    $response = $this->withSession(['locale' => 'ms'])
        ->get(route('about'));

    $response->assertOk()
        ->assertSee('aria-label="Negara"', false)
        ->assertSee('Negara');
});

it('stores the selected country in session and cookie', function () {
    $response = $this->from(route('about'))
        ->get(route('country.switch', 'malaysia'));

    $response->assertRedirect(route('about'))
        ->assertSessionHas(PublicCountryPreference::SESSION_KEY, 'malaysia')
        ->assertPlainCookie(PublicCountryPreference::COOKIE_NAME, 'malaysia');
});
