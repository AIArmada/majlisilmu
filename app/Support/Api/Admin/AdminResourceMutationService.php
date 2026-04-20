<?php

namespace App\Support\Api\Admin;

use App\Actions\Events\SaveAdminEventAction;
use App\Actions\Institutions\SaveInstitutionAction;
use App\Actions\References\SaveReferenceAction;
use App\Actions\Speakers\SaveSpeakerAction;
use App\Actions\Subdistricts\SaveSubdistrictAction;
use App\Actions\Venues\SaveVenueAction;
use App\Enums\ContactCategory;
use App\Enums\ContactType;
use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventKeyPersonRole;
use App\Enums\EventPrayerTime;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\Gender;
use App\Enums\Honorific;
use App\Enums\InstitutionType;
use App\Enums\PostNominal;
use App\Enums\PreNominal;
use App\Enums\ReferenceType;
use App\Enums\RegistrationMode;
use App\Enums\SocialMediaPlatform;
use App\Enums\VenueType;
use App\Filament\Resources\Events\EventResource;
use App\Filament\Resources\Institutions\InstitutionResource;
use App\Filament\Resources\References\ReferenceResource;
use App\Filament\Resources\Speakers\SpeakerResource;
use App\Filament\Resources\Subdistricts\SubdistrictResource;
use App\Filament\Resources\Venues\VenueResource;
use App\Forms\SharedFormSchema;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\Speaker;
use App\Models\Subdistrict;
use App\Models\User;
use App\Models\Venue;
use App\Services\ContributionEntityMutationService;
use App\Support\Location\FederalTerritoryLocation;
use App\Support\Location\PreferredCountryResolver;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Spatie\MediaLibrary\HasMedia;

class AdminResourceMutationService
{
    public function __construct(
        private readonly ContributionEntityMutationService $contributionEntityMutationService,
        private readonly SaveAdminEventAction $saveAdminEventAction,
        private readonly SaveInstitutionAction $saveInstitutionAction,
        private readonly SaveReferenceAction $saveReferenceAction,
        private readonly SaveSpeakerAction $saveSpeakerAction,
        private readonly SaveSubdistrictAction $saveSubdistrictAction,
        private readonly SaveVenueAction $saveVenueAction,
    ) {}

    /**
     * @param  class-string  $resourceClass
     */
    public function supports(string $resourceClass): bool
    {
        return in_array($resourceClass, [
            EventResource::class,
            InstitutionResource::class,
            ReferenceResource::class,
            SpeakerResource::class,
            SubdistrictResource::class,
            VenueResource::class,
        ], true);
    }

    /**
     * @param  class-string  $resourceClass
     * @return array<string, mixed>
     */
    public function schema(string $resourceClass, string $resourceKey, string $operation = 'create', ?Model $record = null): array
    {
        $updating = $operation === 'update';
        $defaults = $updating && $record instanceof Model
            ? $this->defaultsForRecord($record)
            : $this->defaultsForCreate($resourceClass);

        return match ($resourceClass) {
            EventResource::class => [
                'resource_key' => $resourceKey,
                'operation' => $operation,
                'method' => $updating ? 'PUT' : 'POST',
                'endpoint' => $updating && $record instanceof Model
                    ? route('api.admin.resources.update', ['resourceKey' => $resourceKey, 'recordKey' => $this->recordKey($record)], false)
                    : route('api.admin.resources.store', ['resourceKey' => $resourceKey], false),
                'content_type' => 'multipart/form-data',
                'slug_behavior' => 'auto_managed',
                'defaults' => $defaults,
                'current_media' => $record instanceof Event ? $this->mediaState($record, ['poster', 'gallery']) : null,
                'fields' => $this->eventFields(),
                'catalogs' => [],
                'conditional_rules' => [
                    ['field' => 'custom_time', 'required_when' => ['prayer_time' => [EventPrayerTime::LainWaktu->value]]],
                    ['field' => 'organizer_id', 'required_when' => ['organizer_type' => [Institution::class, Speaker::class]]],
                ],
            ],
            InstitutionResource::class => [
                'resource_key' => $resourceKey,
                'operation' => $operation,
                'method' => $updating ? 'PUT' : 'POST',
                'endpoint' => $updating && $record instanceof Model
                    ? route('api.admin.resources.update', ['resourceKey' => $resourceKey, 'recordKey' => $this->recordKey($record)], false)
                    : route('api.admin.resources.store', ['resourceKey' => $resourceKey], false),
                'content_type' => 'multipart/form-data',
                'slug_behavior' => 'auto_managed',
                'defaults' => $defaults,
                'current_media' => $record instanceof Institution ? $this->mediaState($record, ['logo', 'cover', 'gallery']) : null,
                'fields' => $this->institutionFields($updating),
                'catalogs' => $this->addressCatalogs('address'),
                'conditional_rules' => [],
            ],
            ReferenceResource::class => [
                'resource_key' => $resourceKey,
                'operation' => $operation,
                'method' => $updating ? 'PUT' : 'POST',
                'endpoint' => $updating && $record instanceof Model
                    ? route('api.admin.resources.update', ['resourceKey' => $resourceKey, 'recordKey' => $this->recordKey($record)], false)
                    : route('api.admin.resources.store', ['resourceKey' => $resourceKey], false),
                'content_type' => 'multipart/form-data',
                'slug_behavior' => 'auto_managed',
                'defaults' => $defaults,
                'current_media' => $record instanceof Reference ? $this->mediaState($record, ['front_cover', 'back_cover', 'gallery']) : null,
                'fields' => $this->referenceFields(),
                'catalogs' => [],
                'conditional_rules' => [],
            ],
            SpeakerResource::class => [
                'resource_key' => $resourceKey,
                'operation' => $operation,
                'method' => $updating ? 'PUT' : 'POST',
                'endpoint' => $updating && $record instanceof Model
                    ? route('api.admin.resources.update', ['resourceKey' => $resourceKey, 'recordKey' => $this->recordKey($record)], false)
                    : route('api.admin.resources.store', ['resourceKey' => $resourceKey], false),
                'content_type' => 'multipart/form-data',
                'slug_behavior' => 'auto_managed',
                'defaults' => $defaults,
                'current_media' => $record instanceof Speaker ? $this->mediaState($record, ['avatar', 'cover', 'gallery']) : null,
                'fields' => $this->speakerFields($updating),
                'catalogs' => $this->addressCatalogs('address'),
                'conditional_rules' => [
                    ['field' => 'job_title', 'required_when' => ['is_freelance' => [true]]],
                ],
            ],
            VenueResource::class => [
                'resource_key' => $resourceKey,
                'operation' => $operation,
                'method' => $updating ? 'PUT' : 'POST',
                'endpoint' => $updating && $record instanceof Model
                    ? route('api.admin.resources.update', ['resourceKey' => $resourceKey, 'recordKey' => $this->recordKey($record)], false)
                    : route('api.admin.resources.store', ['resourceKey' => $resourceKey], false),
                'content_type' => 'multipart/form-data',
                'slug_behavior' => 'auto_managed',
                'defaults' => $defaults,
                'current_media' => $record instanceof Venue ? $this->mediaState($record, ['cover', 'gallery']) : null,
                'fields' => $this->venueFields(),
                'catalogs' => $this->addressCatalogs('address'),
                'conditional_rules' => [],
            ],
            SubdistrictResource::class => [
                'resource_key' => $resourceKey,
                'operation' => $operation,
                'method' => $updating ? 'PUT' : 'POST',
                'endpoint' => $updating && $record instanceof Model
                    ? route('api.admin.resources.update', ['resourceKey' => $resourceKey, 'recordKey' => $this->recordKey($record)], false)
                    : route('api.admin.resources.store', ['resourceKey' => $resourceKey], false),
                'content_type' => 'application/json',
                'slug_behavior' => 'not_applicable',
                'defaults' => $defaults,
                'fields' => $this->subdistrictFields(),
                'catalogs' => $this->subdistrictCatalogs(),
                'conditional_rules' => [
                    ['field' => 'district_id', 'required_unless' => ['state_id' => $this->federalTerritoryStateIds()]],
                ],
            ],
            default => throw new \RuntimeException('Unsupported admin write resource.'),
        };
    }

