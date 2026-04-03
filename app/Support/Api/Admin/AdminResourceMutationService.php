<?php

namespace App\Support\Api\Admin;

use App\Actions\Institutions\SaveInstitutionAction;
use App\Actions\Speakers\SaveSpeakerAction;
use App\Enums\ContactCategory;
use App\Enums\ContactType;
use App\Enums\Gender;
use App\Enums\Honorific;
use App\Enums\InstitutionType;
use App\Enums\PostNominal;
use App\Enums\PreNominal;
use App\Enums\SocialMediaPlatform;
use App\Filament\Resources\Institutions\InstitutionResource;
use App\Filament\Resources\Speakers\SpeakerResource;
use App\Models\Institution;
use App\Models\Speaker;
use App\Models\User;
use App\Services\ContributionEntityMutationService;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;

class AdminResourceMutationService
{
    public function __construct(
        private readonly ContributionEntityMutationService $contributionEntityMutationService,
        private readonly SaveInstitutionAction $saveInstitutionAction,
        private readonly SaveSpeakerAction $saveSpeakerAction,
    ) {}

    /**
     * @param  class-string  $resourceClass
     */
    public function supports(string $resourceClass): bool
    {
        return in_array($resourceClass, [
            InstitutionResource::class,
            SpeakerResource::class,
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
            InstitutionResource::class => [
                'resource_key' => $resourceKey,
                'operation' => $operation,
                'method' => $updating ? 'PUT' : 'POST',
                'endpoint' => $updating && $record instanceof Model
                    ? route('api.admin.resources.update', ['resourceKey' => $resourceKey, 'recordKey' => $record->getKey()], false)
                    : route('api.admin.resources.store', ['resourceKey' => $resourceKey], false),
                'content_type' => 'multipart/form-data',
                'slug_behavior' => 'auto_managed',
                'defaults' => $defaults,
                'current_media' => $record instanceof Institution ? $this->mediaState($record, ['logo', 'cover', 'gallery']) : null,
                'fields' => $this->institutionFields($updating),
                'conditional_rules' => [],
            ],
            SpeakerResource::class => [
                'resource_key' => $resourceKey,
                'operation' => $operation,
                'method' => $updating ? 'PUT' : 'POST',
                'endpoint' => $updating && $record instanceof Model
                    ? route('api.admin.resources.update', ['resourceKey' => $resourceKey, 'recordKey' => $record->getKey()], false)
                    : route('api.admin.resources.store', ['resourceKey' => $resourceKey], false),
                'content_type' => 'multipart/form-data',
                'slug_behavior' => 'auto_managed',
                'defaults' => $defaults,
                'current_media' => $record instanceof Speaker ? $this->mediaState($record, ['avatar', 'cover', 'gallery']) : null,
                'fields' => $this->speakerFields($updating),
                'conditional_rules' => [
                    ['field' => 'job_title', 'required_when' => ['is_freelance' => [true]]],
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
            InstitutionResource::class => $this->institutionRules($updating),
            SpeakerResource::class => $this->speakerRules($updating),
            default => [],
        };
    }

    /**
     * @param  class-string  $resourceClass
     * @param  array<string, mixed>  $validated
     */
    public function store(string $resourceClass, array $validated, User $actor): Model
    {
        return match ($resourceClass) {
            InstitutionResource::class => $this->saveInstitutionAction->handle($validated, $actor),
            SpeakerResource::class => $this->saveSpeakerAction->handle($validated, $actor),
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
            InstitutionResource::class => $record instanceof Institution
                ? $this->saveInstitutionAction->handle($validated, $actor, $record)
                : throw new \RuntimeException('Expected institution record.'),
            SpeakerResource::class => $record instanceof Speaker
                ? $this->saveSpeakerAction->handle($validated, $actor, $record)
                : throw new \RuntimeException('Expected speaker record.'),
            default => throw new \RuntimeException('Unsupported admin write resource.'),
        };
    }

    /**
     * @param  class-string  $resourceClass
     * @return array<string, mixed>
     */
    private function defaultsForCreate(string $resourceClass): array
    {
        return match ($resourceClass) {
            InstitutionResource::class => [
                'type' => InstitutionType::Masjid->value,
                'is_active' => true,
                'address' => [
                    'country_id' => 132,
                ],
                'clear_logo' => false,
                'clear_cover' => false,
                'clear_gallery' => false,
            ],
            SpeakerResource::class => [
                'gender' => Gender::Male->value,
                'is_freelance' => false,
                'is_active' => true,
                'address' => [
                    'country_id' => 132,
                ],
                'clear_avatar' => false,
                'clear_cover' => false,
                'clear_gallery' => false,
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultsForRecord(Model $record): array
    {
        $defaults = $this->contributionEntityMutationService->stateFor($record);

        if ($record instanceof Institution) {
            $defaults['status'] = $record->status;
            $defaults['is_active'] = (bool) $record->is_active;
            $defaults['allow_public_event_submission'] = (bool) $record->allow_public_event_submission;
            $defaults['clear_logo'] = false;
            $defaults['clear_cover'] = false;
            $defaults['clear_gallery'] = false;
        }

        if ($record instanceof Speaker) {
            $defaults['status'] = $record->status;
            $defaults['is_active'] = (bool) $record->is_active;
            $defaults['allow_public_event_submission'] = (bool) $record->allow_public_event_submission;
            $defaults['clear_avatar'] = false;
            $defaults['clear_cover'] = false;
            $defaults['clear_gallery'] = false;
        }

        return $defaults;
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
            $this->field('contacts', 'array<object>', required: false),
            $this->field('social_media', 'array<object>', required: false),
            $this->field('logo', 'file', required: false),
            $this->field('cover', 'file', required: false),
            $this->field('gallery', 'array<file>', required: false),
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
            $this->field('contacts', 'array<object>', required: false),
            $this->field('social_media', 'array<object>', required: false),
            $this->field('avatar', 'file', required: false),
            $this->field('cover', 'file', required: false),
            $this->field('gallery', 'array<file>', required: false),
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
     * @return array<string, mixed>
     * @param  list<string|int>|null  $allowedValues
     */
    private function field(
        string $name,
        string $type,
        bool $required = false,
        mixed $default = null,
        ?int $maxLength = null,
        ?array $allowedValues = null,
    ): array {
        return array_filter([
            'name' => $name,
            'type' => $type,
            'required' => $required,
            'default' => $default,
            'max_length' => $maxLength,
            'allowed_values' => $allowedValues,
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
     * @param  bool  $updating
     * @return array<string, mixed>
     */
    private function institutionRules(bool $updating): array
    {
        $addressRule = $updating ? ['sometimes', 'array'] : ['required', 'array'];

        return [
            'name' => ['required', 'string', 'max:255'],
            'nickname' => ['nullable', 'string', 'max:255'],
            'type' => ['required', Rule::enum(InstitutionType::class)],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(['unverified', 'pending', 'verified', 'rejected'])],
            'is_active' => ['sometimes', 'boolean'],
            'allow_public_event_submission' => $updating ? ['sometimes', 'boolean'] : ['prohibited'],
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
            'logo' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp,image/svg+xml'],
            'cover' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp'],
            'clear_logo' => ['sometimes', 'boolean'],
            'clear_cover' => ['sometimes', 'boolean'],
            'clear_gallery' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  bool  $updating
     * @return array<string, mixed>
     */
    private function speakerRules(bool $updating): array
    {
        $addressRule = $updating ? ['sometimes', 'array'] : ['required', 'array'];

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
            'avatar' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp'],
            'cover' => ['nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp'],
            'gallery' => ['nullable', 'array'],
            'gallery.*' => ['file', 'mimetypes:image/jpeg,image/png,image/webp'],
            'clear_avatar' => ['sometimes', 'boolean'],
            'clear_cover' => ['sometimes', 'boolean'],
            'clear_gallery' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @param  Model&\Spatie\MediaLibrary\HasMedia  $record
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
                    'url' => $item->getUrl(),
                ])
                ->values()
                ->all();
        }

        return $state;
    }
}
