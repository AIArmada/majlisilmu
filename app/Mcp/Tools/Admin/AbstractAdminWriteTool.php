<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Admin;

use App\Models\User;
use App\Support\Api\Admin\AdminResourceService;
use App\Support\Location\PreferredCountryResolver;
use App\Support\Mcp\McpAuthenticatedUserResolver;
use App\Support\Mcp\McpFilePayloadNormalizer;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Support\ValidationMessages;

abstract class AbstractAdminWriteTool extends AbstractAdminTool
{
    private const ACTION_SET_FIELD = 'set_field';

    private const ACTION_CHOOSE_ONE = 'choose_one';

    private const ACTION_CHOOSE_MANY = 'choose_many';

    private const BLOCKER_REQUIRED_CHOICE = 'required_choice';

    private const BLOCKER_INVALID_CHOICE = 'invalid_choice';

    private const BLOCKER_MISSING_VALUE = 'missing_value';

    private const BLOCKER_VALIDATION_ERROR = 'validation_error';

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function ensureDestructiveMediaClearFlagsAreUnsupported(array $payload): void
    {
        $errors = [];

        foreach (['clear_logo', 'clear_cover', 'clear_avatar', 'clear_poster', 'clear_front_cover', 'clear_back_cover', 'clear_gallery', 'clear_main', 'clear_qr', 'clear_evidence'] as $field) {
            if (! array_key_exists($field, $payload) || ! $this->hasMeaningfulMediaValue($payload[$field])) {
                continue;
            }

            $errors[$field] = ['Destructive media clear flags are not supported through MCP. Upload a replacement file or array when the schema advertises that media field.'];
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

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $schemaResponse
     * @return array{payload: array<string, mixed>, temporary_paths: list<string>}
     */
    protected function normalizeMcpMediaPayload(array $payload, array $schemaResponse): array
    {
        return app(McpFilePayloadNormalizer::class)->normalize(
            $payload,
            $this->mediaFieldContractsFromSchemaResponse($schemaResponse),
        );
    }

    /**
     * @param  array{temporary_paths?: list<string>}  $normalizedMediaPayload
     */
    protected function cleanupMcpMediaPayload(array $normalizedMediaPayload): void
    {
        app(McpFilePayloadNormalizer::class)->cleanup($normalizedMediaPayload['temporary_paths'] ?? []);
    }

    /**
     * @param  array<string, mixed>  $schemaResponse
     * @return array<string, array<string, mixed>>
     */
    private function mediaFieldContractsFromSchemaResponse(array $schemaResponse): array
    {
        $fields = Arr::get($schemaResponse, 'data.schema.fields', []);

        if (! is_array($fields)) {
            return [];
        }

        $contracts = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            /** @var array<string, mixed> $field */
            $name = $field['name'] ?? null;
            $type = $field['type'] ?? null;

            if (! is_string($name) || ! is_string($type) || ! str_contains($type, 'file')) {
                continue;
            }

            $contracts[$name] = $field;
        }

        return $contracts;
    }

    public function shouldRegister(Request $request, AdminResourceService $resourceService): bool
    {
        $user = app(McpAuthenticatedUserResolver::class)->resolve($request->user());

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

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $schemaResponse
     */
    protected function validateOnlyErrorResponse(
        ValidationException $exception,
        array $payload,
        array $schemaResponse,
    ): ResponseFactory {
        return $this->errorResponse(
            ValidationMessages::from($exception),
            'validation_error',
            [
                'errors' => $exception->errors(),
                ...$this->validateOnlyRemediationPlan($payload, $schemaResponse, $exception),
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $schemaResponse
     * @return array{
     *     fix_plan: list<array<string, mixed>>,
     *     remaining_blockers: list<array<string, mixed>>,
     *     normalized_payload_preview: array<string, mixed>,
     *     can_retry: bool
     * }
     */
    protected function validateOnlyRemediationPlan(
        array $payload,
        array $schemaResponse,
        ValidationException $exception,
    ): array {
        $schema = Arr::get($schemaResponse, 'data.schema', []);
        $fieldMap = $this->fieldMapFromSchemaResponse($schemaResponse);
        $defaults = is_array($schema['defaults'] ?? null) ? $schema['defaults'] : [];
        $normalizedPayloadPreview = [...$payload];

        $fixPlan = [];
        $remainingBlockers = [];

        foreach ($exception->errors() as $field => $messages) {
            if (! is_string($field) || ! is_array($messages)) {
                continue;
            }

            $fieldDefinition = $this->fieldDefinitionForPath($field, $fieldMap);
            $currentValue = data_get($normalizedPayloadPreview, $field);
            $defaultValue = $this->defaultValueForPath($field, $defaults, $fieldDefinition);

            if ($defaultValue['exists'] && $this->isMissingRemediationValue($currentValue)) {
                data_set($normalizedPayloadPreview, $field, $defaultValue['value']);

                $fixPlan[$field] = [
                    'action' => self::ACTION_SET_FIELD,
                    'field' => $field,
                    'value' => $defaultValue['value'],
                    'auto_apply_safe' => true,
                ];

                continue;
            }

            $allowedValues = $this->allowedValuesForField($fieldDefinition);

            if ($allowedValues !== []) {
                $fixPlan[$field] = [
                    'action' => $this->allowsMultipleChoices($fieldDefinition)
                        ? self::ACTION_CHOOSE_MANY
                        : self::ACTION_CHOOSE_ONE,
                    'field' => $field,
                    'options' => $allowedValues,
                    'auto_apply_safe' => false,
                ];

                $remainingBlockers[] = [
                    'field' => $field,
                    'type' => $this->determineBlockerType($currentValue, true),
                    'options' => $allowedValues,
                    'messages' => array_values(array_map('strval', $messages)),
                ];

                continue;
            }

            $remainingBlockers[] = [
                'field' => $field,
                'type' => $this->determineBlockerType($currentValue, false),
                'messages' => array_values(array_map('strval', $messages)),
            ];
        }

        return [
            'fix_plan' => array_values($fixPlan),
            'remaining_blockers' => $remainingBlockers,
            'normalized_payload_preview' => $normalizedPayloadPreview,
            'can_retry' => $remainingBlockers === [],
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $fieldMap
     * @return array<string, mixed>|null
     */
    private function fieldDefinitionForPath(string $path, array $fieldMap): ?array
    {
        return $fieldMap[$path] ?? null;
    }

    /**
     * @param  array<string, mixed>  $schemaResponse
     * @return array<string, array<string, mixed>>
     */
    private function fieldMapFromSchemaResponse(array $schemaResponse): array
    {
        $fields = Arr::get($schemaResponse, 'data.schema.fields', []);

        if (! is_array($fields)) {
            return [];
        }

        $fieldMap = [];

        foreach ($fields as $field) {
            if (! is_array($field)) {
                continue;
            }

            $name = $field['name'] ?? null;

            if (! is_string($name) || $name === '') {
                continue;
            }

            $fieldMap[$name] = $field;
        }

        return $fieldMap;
    }

    /**
     * @param  array<string, mixed>  $defaults
     * @param  array<string, mixed>|null  $fieldDefinition
     * @return array{exists: bool, value: mixed}
     */
    private function defaultValueForPath(string $path, array $defaults, ?array $fieldDefinition): array
    {
        if (Arr::has($defaults, $path)) {
            return [
                'exists' => true,
                'value' => data_get($defaults, $path),
            ];
        }

        if (is_array($fieldDefinition) && array_key_exists('default', $fieldDefinition)) {
            return [
                'exists' => true,
                'value' => $fieldDefinition['default'],
            ];
        }

        return [
            'exists' => false,
            'value' => null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $fieldDefinition
     * @return list<string|int>
     */
    private function allowedValuesForField(?array $fieldDefinition): array
    {
        $allowedValues = $fieldDefinition['allowed_values'] ?? null;

        if (! is_array($allowedValues)) {
            return [];
        }

        // MCP write schema option lists are currently exposed as string or integer identifiers,
        // so non-scalar entries are ignored instead of being surfaced as retry suggestions.
        return array_values(array_filter(
            $allowedValues,
            static fn (mixed $value): bool => is_string($value) || is_int($value),
        ));
    }

    /**
     * @param  array<string, mixed>|null  $fieldDefinition
     */
    private function allowsMultipleChoices(?array $fieldDefinition): bool
    {
        return is_string($fieldDefinition['type'] ?? null)
            && str_starts_with((string) $fieldDefinition['type'], 'array<');
    }

    private function isMissingRemediationValue(mixed $value): bool
    {
        return match (true) {
            is_string($value) => trim($value) === '',
            is_array($value) => $value === [],
            default => $value === null,
        };
    }

    private function determineBlockerType(mixed $currentValue, bool $hasAllowedValues): string
    {
        if ($this->isMissingRemediationValue($currentValue)) {
            return $hasAllowedValues ? self::BLOCKER_REQUIRED_CHOICE : self::BLOCKER_MISSING_VALUE;
        }

        return $hasAllowedValues ? self::BLOCKER_INVALID_CHOICE : self::BLOCKER_VALIDATION_ERROR;
    }
}
