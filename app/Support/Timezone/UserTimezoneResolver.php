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

        $candidates = [
            $preferredTimezone,
            $request->header('X-Timezone'),
            $request->cookie('user_timezone'),
            $sessionTimezone,
        ];

        if (Auth::check()) {
            $candidates[] = data_get(Auth::user(), 'timezone');
        }

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
