<?php

namespace App\Support\Timezone;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserTimezoneResolver
{
    /**
     * @var array<int, string>|null
     */
    protected static ?array $validTimezones = null;

    public static function resolve(?Request $request = null, ?string $preferredTimezone = null): string
    {
        $request ??= request();
        $sessionTimezone = $request->hasSession() ? $request->session()->get('user_timezone') : null;
        $authenticatedUserTimezone = Auth::check() ? data_get(Auth::user(), 'timezone') : null;

        $candidates = [
            $preferredTimezone,
            $authenticatedUserTimezone,
            $request->header('X-Timezone'),
            $request->cookie('user_timezone'),
            $sessionTimezone,
        ];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            if (self::isValid($candidate)) {
                return $candidate;
            }
        }

        $fallback = (string) config('app.timezone', 'UTC');

        if (self::isValid($fallback)) {
            return $fallback;
        }

        return 'UTC';
    }

    protected static function isValid(string $timezone): bool
    {
        self::$validTimezones ??= \DateTimeZone::listIdentifiers();

        return in_array($timezone, self::$validTimezones, true);
    }
}
