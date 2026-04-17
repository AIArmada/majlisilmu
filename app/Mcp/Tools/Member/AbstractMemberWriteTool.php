<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Member;

use App\Models\User;
use App\Support\Api\Member\MemberResourceService;
use App\Support\Location\PreferredCountryResolver;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

abstract class AbstractMemberWriteTool extends AbstractMemberTool
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function ensureMediaUploadsAreUnsupported(array $payload): void
    {
        $errors = [];

        foreach (['logo', 'cover', 'avatar', 'poster', 'front_cover', 'back_cover', 'gallery'] as $field) {
            if (! array_key_exists($field, $payload) || ! $this->hasMeaningfulMediaValue($payload[$field])) {
                continue;
            }

            $errors[$field] = ['Media uploads are not supported through MCP v1.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function normalizePayloadForWriteTool(string $resourceKey, array $payload): array
    {
        if ($resourceKey !== 'speakers' || ! is_array($payload['address'] ?? null) || $payload['address'] !== []) {
            return $payload;
        }

        $payload['address'] = [
            'country_id' => app(PreferredCountryResolver::class)->resolveId(),
        ];

        return $payload;
    }

    public function shouldRegister(Request $request, MemberResourceService $resourceService): bool
    {
        $user = $request->user();

        return $user instanceof User && $resourceService->hasAnyWritableResourceAccess($user);
    }

    protected function hasMeaningfulMediaValue(mixed $value): bool
    {
        return match (true) {
            is_string($value) => trim($value) !== '',
            is_array($value) => $value !== [],
            is_bool($value) => $value,
            default => $value !== null,
        };
    }
}
