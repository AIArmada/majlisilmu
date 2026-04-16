<?php

namespace App\Data\Api\Auth;

use Spatie\LaravelData\Data;

class AuthTokenResponseData extends Data
{
    public function __construct(
        public string $message,
        public AuthTokenData $data,
    ) {}
}
