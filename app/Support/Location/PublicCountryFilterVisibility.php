<?php

namespace App\Support\Location;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

class PublicCountryFilterVisibility
{
    public const COOKIE_NAME = 'show_public_country_filters';

    public function shouldShow(?Request $request = null): bool
    {
        $request ??= request();

        return filter_var(
            $request->cookie(self::COOKIE_NAME, '0'),
            FILTER_VALIDATE_BOOL
        );
    }

    public function queue(bool $shouldShow): void
    {
        Cookie::queue(Cookie::forever(
            name: self::COOKIE_NAME,
            value: $shouldShow ? '1' : '0',
            path: '/',
            domain: config('session.domain'),
            secure: config('session.secure'),
            httpOnly: true,
            sameSite: (string) config('session.same_site', 'lax'),
        ));
    }
}
