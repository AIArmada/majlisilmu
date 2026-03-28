<?php

namespace App\Support\Auth;

final class SocialiteProviderConfiguration
{
    public static function isConfigured(string $provider): bool
    {
        $config = config("services.{$provider}");

        if (! is_array($config)) {
            return false;
        }

        $isConfigured = filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null)
            && filled($config['redirect'] ?? null);

        if (! $isConfigured) {
            return false;
        }

        // Google OAuth does not accept arbitrary `.test` callback domains, so do not
        // expose a broken social login CTA on local Herd-style environments.
        return ! (app()->environment('local') && self::usesUnsupportedLocalRedirect($config['redirect'] ?? null));
    }

    private static function usesUnsupportedLocalRedirect(mixed $redirect): bool
    {
        if (! is_string($redirect) || $redirect === '') {
            return false;
        }

        $host = parse_url($redirect, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return false;
        }

        return $host === '.test' || str_ends_with($host, '.test');
    }
}