    /**
     * @param  class-string  $resourceClass
     * @return array<string, mixed>
     */
    public function rules(string $resourceClass, bool $updating = false): array
    {
        return match ($resourceClass) {
            EventResource::class => $this->eventRules($updating),
            InstitutionResource::class => $this->institutionRules($updating),
            ReferenceResource::class => $this->referenceRules($updating),
            SpeakerResource::class => $this->speakerRules($updating),
            SubdistrictResource::class => $this->subdistrictRules($updating),
            VenueResource::class => $this->venueRules($updating),
            default => [],
        };
    }

    /**
     * @param  class-string  $resourceClass
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    public function normalizeValidatedPayload(string $resourceClass, array $validated, ?Model $record = null): array
    {
        if (! in_array($resourceClass, [InstitutionResource::class, SpeakerResource::class], true)) {
            return $validated;
        }

        if (! is_array($validated['address'] ?? null)) {
            return $validated;
        }

        $countryProvided = SharedFormSchema::countrySelectionProvided($validated['address']);
        $validated['address'] = SharedFormSchema::prepareAddressPersistenceData($validated['address']);

        if (
            $resourceClass === InstitutionResource::class
            && ! $countryProvided
            && $record instanceof Institution
            && is_int($record->addressModel?->country_id)
        ) {
            $validated['address']['country_id'] = $record->addressModel->country_id;
            $countryProvided = true;
        }

        if (! $countryProvided) {
            throw ValidationException::withMessages([
                'address.country_id' => __('The address country is required.'),
            ]);
        }

        if (! is_int($validated['address']['country_id'] ?? null)) {
            throw ValidationException::withMessages([
                'address.country_id' => __('The selected country is invalid.'),
            ]);
        }

        return $validated;
    }

    /**
     * @param  class-string  $resourceClass
     * @param  array<string, mixed>  $validated
     */
    public function store(string $resourceClass, array $validated, User $actor): Model
    {
        return match ($resourceClass) {
            EventResource::class => $this->saveAdminEventAction->handle($validated, $actor),
            InstitutionResource::class => $this->saveInstitutionAction->handle($validated, $actor),
            ReferenceResource::class => $this->saveReferenceAction->handle($validated),
            SpeakerResource::class => $this->saveSpeakerAction->handle($validated, $actor),
            SubdistrictResource::class => $this->saveSubdistrictAction->handle($validated),
            VenueResource::class => $this->saveVenueAction->handle($validated),
            default => throw new \RuntimeException('Unsupported admin write resource.'),
        };
    }

