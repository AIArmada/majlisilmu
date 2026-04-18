<?php

declare(strict_types=1);

namespace App\Support\Mcp;

final class McpWriteSchemaFormatter
{
    /**
     * @var list<string>
     */
    private const array MEDIA_FIELDS = [
        'logo',
        'cover',
        'avatar',
        'poster',
        'front_cover',
        'back_cover',
        'gallery',
    ];

    /**
     * @param  array<string, mixed>  $schema
     * @param  array<string, mixed>  $toolArguments
     * @return array<string, mixed>
     */
    public function formatSchema(array $schema, string $tool, array $toolArguments): array
    {
        $fields = is_array($schema['fields'] ?? null) ? $schema['fields'] : [];
        $unsupportedFields = $this->unsupportedMediaFields($fields);

        $schema['transport'] = 'mcp';
        $schema['tool'] = $tool;
        $schema['tool_arguments'] = $toolArguments;
        $schema['endpoint'] = null;
        $schema['content_type'] = 'application/json';
        $schema['media_uploads_supported'] = false;
        $schema['unsupported_fields'] = $unsupportedFields;
        $schema['fields'] = $this->annotateFields($fields, $unsupportedFields);

        return $schema;
    }

    /**
     * @param  array<int, mixed>  $fields
     * @return list<string>
     */
    private function unsupportedMediaFields(array $fields): array
    {
        $unsupported = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $name = $this->fieldName($field);

            if ($name === null || ! $this->isMediaField($field, $name)) {
                continue;
            }

            $unsupported[] = $name;
        }

        return array_values(array_unique($unsupported));
    }

    /**
     * @param  array<int, mixed>  $fields
     * @param  list<string>  $unsupportedFields
     * @return list<array<string, mixed>>
     */
    private function annotateFields(array $fields, array $unsupportedFields): array
    {
        $annotated = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            /** @var array<string, mixed> $field */
            $name = $this->fieldName($field);

            if ($name !== null && in_array($name, $unsupportedFields, true)) {
                $field['supported'] = false;
                $field['unsupported_reason'] = 'Media uploads are not supported through MCP v1.';
            }

            $annotated[] = $field;
        }

        return $annotated;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function fieldName(array $field): ?string
    {
        $name = $field['name'] ?? null;

        return is_string($name) && $name !== '' ? $name : null;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private function isMediaField(array $field, string $name): bool
    {
        $type = $field['type'] ?? null;

        return in_array($name, self::MEDIA_FIELDS, true)
            || (is_string($type) && str_contains($type, 'file'));
    }
}
