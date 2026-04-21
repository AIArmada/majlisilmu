<?php

namespace App\Http\Requests;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class NetworkDiagnosticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (! $this->diagnosticsEnabled()) {
            return false;
        }

        $configuredToken = trim((string) $this->envString('NETWORK_DIAGNOSTICS_TOKEN', ''));

        if ($configuredToken === '') {
            return true;
        }

        $providedToken = $this->query('token');

        return is_string($providedToken) && hash_equals($configuredToken, $providedToken);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'token' => ['nullable', 'string'],
        ];
    }

    /**
     * @throws AuthorizationException
     */
    protected function failedAuthorization(): void
    {
        if (! $this->diagnosticsEnabled()) {
            throw new AuthorizationException('Network diagnostics are disabled for this environment.');
        }

        throw new AuthorizationException('A valid diagnostics token is required to access this route.');
    }

    protected function diagnosticsEnabled(): bool
    {
        return $this->envBoolean('NETWORK_DIAGNOSTICS_ENABLED', true);
    }

    protected function envBoolean(string $key, bool $default = false): bool
    {
        $value = $this->envString($key);

        if ($value === null) {
            return $default;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return is_bool($normalized) ? $normalized : $default;
    }

    protected function envString(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false) {
            return $default;
        }

        $resolved = trim((string) $value);

        return $resolved !== '' ? $resolved : $default;
    }
}
