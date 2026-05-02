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
use App\Models\Space;
use App\Models\Speaker;
use App\Models\Venue;
use App\Support\Api\Admin\AdminResourceService;
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
class AdminCreateEventTool extends AbstractAdminWriteTool
{
    protected string $name = 'admin-create-event';

    protected string $title = 'Create Event';

    protected string $description = 'Use this when you want to create a new event, optionally with cover, poster, or gallery images in the same request. Resolves organizer and location by human-readable route key — avoid raw UUIDs when a key is available. Do not use to update an existing event; use admin-update-record instead.';

    public function __construct(
        private readonly AdminResourceService $resourceService,
    ) {}

    public function handle(Request $request): ResponseFactory|Response
    {
        return $this->safeResponse(function () use ($request): ResponseFactory {
            $actor = $this->authorizeAdmin($request);

            $validated = $this->validateArguments($request, [
                'title' => ['required', 'string', 'max:255'],
                'description' => ['nullable'],
                'event_date' => ['required', 'date'],
                'prayer_time' => ['required', 'string'],
                'custom_time' => ['nullable', 'string', 'max:32'],
                'end_time' => ['nullable', 'string', 'max:32'],
                'timezone' => ['sometimes', 'string', 'max:64'],
                'event_format' => ['sometimes', 'string'],
                'visibility' => ['sometimes', 'string'],
                'event_url' => ['nullable', 'url', 'max:255'],
                'live_url' => ['nullable', 'url', 'max:255'],
                'recording_url' => ['nullable', 'url', 'max:255'],
                'gender' => ['sometimes', 'string'],
                'age_group' => ['sometimes', 'array', 'min:1'],
                'age_group.*' => ['string'],
                'children_allowed' => ['sometimes', 'boolean'],
                'is_muslim_only' => ['sometimes', 'boolean'],
                'event_type' => ['required', 'array', 'min:1'],
                'event_type.*' => ['string'],
                'organizer_type' => ['nullable', 'string', 'in:institution,speaker,'.Institution::class.','.Speaker::class],
                'organizer_key' => ['nullable', 'string'],
                'institution_key' => ['nullable', 'string'],
                'venue_key' => ['nullable', 'string'],
                'space_key' => ['nullable', 'string'],
                'cover' => ['sometimes', 'array'],
                'poster' => ['sometimes', 'array'],
                'gallery' => ['sometimes', 'array', 'max:10'],
                'gallery.*' => ['array'],
                'status' => ['sometimes', 'string', 'in:draft,pending,approved'],
                'registration_required' => ['sometimes', 'boolean'],
                'registration_mode' => ['sometimes', 'string'],
                'is_priority' => ['sometimes', 'boolean'],
                'is_featured' => ['sometimes', 'boolean'],
                'is_active' => ['sometimes', 'boolean'],
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

            $payload = $this->buildEventPayload($validated);

            $this->ensureDestructiveMediaClearFlagsAreUnsupported($payload);

            $normalizedMediaPayload = $this->normalizeMcpMediaPayload($payload, $schemaResponse);

            try {
                if ($validateOnly && $applyDefaults) {
                    $normalizedMediaPayload['payload'] = $this->payloadWithSchemaDefaults($normalizedMediaPayload['payload'], $schemaResponse);
                }

                /** @var array<string, mixed> $normalizedPayload */
                $normalizedPayload = $normalizedMediaPayload['payload'];

                return Response::structured($this->resourceService->storeRecord(
                    resourceKey: $resourceKey,
                    payload: $normalizedPayload,
                    actor: $actor,
                    validateOnly: $validateOnly,
                ));
            } catch (ValidationException $exception) {
                return $this->writeValidationErrorResponse(
                    exception: $exception,
                    payload: $payload,
                    schemaResponse: $schemaResponse,
                    resourceKey: $resourceKey,
                    operation: 'create',
                    validateOnly: $validateOnly,
                    applyDefaults: $applyDefaults,
                );
            } finally {
                $this->cleanupMcpMediaPayload($normalizedMediaPayload);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function buildEventPayload(array $validated): array
    {
        $payload = $validated;

        unset(
            $payload['validate_only'],
            $payload['apply_defaults'],
            $payload['organizer_key'],
            $payload['institution_key'],
            $payload['venue_key'],
            $payload['space_key'],
        );

        $organizerType = $this->normalizeOrganizerType($validated['organizer_type'] ?? null);

        if ($organizerType !== null) {
            $payload['organizer_type'] = $organizerType;
        }

        $organizerKey = $this->normalizeOptionalString($validated['organizer_key'] ?? null);

        if ($organizerKey !== null) {
            $payload['organizer_id'] = $this->resolveRecordIdentifier(
                field: 'organizer_key',
                modelClass: $organizerType === Speaker::class ? Speaker::class : Institution::class,
                key: $organizerKey,
            );
        }

        $institutionKey = $this->normalizeOptionalString($validated['institution_key'] ?? null);

        if ($institutionKey !== null) {
            $payload['institution_id'] = $this->resolveRecordIdentifier(
                field: 'institution_key',
                modelClass: Institution::class,
                key: $institutionKey,
            );
        }

        $venueKey = $this->normalizeOptionalString($validated['venue_key'] ?? null);

        if ($venueKey !== null) {
            $payload['venue_id'] = $this->resolveRecordIdentifier(
                field: 'venue_key',
                modelClass: Venue::class,
                key: $venueKey,
            );
        }

        $spaceKey = $this->normalizeOptionalString($validated['space_key'] ?? null);

        if ($spaceKey !== null) {
            $payload['space_id'] = $this->resolveRecordIdentifier(
                field: 'space_key',
                modelClass: Space::class,
                key: $spaceKey,
            );
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
        return [
            'title' => $schema->string()->required()->max(255),
            'description' => $schema->string()->nullable(),
            'event_date' => $schema->string()->required()->description('Local event date in YYYY-MM-DD format.'),
            'prayer_time' => $schema->string()->required()->enum($this->enumValues(EventPrayerTime::class)),
            'custom_time' => $schema->string()->nullable()->description('Required when prayer_time is lain_waktu. HH:MM preferred.'),
            'end_time' => $schema->string()->nullable()->description('Optional end time (HH:MM).'),
            'timezone' => $schema->string()->default('Asia/Kuala_Lumpur'),
            'event_format' => $schema->string()->default(EventFormat::Physical->value)->enum($this->enumValues(EventFormat::class)),
            'visibility' => $schema->string()->default(EventVisibility::Public->value)->enum($this->enumValues(EventVisibility::class)),
            'event_url' => $schema->string()->nullable()->description('Public event URL when applicable.'),
            'live_url' => $schema->string()->nullable()->description('Livestream URL when applicable.'),
            'recording_url' => $schema->string()->nullable()->description('Recording URL when applicable.'),
            'gender' => $schema->string()->default(EventGenderRestriction::All->value)->enum($this->enumValues(EventGenderRestriction::class)),
            'age_group' => $schema->array()->items($schema->string()->enum($this->enumValues(EventAgeGroup::class)))->default([EventAgeGroup::AllAges->value]),
            'children_allowed' => $schema->boolean()->default(false),
            'is_muslim_only' => $schema->boolean()->default(false),
            'event_type' => $schema->array()->required()->items($schema->string()->enum($this->enumValues(EventType::class))),
            'organizer_type' => $schema->string()->enum(['institution', 'speaker', Institution::class, Speaker::class])->description('Organizer model type. Prefer institution or speaker.'),
            'organizer_key' => $schema->string()->nullable()->description('Organizer route key (slug preferred, UUID allowed).'),
            'institution_key' => $schema->string()->nullable()->description('Institution route key (slug preferred, UUID allowed).'),
            'venue_key' => $schema->string()->nullable()->description('Venue route key (slug preferred, UUID allowed).'),
            'space_key' => $schema->string()->nullable()->description('Space route key (slug preferred, UUID allowed).'),
            'cover' => $schema->object([
                'filename' => $schema->string()->required(),
                'download_url' => $schema->string()->nullable(),
                'content_base64' => $schema->string()->nullable(),
                'file_id' => $schema->string()->nullable(),
                'mime_type' => $schema->string()->nullable(),
            ])->nullable()->description('Optional event cover image descriptor (16:9). Pass {download_url, file_id, filename} or {content_base64, filename}.'),
            'poster' => $schema->object([
                'filename' => $schema->string()->required(),
                'download_url' => $schema->string()->nullable(),
                'content_base64' => $schema->string()->nullable(),
                'file_id' => $schema->string()->nullable(),
                'mime_type' => $schema->string()->nullable(),
            ])->nullable()->description('Optional event poster image descriptor (4:5). Pass {download_url, file_id, filename} or {content_base64, filename}.'),
            'gallery' => $schema->array()->items(
                $schema->object([
                    'filename' => $schema->string()->required(),
                    'download_url' => $schema->string()->nullable(),
                    'content_base64' => $schema->string()->nullable(),
                    'file_id' => $schema->string()->nullable(),
                    'mime_type' => $schema->string()->nullable(),
                ])
            )->nullable()->description('Optional gallery image descriptors (max 10 items).'),
            'status' => $schema->string()->default('draft')->enum(['draft', 'pending', 'approved']),
            'registration_required' => $schema->boolean()->default(false),
            'registration_mode' => $schema->string()->default(RegistrationMode::Event->value)->enum($this->enumValues(RegistrationMode::class)),
            'is_priority' => $schema->boolean()->default(false),
            'is_featured' => $schema->boolean()->default(false),
            'is_active' => $schema->boolean()->default(true),
            'validate_only' => $schema->boolean()->default(false),
            'apply_defaults' => $schema->boolean()->default(false),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    public function toArray(): array
    {
        $tool = parent::toArray();

        $tool['_meta'] = array_merge(
            is_array($tool['_meta'] ?? null) ? $tool['_meta'] : [],
            [
                'openai/note' => 'You may send event fields and image descriptors together in one call. For media fields cover/poster/gallery use {download_url, file_id, filename} or {content_base64, filename}.',
                'openai/fileParams' => [
                    'cover' => ['download_url', 'file_id'],
                    'poster' => ['download_url', 'file_id'],
                    'gallery[]' => ['download_url', 'file_id'],
                ],
            ],
        );

        return $tool;
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
