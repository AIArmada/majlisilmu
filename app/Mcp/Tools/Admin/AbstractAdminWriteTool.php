<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Admin;

use App\Models\User;
use App\Support\Api\Admin\AdminResourceService;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;

abstract class AbstractAdminWriteTool extends AbstractAdminTool
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function ensureMediaUploadsAreUnsupported(array $payload): void
    {
        $errors = [];

        foreach (['logo', 'cover', 'avatar', 'poster', 'gallery'] as $field) {
            if (! array_key_exists($field, $payload) || ! $this->hasMeaningfulMediaValue($payload[$field])) {
                continue;
            }

            $errors[$field] = ['Media uploads are not supported through MCP v1.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    public function shouldRegister(Request $request, AdminResourceService $resourceService): bool
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
