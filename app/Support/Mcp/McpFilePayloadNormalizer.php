<?php

declare(strict_types=1);

namespace App\Support\Mcp;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

final class McpFilePayloadNormalizer
{
    /**
     * @var list<string>
     */
    public const array KNOWN_MEDIA_FIELDS = [
        'logo',
        'cover',
        'avatar',
        'poster',
        'front_cover',
        'back_cover',
        'gallery',
        'evidence',
        'qr',
        'main',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, array<string, mixed>>  $mediaFieldContracts
     * @return array{payload: array<string, mixed>, temporary_paths: list<string>}
     */
    public function normalize(array $payload, array $mediaFieldContracts): array
    {
        $temporaryPaths = [];

        try {
            $this->rejectUnsupportedKnownMediaFields($payload, $mediaFieldContracts);

            foreach ($mediaFieldContracts as $field => $contract) {
                if (! array_key_exists($field, $payload) || ! $this->hasMeaningfulValue($payload[$field])) {
                    continue;
                }

                $payload[$field] = $this->normalizeFieldValue($field, $payload[$field], $contract, $temporaryPaths);
            }
        } catch (Throwable $exception) {
            $this->cleanup($temporaryPaths);

            throw $exception;
        }

        return [
            'payload' => $payload,
            'temporary_paths' => $temporaryPaths,
        ];
    }

    /**
     * @param  list<string>  $temporaryPaths
     */
    public function cleanup(array $temporaryPaths): void
    {
        foreach ($temporaryPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, array<string, mixed>>  $mediaFieldContracts
     */
    private function rejectUnsupportedKnownMediaFields(array $payload, array $mediaFieldContracts): void
    {
        $errors = [];

        foreach (self::KNOWN_MEDIA_FIELDS as $field) {
            if (
                ! array_key_exists($field, $payload)
                || array_key_exists($field, $mediaFieldContracts)
                || ! $this->hasMeaningfulValue($payload[$field])
            ) {
                continue;
            }

            $errors[$field] = ['This media field is not supported by the selected MCP write schema.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $contract
     * @param  list<string>  $temporaryPaths
     * @return UploadedFile|list<UploadedFile>
     */
    private function normalizeFieldValue(string $field, mixed $value, array $contract, array &$temporaryPaths): UploadedFile|array
    {
        $type = $contract['type'] ?? null;
        $isMultiple = is_string($type) && str_contains($type, 'array');

        if (! $isMultiple) {
            return $this->uploadedFileFromDescriptor($field, $value, $contract, $temporaryPaths);
        }

        if (! is_array($value) || ! array_is_list($value)) {
            throw ValidationException::withMessages([
                $field => ['This MCP media field must be an array of file descriptors.'],
            ]);
        }

        $maxFiles = $this->maxFiles($contract);

        if ($maxFiles !== null && count($value) > $maxFiles) {
            throw ValidationException::withMessages([
                $field => ["This MCP media field may not contain more than {$maxFiles} files."],
            ]);
        }

        return array_values(array_map(
            fn (mixed $item, int $index): UploadedFile => $this->uploadedFileFromDescriptor("{$field}.{$index}", $item, $contract, $temporaryPaths),
            $value,
            array_keys($value),
        ));
    }

    /**
     * @param  array<string, mixed>  $contract
     * @param  list<string>  $temporaryPaths
     */
    private function uploadedFileFromDescriptor(string $field, mixed $value, array $contract, array &$temporaryPaths): UploadedFile
    {
        if (! is_array($value) || array_is_list($value)) {
            throw ValidationException::withMessages([
                $field => ['This MCP media field must be a file descriptor object.'],
            ]);
        }

        /** @var array<string, mixed> $descriptor */
        $descriptor = $value;
        $fileName = $this->stringValue($descriptor, ['filename', 'file_name', 'name']);
        $base64 = $this->stringValue($descriptor, ['content_base64', 'base64', 'data']);
        $mimeType = $this->stringValue($descriptor, ['mime_type', 'mime']);

        if ($fileName === null) {
            throw ValidationException::withMessages([
                $field => ['The MCP file descriptor must include a filename.'],
            ]);
        }

        if ($base64 === null) {
            throw ValidationException::withMessages([
                $field => ['The MCP file descriptor must include content_base64.'],
            ]);
        }

        if (preg_match('/^data:([^;]+);base64,(.*)$/s', $base64, $matches) === 1) {
            $mimeType ??= $matches[1];
            $base64 = $matches[2];
        }

        $decoded = base64_decode((string) preg_replace('/\s+/', '', $base64), true);

        if (! is_string($decoded)) {
            throw ValidationException::withMessages([
                $field => ['The MCP file descriptor content_base64 is not valid base64.'],
            ]);
        }

        $maxSizeBytes = $this->maxFileSizeKb($contract) * 1024;

        if (strlen($decoded) > $maxSizeBytes) {
            throw ValidationException::withMessages([
                $field => ['The MCP file descriptor exceeds the maximum upload size.'],
            ]);
        }

        $acceptedMimeTypes = $this->acceptedMimeTypes($contract);

        if ($mimeType !== null && $acceptedMimeTypes !== [] && ! in_array($mimeType, $acceptedMimeTypes, true)) {
            throw ValidationException::withMessages([
                $field => ['The MCP file descriptor mime_type is not allowed for this field.'],
            ]);
        }

        $temporaryFile = $this->writeTemporaryFile($fileName, $mimeType, $decoded);
        $path = $temporaryFile['path'];
        $temporaryPaths[] = $path;

        return new UploadedFile($path, $temporaryFile['file_name'], $mimeType, null, true);
    }

    /**
     * @param  array<string, mixed>  $descriptor
     * @param  list<string>  $keys
     */
    private function stringValue(array $descriptor, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $descriptor[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    /**
     * @return array{path: string, file_name: string}
     */
    private function writeTemporaryFile(string $fileName, ?string $mimeType, string $contents): array
    {
        $directory = storage_path('app/tmp/mcp-uploads');
        File::ensureDirectoryExists($directory);

        $baseName = Str::of(pathinfo($fileName, PATHINFO_FILENAME))
            ->slug()
            ->limit(80, '')
            ->toString();

        if ($baseName === '') {
            $baseName = 'upload';
        }

        $extension = $this->extensionFromMimeType($mimeType);
        $extension = $extension !== '' ? $extension : strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $safeName = $baseName.($extension !== '' ? ".{$extension}" : '');
        $path = $directory.'/'.(Str::uuid()).'-'.$safeName;

        if (file_put_contents($path, $contents) === false) {
            throw ValidationException::withMessages([
                'media' => ['The MCP file descriptor could not be staged for validation.'],
            ]);
        }

        return [
            'path' => $path,
            'file_name' => $safeName,
        ];
    }

    private function extensionFromMimeType(?string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $contract
     * @return list<string>
     */
    private function acceptedMimeTypes(array $contract): array
    {
        $mimeTypes = $contract['accepted_mime_types'] ?? [];

        if (! is_array($mimeTypes)) {
            return [];
        }

        return array_values(array_filter($mimeTypes, static fn (mixed $mimeType): bool => is_string($mimeType) && $mimeType !== ''));
    }

    /**
     * @param  array<string, mixed>  $contract
     */
    private function maxFileSizeKb(array $contract): int
    {
        $maxFileSizeKb = $contract['max_file_size_kb'] ?? null;

        if (is_int($maxFileSizeKb) && $maxFileSizeKb > 0) {
            return $maxFileSizeKb;
        }

        return (int) ceil(((int) config('media-library.max_file_size', 10 * 1024 * 1024)) / 1024);
    }

    /**
     * @param  array<string, mixed>  $contract
     */
    private function maxFiles(array $contract): ?int
    {
        $maxFiles = $contract['max_files'] ?? null;

        return is_int($maxFiles) && $maxFiles > 0 ? $maxFiles : null;
    }

    private function hasMeaningfulValue(mixed $value): bool
    {
        return match (true) {
            is_string($value) => trim($value) !== '',
            is_array($value) => $value !== [],
            is_bool($value) => $value,
            default => $value !== null,
        };
    }
}