    /**
     * @param  class-string  $resourceClass
     * @param  array<string, mixed>  $validated
     */
    public function update(string $resourceClass, Model $record, array $validated, User $actor): Model
    {
        return match ($resourceClass) {
            EventResource::class => $record instanceof Event
                ? $this->saveAdminEventAction->handle($validated, $actor, $record)
                : throw new \RuntimeException('Expected event record.'),
            InstitutionResource::class => $record instanceof Institution
                ? $this->saveInstitutionAction->handle($validated, $actor, $record)
                : throw new \RuntimeException('Expected institution record.'),
            ReferenceResource::class => $record instanceof Reference
                ? $this->saveReferenceAction->handle($validated, $record)
                : throw new \RuntimeException('Expected reference record.'),
            SpeakerResource::class => $record instanceof Speaker
                ? $this->saveSpeakerAction->handle($validated, $actor, $record)
                : throw new \RuntimeException('Expected speaker record.'),
            SubdistrictResource::class => $record instanceof Subdistrict
                ? $this->saveSubdistrictAction->handle($validated, $record)
                : throw new \RuntimeException('Expected subdistrict record.'),
            VenueResource::class => $record instanceof Venue
                ? $this->saveVenueAction->handle($validated, $record)
                : throw new \RuntimeException('Expected venue record.'),
            default => throw new \RuntimeException('Unsupported admin write resource.'),
        };
    }

    /**
     * @param  array<string, mixed>  $normalizedPayload
     * @return array{normalized_payload: array<string, mixed>, warnings: list<array<string, string>>, destructive_media_fields: list<string>}
     */
    public function previewNormalizedPayload(array $normalizedPayload): array
    {
        $destructiveMediaFields = $this->destructiveMediaFields($normalizedPayload);

        return [
            'normalized_payload' => $this->previewPayload($normalizedPayload),
            'warnings' => array_values(array_map(
                fn (string $field): array => [
                    'code' => 'destructive_media_clear',
                    'field' => $field,
                    'message' => $this->destructiveMediaWarning($field),
                ],
                $destructiveMediaFields,
            )),
            'destructive_media_fields' => $destructiveMediaFields,
        ];
    }

