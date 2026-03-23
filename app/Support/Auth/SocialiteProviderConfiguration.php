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

        return filled($config['client_id'] ?? null)
            && filled($config['client_secret'] ?? null)
            && filled($config['redirect'] ?? null);
    }
}
