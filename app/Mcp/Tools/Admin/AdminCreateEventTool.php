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
class AdminCreateEventTool extends AbstractAdminWriteTool
{
    protected string $name = 'admin-create-event';

    protected string $title = 'Create Event';

    protected string $description = 'Use this when you want to create a new event, optionally with cover, poster, or gallery images in the same request. Resolves organizer and location by human-readable route key — avoid raw UUIDs when a key is available. Supports speaker_keys and reference_keys to attach required speakers and references by slug or UUID. Do not use to update an existing event; use admin-update-record instead.';

    public function __construct(
        private readonly AdminResourceService $resourceService,
    ) {}

    public function handle(Request $request): Generator
    {
        yield Response::notification('notifications/message', [
            'level' => 'info',
            'data' => 'Validating and creating event...',
        ]);

        yield $this->safeResponse(function () use ($request): ResponseFactory {
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
                'speaker_keys' => ['sometimes', 'nullable', 'array'],
                'speaker_keys.*' => ['string'],
                'reference_keys' => ['sometimes', 'nullable', 'array'],
                'reference_keys.*' => ['string'],
                'languages' => ['sometimes', 'nullable', 'array'],
                'languages.*' => ['integer'],
                'domain_tags' => ['sometimes', 'nullable', 'array'],
                'domain_tags.*' => ['string'],
                'discipline_tags' => ['sometimes', 'nullable', 'array'],
                'discipline_tags.*' => ['string'],
                'source_tags' => ['sometimes', 'nullable', 'array'],
                'source_tags.*' => ['string'],
                'issue_tags' => ['sometimes', 'nullable', 'array'],
                'issue_tags.*' => ['string'],
                'other_key_people' => ['sometimes', 'nullable', 'array'],
                'other_key_people.*.role' => ['required_with:other_key_people.*.name,other_key_people.*.speaker_id', 'string'],
                'other_key_people.*.speaker_id' => ['nullable', 'string'],
                'other_key_people.*.name' => ['nullable', 'string', 'max:255'],
                'other_key_people.*.is_public' => ['sometimes', 'boolean'],
                'other_key_people.*.notes' => ['nullable', 'string', 'max:500'],
                'series' => ['sometimes', 'nullable', 'array'],
                'series.*' => ['string'],
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
            $this->enforceEventMediaDescriptorsHaveContentSource($payload);

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
            $payload['speaker_keys'],
            $payload['reference_keys'],
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

        $speakerKeys = array_values(array_filter(
            array_map(
                fn (mixed $k): ?string => $this->normalizeOptionalString($k),
                (array) ($validated['speaker_keys'] ?? [])
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
                (array) ($validated['reference_keys'] ?? [])
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
            'speaker_keys' => $schema->array()->items($schema->string())->nullable()->description('Array of speaker route keys (slug preferred, UUID allowed). Each entry is resolved to a speaker UUID and attached as a speaker-role key person. Required for event types that mandate a speaker: kuliah_ceramah, kelas_daurah, talim, forum, seminar_konvensyen, tazkirah.'),
            'reference_keys' => $schema->array()->items($schema->string())->nullable()->description('Array of reference route keys (slug preferred, UUID allowed). Each entry is resolved to a reference UUID and linked to the event.'),
            'languages' => $schema->array()->items($schema->integer())->nullable()->description('Array of language record IDs (integers) for the event language(s). Use admin-list-records on the languages resource to discover available IDs.'),
            'domain_tags' => $schema->array()->items($schema->string())->nullable()->description('Array of domain/category tag UUIDs (max 3). Use admin-list-records on the tags resource filtered by type=domain.'),
            'discipline_tags' => $schema->array()->items($schema->string())->nullable()->description('Array of discipline/field-of-study tag UUIDs. Use admin-list-records on the tags resource filtered by type=discipline.'),
            'source_tags' => $schema->array()->items($schema->string())->nullable()->description('Array of source tag UUIDs (e.g. Quran, Hadith). Use admin-list-records on the tags resource filtered by type=source.'),
            'issue_tags' => $schema->array()->items($schema->string())->nullable()->description('Array of issue/theme tag UUIDs. Use admin-list-records on the tags resource filtered by type=issue.'),
            'other_key_people' => $schema->array()->items(
                $schema->object([
                    'role' => $schema->string()->required()->description('One of the non-speaker EventKeyPersonRole values: moderator, khatib, imam, bilal, pic, other.'),
                    'speaker_id' => $schema->string()->nullable()->description('UUID of an existing speaker profile. Required when name is omitted.'),
                    'name' => $schema->string()->nullable()->description('Display name. Required when speaker_id is omitted.'),
                    'is_public' => $schema->boolean()->default(true),
                    'notes' => $schema->string()->nullable(),
                ])
            )->nullable()->description('Optional non-speaker key people (moderators, khatib, imam, bilal, PIC, etc.).'),
            'series' => $schema->array()->items($schema->string())->nullable()->description('Array of series UUIDs to link the event to.'),
            'cover' => $schema->object([
                'filename' => $schema->string()->required(),
                'content_base64' => $schema->string()->nullable(),
                'content_url' => $schema->string()->nullable(),
                'mime_type' => $schema->string()->nullable(),
            ])->nullable()->description('Optional event cover image descriptor (16:9). Pass {content_base64, filename} or {content_url, filename}.'),
            'poster' => $schema->object([
                'filename' => $schema->string()->required(),
                'content_base64' => $schema->string()->nullable(),
                'content_url' => $schema->string()->nullable(),
                'mime_type' => $schema->string()->nullable(),
            ])->nullable()->description('Optional event poster image descriptor (4:5). Pass {content_base64, filename} or {content_url, filename}.'),
            'gallery' => $schema->array()->items(
                $schema->object([
                    'filename' => $schema->string()->required(),
                    'content_base64' => $schema->string()->nullable(),
                    'content_url' => $schema->string()->nullable(),
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
                'openai/note' => 'You may send event fields and image descriptors together in one call. For media fields cover/poster/gallery pass {content_base64, filename} or {content_url, filename}.',
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function enforceEventMediaDescriptorsHaveContentSource(array $payload): void
    {
        $this->enforceDescriptorFieldHasContentSource($payload, 'cover');
        $this->enforceDescriptorFieldHasContentSource($payload, 'poster');

        $gallery = $payload['gallery'] ?? null;

        if (! is_array($gallery)) {
            return;
        }

        foreach ($gallery as $index => $descriptor) {
            if (! is_array($descriptor)) {
                continue;
            }

            $this->assertDescriptorHasContentSource(
                descriptor: $descriptor,
                field: "gallery.{$index}",
            );
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function enforceDescriptorFieldHasContentSource(array $payload, string $field): void
    {
        $descriptor = $payload[$field] ?? null;

        if (! is_array($descriptor)) {
            return;
        }

        $this->assertDescriptorHasContentSource(
            descriptor: $descriptor,
            field: $field,
        );
    }

    /**
     * @param  array<string, mixed>  $descriptor
     */
    private function assertDescriptorHasContentSource(array $descriptor, string $field): void
    {
        $base64Value = $descriptor['content_base64']
            ?? $descriptor['contentBase64']
            ?? $descriptor['base64']
            ?? $descriptor['data']
            ?? null;

        $contentUrl = $descriptor['content_url']
            ?? $descriptor['contentUrl']
            ?? $descriptor['url']
            ?? null;

        if (is_string($base64Value) && trim($base64Value) !== '') {
            return;
        }

        if (is_string($contentUrl) && trim($contentUrl) !== '') {
            return;
        }

        throw ValidationException::withMessages([
            $field => ['Event media uploads require either content_base64 or content_url.'],
        ]);
    }
}
