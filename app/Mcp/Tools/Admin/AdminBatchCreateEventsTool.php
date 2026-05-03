<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Admin;

use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventPrayerTime;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\RegistrationMode;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Space;
use App\Models\Speaker;
use App\Models\Venue;
use App\Support\Api\Admin\AdminResourceService;
use Generator;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly(false)]
#[IsIdempotent(false)]
#[IsDestructive(false)]
#[IsOpenWorld(false)]
class AdminBatchCreateEventsTool extends AbstractAdminWriteTool
{
    protected string $name = 'admin-batch-create-events';

    protected string $title = 'Batch Create Events';

    protected string $description = 'Use this to create multiple events in a single request. Each item uses the same field contract as admin-create-event and supports organizer_key, institution_key, venue_key, space_key, speaker_keys, and reference_keys resolved by slug or UUID. Items are processed independently; the response contains a per-row result with status created, validation_failed, unresolved_key, or error. Set validate_only=true to preview all rows and surface validation errors upfront without persisting any records. Include external_row_id per item for idempotency tracking and safe retries after interruption. Maximum 50 events per batch.';

    public function __construct(
        private readonly AdminResourceService $resourceService,
    ) {}

    public function handle(Request $request): Generator
    {
        yield Response::notification('notifications/message', [
            'level' => 'info',
            'data' => 'Validating and batch-creating events...',
        ]);

        yield $this->safeResponse(function () use ($request): ResponseFactory {
            $actor = $this->authorizeAdmin($request);

            $validated = $this->validateArguments($request, [
                'items' => ['required', 'array', 'min:1', 'max:50'],
                'items.*' => ['array'],
                'validate_only' => ['sometimes', 'boolean'],
                'apply_defaults' => ['sometimes', 'boolean'],
            ]);

            $validateOnly = (bool) ($validated['validate_only'] ?? false);
            $applyDefaults = (bool) ($validated['apply_defaults'] ?? false);
            $resourceKey = 'events';

            $schemaResponse = $this->resourceService->writeSchema(
                resourceKey: $resourceKey,
                operation: 'create',
                actor: $actor,
            );

            /** @var list<array<string, mixed>> $rawItems */
            $rawItems = $validated['items'];

            /** @var array<int, array<string, mixed>> $resolvedItems */
            $resolvedItems = [];

            /** @var array<int, array<string, mixed>> $unresolvedResults */
            $unresolvedResults = [];

            foreach ($rawItems as $index => $item) {
                if (! is_array($item)) {
                    $item = [];
                }

                $externalRowId = isset($item['external_row_id']) && is_string($item['external_row_id'])
                    ? $item['external_row_id']
                    : null;

                /** @var array<string, mixed> $itemPayload */
                $itemPayload = isset($item['payload']) && is_array($item['payload']) ? $item['payload'] : $item;

                if (array_key_exists('external_row_id', $itemPayload)) {
                    unset($itemPayload['external_row_id']);
                }

                try {
                    $builtPayload = $this->buildEventPayload($itemPayload, $schemaResponse, $applyDefaults);

                    $resolvedItem = ['payload' => $builtPayload];

                    if ($externalRowId !== null) {
                        $resolvedItem['external_row_id'] = $externalRowId;
                    }

                    $resolvedItems[$index] = $resolvedItem;
                } catch (ValidationException $exception) {
                    $result = [
                        'row' => $index,
                        'status' => 'unresolved_key',
                        'errors' => $exception->errors(),
                    ];

                    if ($externalRowId !== null) {
                        $result['external_row_id'] = $externalRowId;
                    }

                    $unresolvedResults[$index] = $result;
                }
            }

            // Build service items list (only resolved items, preserving original indices for re-mapping)
            $serviceItems = array_values($resolvedItems);

            // Map service row index (0-based position in $serviceItems) back to original index
            $serviceIndexToOriginalIndex = array_keys($resolvedItems);

            $batchResult = $this->resourceService->batchStoreRecords(
                resourceKey: $resourceKey,
                items: $serviceItems,
                actor: $actor,
                validateOnly: $validateOnly,
            );

            // Re-map service result row indices to original indices
            /** @var array<int, array<string, mixed>> $finalResults */
            $finalResults = [];

            foreach ($batchResult['data']['results'] ?? [] as $result) {
                $serviceIndex = $result['row'] ?? 0;
                $originalIndex = $serviceIndexToOriginalIndex[$serviceIndex] ?? $serviceIndex;
                $result['row'] = $originalIndex;
                $finalResults[$originalIndex] = $result;
            }

            // Merge unresolved-key failures at their original positions
            foreach ($unresolvedResults as $originalIndex => $unresolvedResult) {
                $finalResults[$originalIndex] = $unresolvedResult;
            }

            ksort($finalResults);
            $batchResult['data']['results'] = array_values($finalResults);

            // Recalculate summary with unresolved counts
            $unresolvedCount = count($unresolvedResults);
            $summary = $batchResult['data']['summary'] ?? [];

            /** @var array<string, int|bool> $summary */
            $summary['total'] = count($rawItems);

            if ($unresolvedCount > 0) {
                $summary['unresolved_key'] = $unresolvedCount;
            }

            $batchResult['data']['summary'] = $summary;

            return Response::structured($batchResult);
        });
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $schemaResponse
     * @return array<string, mixed>
     */
    private function buildEventPayload(array $item, array $schemaResponse, bool $applyDefaults): array
    {
        $payload = $item;

        unset(
            $payload['validate_only'],
            $payload['apply_defaults'],
            $payload['organizer_key'],
            $payload['institution_key'],
            $payload['venue_key'],
            $payload['space_key'],
            $payload['speaker_keys'],
            $payload['reference_keys'],
        );

        $organizerType = $this->normalizeOrganizerType($item['organizer_type'] ?? null);

        if ($organizerType !== null) {
            $payload['organizer_type'] = $organizerType;
        }

        $organizerKey = $this->normalizeOptionalString($item['organizer_key'] ?? null);

        if ($organizerKey !== null) {
            $payload['organizer_id'] = $this->resolveRecordIdentifier(
                field: 'organizer_key',
                modelClass: $organizerType === Speaker::class ? Speaker::class : Institution::class,
                key: $organizerKey,
            );
        }

        $institutionKey = $this->normalizeOptionalString($item['institution_key'] ?? null);

        if ($institutionKey !== null) {
            $payload['institution_id'] = $this->resolveRecordIdentifier(
                field: 'institution_key',
                modelClass: Institution::class,
                key: $institutionKey,
            );
        }

        $venueKey = $this->normalizeOptionalString($item['venue_key'] ?? null);

        if ($venueKey !== null) {
            $payload['venue_id'] = $this->resolveRecordIdentifier(
                field: 'venue_key',
                modelClass: Venue::class,
                key: $venueKey,
            );
        }

        $spaceKey = $this->normalizeOptionalString($item['space_key'] ?? null);

        if ($spaceKey !== null) {
            $payload['space_id'] = $this->resolveRecordIdentifier(
                field: 'space_key',
                modelClass: Space::class,
                key: $spaceKey,
            );
        }

        $speakerKeys = array_values(array_filter(
            array_map(
                fn (mixed $k): ?string => $this->normalizeOptionalString($k),
                (array) ($item['speaker_keys'] ?? [])
            ),
            static fn (?string $v): bool => $v !== null,
        ));

        if ($speakerKeys !== []) {
            $payload['speakers'] = array_values(array_map(
                fn (string $key): string => $this->resolveRecordIdentifier(
                    field: 'speaker_keys',
                    modelClass: Speaker::class,
                    key: $key,
                ),
                $speakerKeys,
            ));
        }

        $referenceKeys = array_values(array_filter(
            array_map(
                fn (mixed $k): ?string => $this->normalizeOptionalString($k),
                (array) ($item['reference_keys'] ?? [])
            ),
            static fn (?string $v): bool => $v !== null,
        ));

        if ($referenceKeys !== []) {
            $payload['references'] = array_values(array_map(
                fn (string $key): string => $this->resolveRecordIdentifier(
                    field: 'reference_keys',
                    modelClass: Reference::class,
                    key: $key,
                ),
                $referenceKeys,
            ));
        }

        if ($applyDefaults) {
            $payload = $this->payloadWithSchemaDefaults($payload, $schemaResponse);
        }

        return $payload;
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeOrganizerType(mixed $value): ?string
    {
        return match ($value) {
            'institution', Institution::class => Institution::class,
            'speaker', Speaker::class => Speaker::class,
            default => null,
        };
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function resolveRecordIdentifier(string $field, string $modelClass, string $key): string
    {
        /** @var Model $model */
        $model = new $modelClass;

        foreach ($this->lookupColumns($model) as $column) {
            if (! $this->canLookupWithValue($model, $column, $key)) {
                continue;
            }

            $record = $modelClass::query()->where($column, $key)->first();

            if ($record instanceof Model) {
                return (string) $record->getKey();
            }
        }

        throw ValidationException::withMessages([
            $field => __('The selected record key is invalid.'),
        ]);
    }

    /**
     * @return list<string>
     */
    private function lookupColumns(Model $model): array
    {
        $columns = [];

        if (in_array('slug', $model->getFillable(), true)) {
            $columns[] = 'slug';
        }

        foreach ([$model->getRouteKeyName(), $model->getKeyName()] as $column) {
            if (! in_array($column, $columns, true)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    private function canLookupWithValue(Model $model, string $column, string $value): bool
    {
        $keyName = $model->getKeyName();

        if ($column !== $keyName) {
            return true;
        }

        if ($model->getKeyType() === 'int') {
            return ctype_digit($value);
        }

        if ($keyName === 'id') {
            return Str::isUuid($value) || Str::isUlid($value);
        }

        return true;
    }

    /**
     * @return array<string, Type>
     */
    #[\Override]
    public function schema(JsonSchema $schema): array
    {
        $eventItemSchema = $schema->object([
            'external_row_id' => $schema->string()->nullable()->description('Optional caller-assigned row identifier for idempotency tracking and safe retries.'),
            'title' => $schema->string()->required()->max(255),
            'description' => $schema->string()->nullable(),
            'event_date' => $schema->string()->required()->description('Local event date in YYYY-MM-DD format.'),
            'prayer_time' => $schema->string()->required()->enum($this->enumValues(EventPrayerTime::class)),
            'custom_time' => $schema->string()->nullable()->description('Required when prayer_time is lain_waktu. HH:MM preferred.'),
            'end_time' => $schema->string()->nullable()->description('Optional end time (HH:MM).'),
            'timezone' => $schema->string()->default('Asia/Kuala_Lumpur'),
            'event_format' => $schema->string()->default(EventFormat::Physical->value)->enum($this->enumValues(EventFormat::class)),
            'visibility' => $schema->string()->default(EventVisibility::Public->value)->enum($this->enumValues(EventVisibility::class)),
            'event_url' => $schema->string()->nullable(),
            'live_url' => $schema->string()->nullable(),
            'recording_url' => $schema->string()->nullable(),
            'gender' => $schema->string()->default(EventGenderRestriction::All->value)->enum($this->enumValues(EventGenderRestriction::class)),
            'age_group' => $schema->array()->items($schema->string()->enum($this->enumValues(EventAgeGroup::class)))->default([EventAgeGroup::AllAges->value]),
            'children_allowed' => $schema->boolean()->default(false),
            'is_muslim_only' => $schema->boolean()->default(false),
            'event_type' => $schema->array()->required()->items($schema->string()->enum($this->enumValues(EventType::class))),
            'organizer_type' => $schema->string()->enum(['institution', 'speaker', Institution::class, Speaker::class])->description('Organizer model type.'),
            'organizer_key' => $schema->string()->nullable()->description('Organizer route key (slug preferred, UUID allowed).'),
            'institution_key' => $schema->string()->nullable()->description('Institution route key (slug preferred, UUID allowed).'),
            'venue_key' => $schema->string()->nullable()->description('Venue route key (slug preferred, UUID allowed).'),
            'space_key' => $schema->string()->nullable()->description('Space route key (slug preferred, UUID allowed).'),
            'speaker_keys' => $schema->array()->items($schema->string())->nullable()->description('Array of speaker route keys (slug or UUID). Resolved to speaker UUIDs and attached as speaker-role key people.'),
            'reference_keys' => $schema->array()->items($schema->string())->nullable()->description('Array of reference route keys (slug or UUID).'),
            'languages' => $schema->array()->items($schema->integer())->nullable(),
            'domain_tags' => $schema->array()->items($schema->string())->nullable(),
            'discipline_tags' => $schema->array()->items($schema->string())->nullable(),
            'source_tags' => $schema->array()->items($schema->string())->nullable(),
            'issue_tags' => $schema->array()->items($schema->string())->nullable(),
            'other_key_people' => $schema->array()->items(
                $schema->object([
                    'role' => $schema->string()->required(),
                    'speaker_id' => $schema->string()->nullable(),
                    'name' => $schema->string()->nullable(),
                    'is_public' => $schema->boolean()->default(true),
                    'notes' => $schema->string()->nullable(),
                ])
            )->nullable(),
            'series' => $schema->array()->items($schema->string())->nullable(),
            'status' => $schema->string()->default('draft')->enum(['draft', 'pending', 'approved']),
            'registration_required' => $schema->boolean()->default(false),
            'registration_mode' => $schema->string()->default(RegistrationMode::Event->value)->enum($this->enumValues(RegistrationMode::class)),
            'is_priority' => $schema->boolean()->default(false),
            'is_featured' => $schema->boolean()->default(false),
            'is_active' => $schema->boolean()->default(true),
        ])->required(['title', 'event_date', 'prayer_time', 'event_type']);

        return [
            'items' => $schema->array()->required()->min(1)->max(50)->items($eventItemSchema)->description('Array of event items to create. Each item must include title, event_date, prayer_time, and event_type. Maximum 50 events per batch.'),
            'validate_only' => $schema->boolean()->default(false)->description('When true, validates all items without persisting. Returns per-row preview or validation error details.'),
            'apply_defaults' => $schema->boolean()->default(false)->description('When true, fills schema defaults into the normalized payload visible in validate_only previews.'),
        ];
    }

    /**
     * @param  class-string<\BackedEnum>  $enumClass
     * @return list<string>
     */
    private function enumValues(string $enumClass): array
    {
        return array_values(array_column($enumClass::cases(), 'value'));
    }
}