    /**
     * @param  class-string  $resourceClass
     * @return array<string, mixed>
     */
    private function defaultsForCreate(string $resourceClass): array
    {
        $defaultCountryId = app(PreferredCountryResolver::class)->resolveId();

        return match ($resourceClass) {
            EventResource::class => $this->saveAdminEventAction->defaultsForCreate(),
            InstitutionResource::class => [
                'type' => InstitutionType::Masjid->value,
                'is_active' => true,
                'address' => [
                    'country_id' => $defaultCountryId,
                ],
                'clear_logo' => false,
                'clear_cover' => false,
                'clear_gallery' => false,
            ],
            ReferenceResource::class => [
                'type' => ReferenceType::Book->value,
                'is_canonical' => false,
                'status' => 'verified',
                'is_active' => true,
                'social_media' => [],
                'clear_front_cover' => false,
                'clear_back_cover' => false,
                'clear_gallery' => false,
            ],
            SpeakerResource::class => [
                'gender' => Gender::Male->value,
                'is_freelance' => false,
                'is_active' => true,
                'address' => [
                    'country_id' => $defaultCountryId,
                    'state_id' => null,
                    'district_id' => null,
                    'subdistrict_id' => null,
                ],
                'clear_avatar' => false,
                'clear_cover' => false,
                'clear_gallery' => false,
            ],
            VenueResource::class => [
                'type' => VenueType::Dewan->value,
                'status' => 'verified',
                'is_active' => true,
                'facilities' => [],
                'address' => [
                    'country_id' => 132,
                ],
                'clear_cover' => false,
                'clear_gallery' => false,
            ],
            SubdistrictResource::class => [
                'district_id' => null,
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultsForRecord(Model $record): array
    {
        $defaults = $record instanceof Subdistrict
            ? []
            : $this->contributionEntityMutationService->stateFor($record);

        if ($record instanceof Institution) {
            $defaults['status'] = $record->status;
            $defaults['is_active'] = (bool) $record->is_active;
            $defaults['allow_public_event_submission'] = (bool) $record->allow_public_event_submission;
            $defaults['clear_logo'] = false;
            $defaults['clear_cover'] = false;
            $defaults['clear_gallery'] = false;
        }

        if ($record instanceof Speaker) {
            if (is_array($defaults['address'] ?? null)) {
                unset(
                    $defaults['address']['line1'],
                    $defaults['address']['line2'],
                    $defaults['address']['postcode'],
                    $defaults['address']['lat'],
                    $defaults['address']['lng'],
                    $defaults['address']['google_maps_url'],
                    $defaults['address']['google_place_id'],
                    $defaults['address']['waze_url'],
                );
            }

            unset(
                $defaults['institution_id'],
                $defaults['institution_position'],
            );

            $defaults['status'] = $record->status;
            $defaults['is_active'] = (bool) $record->is_active;
            $defaults['allow_public_event_submission'] = (bool) $record->allow_public_event_submission;
            $defaults['clear_avatar'] = false;
            $defaults['clear_cover'] = false;
            $defaults['clear_gallery'] = false;
        }

        if ($record instanceof Reference) {
            $defaults['is_canonical'] = (bool) $record->is_canonical;
            $defaults['status'] = $record->status;
            $defaults['is_active'] = (bool) $record->is_active;
            $defaults['clear_front_cover'] = false;
            $defaults['clear_back_cover'] = false;
            $defaults['clear_gallery'] = false;
        }

        if ($record instanceof Event) {
            $defaults = $this->saveAdminEventAction->formStateForRecord($record);
        }

        if ($record instanceof Venue) {
            $defaults['status'] = $record->status;
            $defaults['is_active'] = (bool) $record->is_active;
            $defaults['clear_cover'] = false;
            $defaults['clear_gallery'] = false;
        }

        if ($record instanceof Subdistrict) {
            $defaults = [
                'country_id' => (int) $record->country_id,
                'state_id' => (int) $record->state_id,
                'district_id' => $record->district_id !== null ? (int) $record->district_id : null,
                'name' => $record->name,
            ];
        }

        return $defaults;
    }

    private function recordKey(Model $record): string
    {
        return (string) $record->getRouteKey();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private function destructiveMediaFields(array $payload): array
    {
        $fields = [];

        foreach ($payload as $field => $value) {
            if (! is_string($field) || ! str_starts_with($field, 'clear_') || $value !== true) {
                continue;
            }

            $fields[] = $field;
        }

        return array_values(array_unique($fields));
    }

    private function destructiveMediaWarning(string $field): string
    {
        $label = ucwords(str_replace('_', ' ', substr($field, 6)));

        return __('The :field flag will clear the existing :label media collection before saving.', [
            'field' => $field,
            'label' => $label,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function previewPayload(array $payload): array
    {
        $preview = [];

        foreach ($payload as $key => $value) {
            $preview[$key] = $this->previewValue($value);
        }

        return $preview;
    }

    private function previewValue(mixed $value): mixed
    {
        if ($value instanceof UploadedFile) {
            return [
                'file_name' => $value->getClientOriginalName(),
                'mime_type' => $value->getClientMimeType(),
                'size' => $value->getSize(),
            ];
        }

        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->previewValue($item), $value);
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function imageMimeTypes(): array
    {
        return ['image/jpeg', 'image/png', 'image/webp'];
    }

    /**
     * @return list<string>
     */
    private function logoMimeTypes(): array
    {
        return ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
    }

    private function maxUploadSizeKb(): int
    {
        return (int) ceil(((int) config('media-library.max_file_size', 10 * 1024 * 1024)) / 1024);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function institutionFields(bool $updating): array
    {
        $fields = [
            $this->field('name', 'string', required: true, maxLength: 255),
            $this->field('nickname', 'string', required: false, maxLength: 255),
            $this->field('type', 'string', required: true, default: InstitutionType::Masjid->value, allowedValues: $this->enumValues(InstitutionType::class)),
            $this->field('description', 'string', required: false),
            $this->field('status', 'string', required: true, allowedValues: ['unverified', 'pending', 'verified', 'rejected']),
            $this->field('is_active', 'boolean', required: false, default: true),
            $this->field('address', 'object', required: true),
            $this->field('address.country_id', 'integer', required: ! $updating, default: SharedFormSchema::preferredPublicCountryId()),
            $this->field('contacts', 'array<object>', required: false),
            $this->field('social_media', 'array<object>', required: false),
            $this->field('logo', 'file', required: false, acceptedMimeTypes: $this->logoMimeTypes(), maxFileSizeKb: $this->maxUploadSizeKb()),
            $this->field('cover', 'file', required: false, acceptedMimeTypes: $this->imageMimeTypes(), maxFileSizeKb: $this->maxUploadSizeKb()),
            $this->field('gallery', 'array<file>', required: false, acceptedMimeTypes: $this->imageMimeTypes(), maxFileSizeKb: $this->maxUploadSizeKb()),
            $this->field('clear_logo', 'boolean', required: false, default: false),
            $this->field('clear_cover', 'boolean', required: false, default: false),
            $this->field('clear_gallery', 'boolean', required: false, default: false),
        ];

        if ($updating) {
            $fields[] = $this->field('allow_public_event_submission', 'boolean', required: false);
        }

        return $fields;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function speakerFields(bool $updating): array
    {
        $fields = [
            $this->field('name', 'string', required: true, maxLength: 255),
            $this->field('gender', 'string', required: true, default: Gender::Male->value, allowedValues: $this->enumValues(Gender::class)),
            $this->field('is_freelance', 'boolean', required: false, default: false),
            $this->field('job_title', 'string', required: false, maxLength: 255),
            $this->field('honorific', 'array<string>', required: false, allowedValues: $this->enumValues(Honorific::class)),
            $this->field('pre_nominal', 'array<string>', required: false, allowedValues: $this->enumValues(PreNominal::class)),
            $this->field('post_nominal', 'array<string>', required: false, allowedValues: $this->enumValues(PostNominal::class)),
            $this->field('bio', 'rich_text', required: false),
            $this->field('qualifications', 'array<object>', required: false),
            $this->field('language_ids', 'array<int>', required: false),
            $this->field('status', 'string', required: true, allowedValues: ['pending', 'verified', 'rejected']),
            $this->field('is_active', 'boolean', required: false, default: true),
            $this->field('address', 'object', required: true),
            $this->field('address.country_id', 'integer', required: ! $updating, default: SharedFormSchema::preferredPublicCountryId()),
            $this->field('contacts', 'array<object>', required: false),
            $this->field('social_media', 'array<object>', required: false),
            $this->field('avatar', 'file', required: false, acceptedMimeTypes: $this->imageMimeTypes(), maxFileSizeKb: $this->maxUploadSizeKb()),
            $this->field('cover', 'file', required: false, acceptedMimeTypes: $this->imageMimeTypes(), maxFileSizeKb: $this->maxUploadSizeKb()),
            $this->field('gallery', 'array<file>', required: false, acceptedMimeTypes: $this->imageMimeTypes(), maxFileSizeKb: $this->maxUploadSizeKb()),
            $this->field('clear_avatar', 'boolean', required: false, default: false),
            $this->field('clear_cover', 'boolean', required: false, default: false),
            $this->field('clear_gallery', 'boolean', required: false, default: false),
        ];

        if ($updating) {
            $fields[] = $this->field('allow_public_event_submission', 'boolean', required: false);
        }

        return $fields;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function referenceFields(): array
    {
        return [
            $this->field('title', 'string', required: true, maxLength: 255),
            $this->field('author', 'string', required: false, maxLength: 255),
            $this->field('type', 'string', required: true, default: ReferenceType::Book->value, allowedValues: $this->enumValues(ReferenceType::class)),
            $this->field('publication_year', 'string', required: false, maxLength: 255),
            $this->field('publisher', 'string', required: false, maxLength: 255),
            $this->field('description', 'string', required: false),
            $this->field('is_canonical', 'boolean', required: false, default: false),
            $this->field('status', 'string', required: true, default: 'verified', allowedValues: ['pending', 'verified']),
            $this->field('is_active', 'boolean', required: false, default: true),
            $this->field('social_media', 'array<object>', required: false),
            $this->field('front_cover', 'file', required: false, acceptedMimeTypes: $this->imageMimeTypes(), maxFileSizeKb: $this->maxUploadSizeKb()),
            $this->field('back_cover', 'file', required: false, acceptedMimeTypes: $this->imageMimeTypes(), maxFileSizeKb: $this->maxUploadSizeKb()),
            $this->field('gallery', 'array<file>', required: false, acceptedMimeTypes: $this->imageMimeTypes(), maxFileSizeKb: $this->maxUploadSizeKb(), maxFiles: 10),
            $this->field('clear_front_cover', 'boolean', required: false, default: false),
            $this->field('clear_back_cover', 'boolean', required: false, default: false),
            $this->field('clear_gallery', 'boolean', required: false, default: false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function venueFields(): array
    {
        return [
            $this->field('name', 'string', required: true, maxLength: 255),
            $this->field('type', 'string', required: true, default: VenueType::Dewan->value, allowedValues: $this->enumValues(VenueType::class)),
            $this->field('status', 'string', required: true, default: 'verified', allowedValues: ['unverified', 'pending', 'verified', 'rejected']),
            $this->field('is_active', 'boolean', required: false, default: true),
            $this->field('facilities', 'array<string>', required: false, allowedValues: $this->venueFacilityValues()),
            $this->field('address', 'object', required: true),
            $this->field('contacts', 'array<object>', required: false),
            $this->field('social_media', 'array<object>', required: false),
            $this->field('cover', 'file', required: false, acceptedMimeTypes: $this->imageMimeTypes(), maxFileSizeKb: $this->maxUploadSizeKb()),
            $this->field('gallery', 'array<file>', required: false, acceptedMimeTypes: $this->imageMimeTypes(), maxFileSizeKb: $this->maxUploadSizeKb()),
            $this->field('clear_cover', 'boolean', required: false, default: false),
            $this->field('clear_gallery', 'boolean', required: false, default: false),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function subdistrictFields(): array
    {
        return [
            $this->field('country_id', 'integer', required: true),
            $this->field('state_id', 'integer', required: true),
            $this->field('district_id', 'integer', required: false),
            $this->field('name', 'string', required: true, maxLength: 255),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function eventFields(): array
    {
        return [
            $this->field('title', 'string', required: true, maxLength: 255),
            $this->field('description', 'rich_text', required: false),
            $this->field('event_date', 'date', required: true),
            $this->field('prayer_time', 'string', required: true, default: EventPrayerTime::LainWaktu->value, allowedValues: $this->enumValues(EventPrayerTime::class)),
            $this->field('custom_time', 'string', required: false, maxLength: 32),
            $this->field('end_time', 'string', required: false, maxLength: 32),
            $this->field('timezone', 'string', required: true, default: 'Asia/Kuala_Lumpur', maxLength: 64),
            $this->field('event_format', 'string', required: true, default: EventFormat::Physical->value, allowedValues: $this->enumValues(EventFormat::class)),
            $this->field('visibility', 'string', required: true, default: EventVisibility::Public->value, allowedValues: $this->enumValues(EventVisibility::class)),
            $this->field('event_url', 'string', required: false, maxLength: 255),
            $this->field('live_url', 'string', required: false, maxLength: 255),
            $this->field('recording_url', 'string', required: false, maxLength: 255),
            $this->field('gender', 'string', required: true, default: EventGenderRestriction::All->value, allowedValues: $this->enumValues(EventGenderRestriction::class)),
            $this->field('age_group', 'array<string>', required: true, allowedValues: $this->enumValues(EventAgeGroup::class)),
            $this->field('children_allowed', 'boolean', required: false, default: false),
            $this->field('is_muslim_only', 'boolean', required: false, default: false),
            $this->field('languages', 'array<int>', required: false),
            $this->field('event_type', 'array<string>', required: true, allowedValues: $this->enumValues(EventType::class)),
            $this->field('domain_tags', 'array<string>', required: false),
            $this->field('discipline_tags', 'array<string>', required: false),
            $this->field('source_tags', 'array<string>', required: false),
            $this->field('issue_tags', 'array<string>', required: false),
            $this->field('references', 'array<string>', required: false),
            $this->field('organizer_type', 'string', required: false, allowedValues: [Institution::class, Speaker::class]),
            $this->field('organizer_id', 'string', required: false),
            $this->field('series', 'array<string>', required: false),
            $this->field('institution_id', 'string', required: false),
            $this->field('venue_id', 'string', required: false),
            $this->field('space_id', 'string', required: false),
            $this->field('speakers', 'array<string>', required: false),
            $this->field('other_key_people', 'array<object>', required: false),
            $this->field('poster', 'file', required: false, acceptedMimeTypes: $this->imageMimeTypes(), maxFileSizeKb: $this->maxUploadSizeKb()),
            $this->field('gallery', 'array<file>', required: false, acceptedMimeTypes: $this->imageMimeTypes(), maxFileSizeKb: $this->maxUploadSizeKb(), maxFiles: 10),
            $this->field('clear_poster', 'boolean', required: false, default: false),
            $this->field('clear_gallery', 'boolean', required: false, default: false),
            $this->field('is_priority', 'boolean', required: false, default: false),
            $this->field('is_featured', 'boolean', required: false, default: false),
            $this->field('is_active', 'boolean', required: false, default: true),
            $this->field('escalated_at', 'datetime', required: false),
            $this->field('registration_required', 'boolean', required: false, default: false),
            $this->field('registration_mode', 'string', required: false, default: RegistrationMode::Event->value, allowedValues: $this->enumValues(RegistrationMode::class)),
        ];
    }

    /**
     * @param  list<string|int>|null  $allowedValues
     * @param  list<string>|null  $acceptedMimeTypes
     * @return array<string, mixed>
     */
    private function field(
        string $name,
        string $type,
        bool $required = false,
        mixed $default = null,
        ?int $maxLength = null,
        ?array $allowedValues = null,
        ?array $acceptedMimeTypes = null,
        ?int $maxFileSizeKb = null,
        ?int $maxFiles = null,
    ): array {
        return array_filter([
            'name' => $name,
            'type' => $type,
            'required' => $required,
            'default' => $default,
            'max_length' => $maxLength,
            'allowed_values' => $allowedValues,
            'accepted_mime_types' => $acceptedMimeTypes,
            'max_file_size_kb' => $maxFileSizeKb,
            'max_files' => $maxFiles,
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * @param  class-string<BackedEnum>  $enumClass
     * @return list<string|int>
     */
    private function enumValues(string $enumClass): array
    {
        return array_map(
            static fn (BackedEnum $case): string|int => $case->value,
            $enumClass::cases(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function institutionRules(bool $updating): array
    {
        $addressRule = $updating ? ['sometimes', 'array'] : ['required', 'array'];
        $maxUploadSize = 'max:'.$this->maxUploadSizeKb();

        return [
            'name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(InstitutionType::class)],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['unverified', 'pending', 'verified', 'rejected'])],
            'is_active' => ['sometimes', 'boolean'],
            'allow_public_event_submission' => $updating ? ['sometimes', 'boolean'] : ['prohibited'],
            'address' => $addressRule,
            'address.country_id' => $updating ? ['nullable', 'integer', 'exists:countries,id'] : ['required', 'integer', 'exists:countries,id'],
            'address.state_id' => ['nullable', 'integer', 'exists:states,id'],
            'address.district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'address.subdistrict_id' => ['nullable', 'integer', 'exists:subdistricts,id'],
            'address.line1' => ['nullable', 'string', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.postcode' => ['nullable', 'string', 'max:16'],
            'address.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'address.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'address.google_maps_url' => ['nullable', 'url', 'max:2048'],
            'address.google_place_id' => ['nullable', 'string', 'max:255'],
            'address.waze_url' => ['nullable', 'url', 'max:255'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.category' => ['required_with:contacts.*.value', Rule::enum(ContactCategory::class)],
            'contacts.*.value' => ['required_with:contacts.*.category', 'string', 'max:255'],
            'contacts.*.type' => ['nullable', Rule::enum(ContactType::class)],
            'contacts.*.is_public' => ['sometimes', 'boolean'],
            'social_media' => ['nullable', 'array'],
            'social_media.*.platform' => ['required_with:social_media.*.username,social_media.*.url', Rule::enum(SocialMediaPlatform::class)],
            'social_media.*.username' => ['nullable', 'string', 'max:255', 'required_without:social_media.*.url'],
            'social_media.*.url' => ['nullable', 'url', 'max:255', 'required_without:social_media.*.username'],
            'logo' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/svg+xml', $maxUploadSize],
            'cover' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', $maxUploadSize],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp', $maxUploadSize],
            'clear_logo' => ['sometimes', 'boolean'],
            'clear_cover' => ['sometimes', 'boolean'],
            'clear_gallery' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventRules(bool $updating): array
    {
        $required = $updating ? 'sometimes' : 'required';
        $maxUploadSize = 'max:'.$this->maxUploadSizeKb();

        return [
            'title' => [$required, 'string', 'max:255'],
            'description' => ['nullable'],
            'event_date' => [$required, 'date'],
            'prayer_time' => [$required, Rule::enum(EventPrayerTime::class)],
            'custom_time' => ['nullable', 'string', 'max:32', 'required_if:prayer_time,'.EventPrayerTime::LainWaktu->value],
            'end_time' => ['nullable', 'string', 'max:32'],
            'timezone' => [$required, 'string', 'max:64'],
            'event_format' => [$required, Rule::enum(EventFormat::class)],
            'visibility' => [$required, Rule::enum(EventVisibility::class)],
            'event_url' => ['nullable', 'url', 'max:255'],
            'live_url' => ['nullable', 'url', 'max:255'],
            'recording_url' => ['nullable', 'url', 'max:255'],
            'gender' => [$required, Rule::enum(EventGenderRestriction::class)],
            'age_group' => [$required, 'array', 'min:1'],
            'age_group.*' => [Rule::enum(EventAgeGroup::class)],
            'children_allowed' => ['sometimes', 'boolean'],
            'is_muslim_only' => ['sometimes', 'boolean'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['integer', 'exists:languages,id'],
            'event_type' => [$required, 'array', 'min:1'],
            'event_type.*' => [Rule::enum(EventType::class)],
            'domain_tags' => ['nullable', 'array'],
            'domain_tags.*' => ['uuid', 'exists:tags,id'],
            'discipline_tags' => ['nullable', 'array'],
            'discipline_tags.*' => ['uuid', 'exists:tags,id'],
            'source_tags' => ['nullable', 'array'],
            'source_tags.*' => ['uuid', 'exists:tags,id'],
            'issue_tags' => ['nullable', 'array'],
            'issue_tags.*' => ['uuid', 'exists:tags,id'],
            'references' => ['nullable', 'array'],
            'references.*' => ['uuid', 'exists:references,id'],
            'organizer_type' => ['nullable', 'string', Rule::in([Institution::class, Speaker::class, 'institution', 'speaker'])],
            'organizer_id' => ['nullable', 'uuid'],
            'series' => ['nullable', 'array'],
            'series.*' => ['uuid', 'exists:series,id'],
            'institution_id' => ['nullable', 'uuid', 'exists:institutions,id'],
            'venue_id' => ['nullable', 'uuid', 'exists:venues,id'],
            'space_id' => ['nullable', 'uuid', 'exists:spaces,id'],
            'speakers' => ['nullable', 'array'],
            'speakers.*' => ['uuid', 'exists:speakers,id'],
            'other_key_people' => ['nullable', 'array'],
            'other_key_people.*.role' => ['required_with:other_key_people.*.name,other_key_people.*.speaker_id', Rule::enum(EventKeyPersonRole::class)],
            'other_key_people.*.speaker_id' => ['nullable', 'uuid', 'exists:speakers,id', 'required_without:other_key_people.*.name'],
            'other_key_people.*.name' => ['nullable', 'string', 'max:255', 'required_without:other_key_people.*.speaker_id'],
            'other_key_people.*.is_public' => ['sometimes', 'boolean'],
            'other_key_people.*.notes' => ['nullable', 'string', 'max:500'],
            'poster' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', $maxUploadSize],
            'gallery' => ['nullable', 'array', 'max:10'],
            'gallery.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp', $maxUploadSize],
            'clear_poster' => ['sometimes', 'boolean'],
            'clear_gallery' => ['sometimes', 'boolean'],
            'is_priority' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'escalated_at' => ['nullable', 'date'],
            'registration_required' => ['sometimes', 'boolean'],
            'registration_mode' => ['sometimes', Rule::enum(RegistrationMode::class)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function referenceRules(bool $updating): array
    {
        $required = $updating ? 'required' : 'required';
        $maxUploadSize = 'max:'.$this->maxUploadSizeKb();

        return [
            'title' => [$required, 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'type' => [$required, Rule::enum(ReferenceType::class)],
            'publication_year' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_canonical' => ['sometimes', 'boolean'],
            'status' => [$required, Rule::in(['pending', 'verified'])],
            'is_active' => ['sometimes', 'boolean'],
            'social_media' => ['nullable', 'array'],
            'social_media.*.platform' => ['required_with:social_media.*.username,social_media.*.url', Rule::enum(SocialMediaPlatform::class)],
            'social_media.*.username' => ['nullable', 'string', 'max:255', 'required_without:social_media.*.url'],
            'social_media.*.url' => ['nullable', 'url', 'max:255', 'required_without:social_media.*.username'],
            'front_cover' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', $maxUploadSize],
            'back_cover' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', $maxUploadSize],
            'gallery' => ['nullable', 'array', 'max:10'],
            'gallery.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp', $maxUploadSize],
            'clear_front_cover' => ['sometimes', 'boolean'],
            'clear_back_cover' => ['sometimes', 'boolean'],
            'clear_gallery' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function speakerRules(bool $updating): array
    {
        $addressRule = $updating ? ['sometimes', 'array'] : ['present', 'array'];
        $maxUploadSize = 'max:'.$this->maxUploadSizeKb();

        return [
            'name' => ['required', 'string', 'max:255'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'is_freelance' => ['sometimes', 'boolean'],
            'job_title' => ['nullable', 'string', 'max:255', 'required_if:is_freelance,true'],
            'honorific' => ['nullable', 'array'],
            'honorific.*' => [Rule::enum(Honorific::class)],
            'pre_nominal' => ['nullable', 'array'],
            'pre_nominal.*' => [Rule::enum(PreNominal::class)],
            'post_nominal' => ['nullable', 'array'],
            'post_nominal.*' => [Rule::enum(PostNominal::class)],
            'bio' => ['nullable', 'array'],
            'qualifications' => ['nullable', 'array'],
            'qualifications.*.institution' => ['required_with:qualifications.*.degree', 'string', 'max:255'],
            'qualifications.*.degree' => ['required_with:qualifications.*.institution', 'string', 'max:255'],
            'qualifications.*.field' => ['nullable', 'string', 'max:255'],
            'qualifications.*.year' => ['nullable', 'digits:4'],
            'language_ids' => ['nullable', 'array'],
            'language_ids.*' => ['integer', 'exists:languages,id'],
            'status' => ['required', Rule::in(['pending', 'verified', 'rejected'])],
            'is_active' => ['sometimes', 'boolean'],
            'allow_public_event_submission' => $updating ? ['sometimes', 'boolean'] : ['prohibited'],
            'address' => $addressRule,
            'address.country_id' => $updating ? ['nullable', 'integer', 'exists:countries,id'] : ['required', 'integer', 'exists:countries,id'],
            'address.state_id' => ['nullable', 'integer', 'exists:states,id'],
            'address.district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'address.subdistrict_id' => ['nullable', 'integer', 'exists:subdistricts,id'],
            'address.line1' => ['prohibited'],
            'address.line2' => ['prohibited'],
            'address.postcode' => ['prohibited'],
            'address.lat' => ['prohibited'],
            'address.lng' => ['prohibited'],
            'address.google_maps_url' => ['prohibited'],
            'address.google_place_id' => ['prohibited'],
            'address.waze_url' => ['prohibited'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.category' => ['required_with:contacts.*.value', Rule::enum(ContactCategory::class)],
            'contacts.*.value' => ['required_with:contacts.*.category', 'string', 'max:255'],
            'contacts.*.type' => ['nullable', Rule::enum(ContactType::class)],
            'contacts.*.is_public' => ['sometimes', 'boolean'],
            'social_media' => ['nullable', 'array'],
            'social_media.*.platform' => ['required_with:social_media.*.username,social_media.*.url', Rule::enum(SocialMediaPlatform::class)],
            'social_media.*.username' => ['nullable', 'string', 'max:255', 'required_without:social_media.*.url'],
            'social_media.*.url' => ['nullable', 'url', 'max:255', 'required_without:social_media.*.username'],
            'avatar' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', $maxUploadSize],
            'cover' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', $maxUploadSize],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp', $maxUploadSize],
            'clear_avatar' => ['sometimes', 'boolean'],
            'clear_cover' => ['sometimes', 'boolean'],
            'clear_gallery' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function subdistrictRules(bool $updating): array
    {
        $required = $updating ? 'required' : 'required';

        return [
            'country_id' => [$required, 'integer', 'exists:countries,id'],
            'state_id' => [$required, 'integer', 'exists:states,id'],
            'district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'name' => [$required, 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function venueRules(bool $updating): array
    {
        $addressRule = $updating ? ['sometimes', 'array'] : ['required', 'array'];
        $required = $updating ? 'sometimes' : 'required';
        $maxUploadSize = 'max:'.$this->maxUploadSizeKb();

        return [
            'name' => [$required, 'string', 'max:255'],
            'type' => [$required, Rule::enum(VenueType::class)],
            'status' => [$required, Rule::in(['unverified', 'pending', 'verified', 'rejected'])],
            'is_active' => ['sometimes', 'boolean'],
            'facilities' => ['nullable', 'array'],
            'facilities.*' => ['string', Rule::in($this->venueFacilityValues())],
            'address' => $addressRule,
            'address.country_id' => [$updating ? 'sometimes' : 'required', 'integer', 'exists:countries,id'],
            'address.state_id' => ['nullable', 'integer', 'exists:states,id'],
            'address.district_id' => ['nullable', 'integer', 'exists:districts,id'],
            'address.subdistrict_id' => ['nullable', 'integer', 'exists:subdistricts,id'],
            'address.line1' => ['nullable', 'string', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.postcode' => ['nullable', 'string', 'max:16'],
            'address.lat' => ['nullable', 'numeric', 'between:-90,90'],
            'address.lng' => ['nullable', 'numeric', 'between:-180,180'],
            'address.google_maps_url' => ['nullable', 'url', 'max:2048'],
            'address.google_place_id' => ['nullable', 'string', 'max:255'],
            'address.waze_url' => ['nullable', 'url', 'max:255'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.category' => ['required_with:contacts.*.value', Rule::enum(ContactCategory::class)],
            'contacts.*.value' => ['required_with:contacts.*.category', 'string', 'max:255'],
            'contacts.*.type' => ['nullable', Rule::enum(ContactType::class)],
            'contacts.*.is_public' => ['sometimes', 'boolean'],
            'social_media' => ['nullable', 'array'],
            'social_media.*.platform' => ['required_with:social_media.*.username,social_media.*.url', Rule::enum(SocialMediaPlatform::class)],
            'social_media.*.username' => ['nullable', 'string', 'max:255', 'required_without:social_media.*.url'],
            'social_media.*.url' => ['nullable', 'url', 'max:255', 'required_without:social_media.*.username'],
            'cover' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', $maxUploadSize],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp', $maxUploadSize],
            'clear_cover' => ['sometimes', 'boolean'],
            'clear_gallery' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  Model&HasMedia  $record
     * @param  list<string>  $collections
     * @return array<string, mixed>
     */
    private function mediaState(Model $record, array $collections): array
    {
        $state = [];

        foreach ($collections as $collection) {
            $media = $record->getMedia($collection);

            $state[$collection] = $media
                ->map(fn ($item): array => [
                    'id' => (int) $item->getKey(),
                    'name' => $item->name,
                    'file_name' => $item->file_name,
                ])
                ->values()
                ->all();
        }

        return $state;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function addressCatalogs(string $prefix): array
    {
        return [
            $this->catalog($prefix.'.country_id', route('api.admin.catalogs.countries', [], false)),
            $this->catalog(
                $prefix.'.state_id',
                route('api.admin.catalogs.states', [], false),
                ['country_id' => '{'.$prefix.'.country_id}'],
            ),
            $this->catalog(
                $prefix.'.district_id',
                route('api.admin.catalogs.districts', [], false),
                ['state_id' => '{'.$prefix.'.state_id}'],
            ),
            $this->catalog(
                $prefix.'.subdistrict_id',
                route('api.admin.catalogs.subdistricts', [], false),
                [
                    'state_id' => '{'.$prefix.'.state_id}',
                    'district_id' => '{'.$prefix.'.district_id}',
                ],
            ),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function subdistrictCatalogs(): array
    {
        return [
            $this->catalog('country_id', route('api.admin.catalogs.countries', [], false)),
            $this->catalog(
                'state_id',
                route('api.admin.catalogs.states', [], false),
                ['country_id' => '{country_id}'],
            ),
            $this->catalog(
                'district_id',
                route('api.admin.catalogs.districts', [], false),
                ['state_id' => '{state_id}'],
            ),
        ];
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function catalog(string $field, string $endpoint, array $query = []): array
    {
        return array_filter([
            'field' => $field,
            'endpoint' => $endpoint,
            'query' => $query,
        ], static fn (mixed $value): bool => $value !== []);
    }

    /**
     * @return list<int>
     */
    private function federalTerritoryStateIds(): array
    {
        return array_keys(FederalTerritoryLocation::stateIds());
    }

    /**
     * @return list<string>
     */
    private function venueFacilityValues(): array
    {
        return [
            'parking',
            'oku',
            'women_section',
            'ablution_area',
        ];
    }
}
