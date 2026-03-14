<?php

namespace App\Services;

use App\Actions\Membership\AddMemberToSubject;
use App\Enums\ContactCategory;
use App\Enums\ContactType;
use App\Enums\EventKeyPersonRole;
use App\Enums\TagType;
use App\Models\Address;
use App\Models\Contact;
use App\Models\Event;
use App\Models\Institution;
use App\Models\Reference;
use App\Models\SocialMedia;
use App\Models\Speaker;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;

class ContributionEntityMutationService
{
    public function __construct(
        private readonly EventKeyPersonSyncService $eventKeyPersonSyncService,
        private readonly AddMemberToSubject $addMemberToSubject,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function stateFor(Model $entity): array
    {
        return match (true) {
            $entity instanceof Institution => $this->institutionState($entity),
            $entity instanceof Speaker => $this->speakerState($entity),
            $entity instanceof Reference => $this->referenceState($entity),
            $entity instanceof Event => $this->eventState($entity),
            default => throw new RuntimeException('Unsupported contribution entity type.'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createInstitution(array $payload, User $proposer): Institution
    {
        $institution = Institution::create([
            'name' => (string) ($payload['name'] ?? 'Institution'),
            'slug' => Str::slug((string) ($payload['name'] ?? 'institution')).'-'.Str::lower(Str::random(7)),
            'type' => (string) ($payload['type'] ?? 'masjid'),
            'description' => $payload['description'] ?? null,
            'status' => 'pending',
            'is_active' => true,
            'allow_public_event_submission' => true,
        ]);

        $this->addMemberToSubject->handle($institution, $proposer);

        $this->syncInstitutionRelations($institution, $payload);

        return $institution;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createSpeaker(array $payload, User $proposer): Speaker
    {
        $speaker = Speaker::create([
            'name' => (string) ($payload['name'] ?? 'Speaker'),
            'gender' => (string) ($payload['gender'] ?? 'male'),
            'honorific' => $this->normalizeStringArray($payload['honorific'] ?? []),
            'pre_nominal' => $this->normalizeStringArray($payload['pre_nominal'] ?? []),
            'post_nominal' => $this->normalizeStringArray($payload['post_nominal'] ?? []),
            'bio' => $payload['bio'] ?? null,
            'qualifications' => $this->normalizeQualificationEntries($payload['qualifications'] ?? []),
            'is_freelance' => (bool) ($payload['is_freelance'] ?? false),
            'job_title' => $payload['job_title'] ?? null,
            'slug' => Str::slug((string) ($payload['name'] ?? 'speaker')).'-'.Str::lower(Str::random(7)),
            'status' => 'pending',
            'is_active' => true,
            'allow_public_event_submission' => true,
        ]);

        $this->addMemberToSubject->handle($speaker, $proposer);

        $this->syncSpeakerRelations($speaker, $payload);

        return $speaker;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function apply(Model $entity, array $payload): array
    {
        return match (true) {
            $entity instanceof Institution => $this->applyInstitution($entity, $payload),
            $entity instanceof Speaker => $this->applySpeaker($entity, $payload),
            $entity instanceof Reference => $this->applyReference($entity, $payload),
            $entity instanceof Event => $this->applyEvent($entity, $payload),
            default => throw new RuntimeException('Unsupported contribution entity type.'),
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyInstitution(Institution $institution, array $payload): array
    {
        $institution->fill([
            'name' => $payload['name'] ?? $institution->name,
            'type' => $payload['type'] ?? ($institution->type instanceof \BackedEnum ? $institution->type->value : $institution->type),
            'description' => $payload['description'] ?? $institution->description,
        ]);

        $dirty = $institution->getDirty();
        $institution->save();

        $this->syncInstitutionRelations($institution, $payload);

        return $dirty;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applySpeaker(Speaker $speaker, array $payload): array
    {
        $speaker->fill([
            'name' => $payload['name'] ?? $speaker->name,
            'gender' => $payload['gender'] ?? $speaker->gender,
            'honorific' => array_key_exists('honorific', $payload) ? $this->normalizeStringArray($payload['honorific']) : $speaker->honorific,
            'pre_nominal' => array_key_exists('pre_nominal', $payload) ? $this->normalizeStringArray($payload['pre_nominal']) : $speaker->pre_nominal,
            'post_nominal' => array_key_exists('post_nominal', $payload) ? $this->normalizeStringArray($payload['post_nominal']) : $speaker->post_nominal,
            'bio' => array_key_exists('bio', $payload) ? $payload['bio'] : $speaker->bio,
            'qualifications' => array_key_exists('qualifications', $payload) ? $this->normalizeQualificationEntries($payload['qualifications']) : $speaker->qualifications,
            'is_freelance' => array_key_exists('is_freelance', $payload) ? (bool) $payload['is_freelance'] : $speaker->is_freelance,
            'job_title' => array_key_exists('job_title', $payload) ? $payload['job_title'] : $speaker->job_title,
        ]);

        $dirty = $speaker->getDirty();
        $speaker->save();

        $this->syncSpeakerRelations($speaker, $payload);

        return $dirty;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyReference(Reference $reference, array $payload): array
    {
        $reference->fill([
            'title' => $payload['title'] ?? $reference->title,
            'author' => $payload['author'] ?? $reference->author,
            'type' => $payload['type'] ?? $reference->type,
            'publication_year' => $payload['publication_year'] ?? $reference->publication_year,
            'publisher' => $payload['publisher'] ?? $reference->publisher,
            'description' => array_key_exists('description', $payload) ? $payload['description'] : $reference->description,
        ]);

        $dirty = $reference->getDirty();
        $reference->save();

        if (array_key_exists('social_media', $payload)) {
            $this->syncSocialMedia($reference, $payload['social_media']);
        }

        return $dirty;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function applyEvent(Event $event, array $payload): array
    {
        $event->fill([
            'title' => $payload['title'] ?? $event->title,
            'description' => array_key_exists('description', $payload) ? $payload['description'] : $event->description,
            'starts_at' => $payload['starts_at'] ?? $event->starts_at,
            'ends_at' => $payload['ends_at'] ?? $event->ends_at,
            'timezone' => $payload['timezone'] ?? $event->timezone,
            'event_type' => array_key_exists('event_type', $payload) ? $this->normalizeStringArray($payload['event_type']) : $event->event_type,
            'gender' => $payload['gender'] ?? $event->gender,
            'age_group' => array_key_exists('age_group', $payload) ? $this->normalizeStringArray($payload['age_group']) : $event->age_group,
            'children_allowed' => array_key_exists('children_allowed', $payload) ? (bool) $payload['children_allowed'] : $event->children_allowed,
            'is_muslim_only' => array_key_exists('is_muslim_only', $payload) ? (bool) $payload['is_muslim_only'] : $event->is_muslim_only,
            'event_format' => $payload['event_format'] ?? $event->event_format,
            'visibility' => $payload['visibility'] ?? $event->visibility,
            'event_url' => $payload['event_url'] ?? $event->event_url,
            'live_url' => $payload['live_url'] ?? $event->live_url,
            'recording_url' => $payload['recording_url'] ?? $event->recording_url,
            'organizer_type' => $payload['organizer_type'] ?? $event->organizer_type,
            'organizer_id' => $payload['organizer_id'] ?? $event->organizer_id,
            'institution_id' => $payload['institution_id'] ?? $event->institution_id,
            'venue_id' => $payload['venue_id'] ?? $event->venue_id,
            'space_id' => $payload['space_id'] ?? $event->space_id,
        ]);

        $dirty = $event->getDirty();
        $event->save();

        if (array_key_exists('language_ids', $payload)) {
            $event->languages()->sync($this->normalizeIntegerArray($payload['language_ids']));
        }

        if (array_key_exists('reference_ids', $payload)) {
            $event->references()->sync($this->normalizeStringArray($payload['reference_ids']));
        }

        if (array_key_exists('series_ids', $payload)) {
            $event->series()->sync($this->normalizeStringArray($payload['series_ids']));
        }

        if (
            array_key_exists('speaker_ids', $payload)
            || array_key_exists('other_key_people', $payload)
        ) {
            $this->eventKeyPersonSyncService->sync(
                $event,
                $this->normalizeStringArray($payload['speaker_ids'] ?? []),
                $this->normalizeKeyPeople($payload['other_key_people'] ?? []),
            );
        }

        if (
            array_key_exists('domain_tags', $payload)
            || array_key_exists('discipline_tags', $payload)
            || array_key_exists('source_tags', $payload)
            || array_key_exists('issue_tags', $payload)
        ) {
            $event->syncTags($this->resolveEventTags($payload));
        }

        return $dirty;
    }

    /**
     * @return array<string, mixed>
     */
    private function institutionState(Institution $institution): array
    {
        $institution->loadMissing(['address', 'contacts', 'socialMedia']);

        return [
            'name' => $institution->name,
            'type' => $institution->type instanceof \BackedEnum ? $institution->type->value : (string) $institution->type,
            'description' => $institution->description,
            'address' => $this->addressState($institution->addressModel),
            'contacts' => $this->contactsState($institution->contacts),
            'social_media' => $this->socialMediaState($institution->socialMedia),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function speakerState(Speaker $speaker): array
    {
        $speaker->loadMissing(['address', 'contacts', 'socialMedia', 'languages']);

        return [
            'name' => $speaker->name,
            'gender' => (string) $speaker->gender,
            'is_freelance' => (bool) $speaker->is_freelance,
            'job_title' => $speaker->job_title,
            'honorific' => $this->normalizeStringArray($speaker->honorific ?? []),
            'pre_nominal' => $this->normalizeStringArray($speaker->pre_nominal ?? []),
            'post_nominal' => $this->normalizeStringArray($speaker->post_nominal ?? []),
            'bio' => $speaker->bio,
            'qualifications' => $this->normalizeQualificationEntries($speaker->qualifications ?? []),
            'language_ids' => $speaker->languages->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
            'address' => $this->addressState($speaker->addressModel),
            'contacts' => $this->contactsState($speaker->contacts),
            'social_media' => $this->socialMediaState($speaker->socialMedia),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function referenceState(Reference $reference): array
    {
        $reference->loadMissing(['socialMedia']);

        return [
            'title' => $reference->title,
            'author' => $reference->author,
            'type' => $reference->type,
            'publication_year' => $reference->publication_year,
            'publisher' => $reference->publisher,
            'description' => $reference->description,
            'social_media' => $this->socialMediaState($reference->socialMedia),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function eventState(Event $event): array
    {
        $event->loadMissing(['references', 'series', 'tags', 'keyPeople', 'languages']);

        $tags = $event->tags->groupBy('type');

        return [
            'title' => $event->title,
            'description' => $event->description,
            'starts_at' => $event->starts_at?->toDateTimeString(),
            'ends_at' => $event->ends_at?->toDateTimeString(),
            'timezone' => $event->timezone,
            'event_type' => $this->enumCollectionValues($event->event_type),
            'gender' => $event->gender instanceof \BackedEnum ? $event->gender->value : (string) $event->gender,
            'age_group' => $this->enumCollectionValues($event->age_group),
            'children_allowed' => (bool) $event->children_allowed,
            'is_muslim_only' => (bool) $event->is_muslim_only,
            'event_format' => $event->event_format instanceof \BackedEnum ? $event->event_format->value : (string) $event->event_format,
            'visibility' => $event->visibility instanceof \BackedEnum ? $event->visibility->value : (string) $event->visibility,
            'event_url' => $event->event_url,
            'live_url' => $event->live_url,
            'recording_url' => $event->recording_url,
            'organizer_type' => $event->organizer_type,
            'organizer_id' => $event->organizer_id,
            'institution_id' => $event->institution_id,
            'venue_id' => $event->venue_id,
            'space_id' => $event->space_id,
            'language_ids' => $event->languages->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all(),
            'domain_tags' => $tags->get(TagType::Domain->value, collect())->pluck('id')->values()->all(),
            'discipline_tags' => $tags->get(TagType::Discipline->value, collect())->pluck('id')->values()->all(),
            'source_tags' => $tags->get(TagType::Source->value, collect())->pluck('id')->values()->all(),
            'issue_tags' => $tags->get(TagType::Issue->value, collect())->pluck('id')->values()->all(),
            'reference_ids' => $event->references->pluck('id')->values()->all(),
            'series_ids' => $event->series->pluck('id')->values()->all(),
            'speaker_ids' => $event->keyPeople
                ->where('role', EventKeyPersonRole::Speaker)
                ->pluck('speaker_id')
                ->filter(fn (mixed $speakerId): bool => is_string($speakerId) && $speakerId !== '')
                ->values()
                ->all(),
            'other_key_people' => $event->keyPeople
                ->reject(fn ($keyPerson): bool => $keyPerson->role === EventKeyPersonRole::Speaker)
                ->map(fn ($keyPerson): array => [
                    'role' => $keyPerson->role instanceof \BackedEnum ? $keyPerson->role->value : (string) $keyPerson->role,
                    'speaker_id' => $keyPerson->speaker_id,
                    'name' => $keyPerson->name,
                    'is_public' => (bool) $keyPerson->is_public,
                    'notes' => $keyPerson->notes,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncInstitutionRelations(Institution $institution, array $payload): void
    {
        if (array_key_exists('address', $payload)) {
            $this->syncAddress($institution, $payload['address']);
        }

        if (array_key_exists('contacts', $payload)) {
            $this->syncContacts($institution, $payload['contacts']);
        }

        if (array_key_exists('social_media', $payload)) {
            $this->syncSocialMedia($institution, $payload['social_media']);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncSpeakerRelations(Speaker $speaker, array $payload): void
    {
        if (array_key_exists('address', $payload)) {
            $this->syncAddress($speaker, $payload['address']);
        }

        if (array_key_exists('contacts', $payload)) {
            $this->syncContacts($speaker, $payload['contacts']);
        }

        if (array_key_exists('social_media', $payload)) {
            $this->syncSocialMedia($speaker, $payload['social_media']);
        }

        if (array_key_exists('language_ids', $payload)) {
            $speaker->languages()->sync($this->normalizeIntegerArray($payload['language_ids']));
        }
    }

    private function syncAddress(Model $model, mixed $addressPayload): void
    {
        if (! method_exists($model, 'address')) {
            return;
        }

        $payload = is_array($addressPayload) ? $addressPayload : [];
        $hasContent = collect([
            $payload['line1'] ?? null,
            $payload['line2'] ?? null,
            $payload['postcode'] ?? null,
            $payload['state_id'] ?? null,
            $payload['district_id'] ?? null,
            $payload['subdistrict_id'] ?? null,
            $payload['google_maps_url'] ?? null,
            $payload['waze_url'] ?? null,
        ])->contains(fn (mixed $value): bool => filled($value));

        /** @var Address|null $existingAddress */
        $existingAddress = $model->address()->first();

        if (! $hasContent) {
            $existingAddress?->delete();

            return;
        }

        $attributes = [
            'type' => 'main',
            'country_id' => isset($payload['country_id']) ? (int) $payload['country_id'] : 132,
            'state_id' => isset($payload['state_id']) && $payload['state_id'] !== '' ? (int) $payload['state_id'] : null,
            'district_id' => isset($payload['district_id']) && $payload['district_id'] !== '' ? (int) $payload['district_id'] : null,
            'subdistrict_id' => isset($payload['subdistrict_id']) && $payload['subdistrict_id'] !== '' ? (int) $payload['subdistrict_id'] : null,
            'line1' => $payload['line1'] ?? null,
            'line2' => $payload['line2'] ?? null,
            'postcode' => $payload['postcode'] ?? null,
            'google_maps_url' => $payload['google_maps_url'] ?? null,
            'waze_url' => $payload['waze_url'] ?? null,
        ];

        if ($existingAddress instanceof Address) {
            $existingAddress->fill($attributes)->save();

            return;
        }

        $model->address()->create($attributes);
    }

    private function syncContacts(Model $model, mixed $contactPayload): void
    {
        if (! method_exists($model, 'contacts')) {
            return;
        }

        $contacts = collect(is_array($contactPayload) ? $contactPayload : [])
            ->map(function (mixed $contact): ?array {
                if (! is_array($contact)) {
                    return null;
                }

                $category = ContactCategory::tryFrom((string) ($contact['category'] ?? ''));
                $value = is_string($contact['value'] ?? null) ? trim((string) $contact['value']) : null;
                $type = ContactType::tryFrom((string) ($contact['type'] ?? '')) ?? ContactType::Main;

                if ($category === null || $value === null || $value === '') {
                    return null;
                }

                return [
                    'category' => $category->value,
                    'value' => $value,
                    'type' => $type->value,
                    'is_public' => (bool) ($contact['is_public'] ?? true),
                ];
            })
            ->filter()
            ->values();

        $model->contacts()->delete();

        $contacts->each(fn (array $contact): Contact => $model->contacts()->create($contact));
    }

    private function syncSocialMedia(Model $model, mixed $socialMediaPayload): void
    {
        if (! method_exists($model, 'socialMedia')) {
            return;
        }

        $entries = collect(is_array($socialMediaPayload) ? $socialMediaPayload : [])
            ->map(function (mixed $entry): ?array {
                if (! is_array($entry)) {
                    return null;
                }

                $platform = is_string($entry['platform'] ?? null) ? trim((string) $entry['platform']) : null;
                $username = is_string($entry['username'] ?? null) ? trim((string) $entry['username']) : null;
                $url = is_string($entry['url'] ?? null) ? trim((string) $entry['url']) : null;

                if ($platform === null || $platform === '' || ($username === null || $username === '') && ($url === null || $url === '')) {
                    return null;
                }

                return [
                    'platform' => $platform,
                    'username' => $username !== '' ? $username : null,
                    'url' => $url !== '' ? $url : null,
                ];
            })
            ->filter()
            ->values();

        $model->socialMedia()->delete();

        $entries->each(fn (array $entry): SocialMedia => $model->socialMedia()->create($entry));
    }

    /**
     * @param  iterable<int, mixed>  $values
     * @return list<string>
     */
    private function normalizeStringArray(iterable $values): array
    {
        return collect($values)
            ->map(fn (mixed $value): ?string => is_string($value) && trim($value) !== '' ? trim($value) : null)
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  iterable<int, mixed>  $values
     * @return list<int>
     */
    private function normalizeIntegerArray(iterable $values): array
    {
        return collect($values)
            ->map(fn (mixed $value): ?int => is_numeric($value) ? (int) $value : null)
            ->filter(static fn (?int $value): bool => $value !== null)
            ->values()
            ->all();
    }

    /**
     * @param  iterable<int, mixed>  $entries
     * @return list<array<string, mixed>>
     */
    private function normalizeQualificationEntries(iterable $entries): array
    {
        return collect($entries)
            ->map(function (mixed $entry): ?array {
                if (! is_array($entry)) {
                    return null;
                }

                $institution = is_string($entry['institution'] ?? null) ? trim((string) $entry['institution']) : null;
                $degree = is_string($entry['degree'] ?? null) ? trim((string) $entry['degree']) : null;
                $field = is_string($entry['field'] ?? null) ? trim((string) $entry['field']) : null;
                $year = is_scalar($entry['year'] ?? null) ? trim((string) $entry['year']) : null;

                if (($institution === null || $institution === '') && ($degree === null || $degree === '')) {
                    return null;
                }

                return [
                    'institution' => $institution,
                    'degree' => $degree,
                    'field' => $field !== '' ? $field : null,
                    'year' => $year !== '' ? $year : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function enumCollectionValues(mixed $collection): array
    {
        if ($collection instanceof Collection) {
            return $collection
                ->map(fn (mixed $value): ?string => $value instanceof \BackedEnum ? $value->value : (is_string($value) ? $value : null))
                ->filter()
                ->values()
                ->all();
        }

        if (is_array($collection)) {
            return $this->normalizeStringArray($collection);
        }

        return [];
    }

    /**
     * @param  iterable<int, mixed>  $entries
     * @return list<array{role: string, speaker_id: ?string, name: ?string, is_public: bool, notes: ?string}>
     */
    private function normalizeKeyPeople(iterable $entries): array
    {
        return collect($entries)
            ->map(function (mixed $entry): ?array {
                if (! is_array($entry)) {
                    return null;
                }

                $role = is_string($entry['role'] ?? null) ? trim((string) $entry['role']) : null;
                $speakerId = is_string($entry['speaker_id'] ?? null) && $entry['speaker_id'] !== '' ? (string) $entry['speaker_id'] : null;
                $name = is_string($entry['name'] ?? null) ? trim((string) $entry['name']) : null;
                $notes = is_string($entry['notes'] ?? null) ? trim((string) $entry['notes']) : null;

                if ($role === null || $role === '' || ($speakerId === null && ($name === null || $name === ''))) {
                    return null;
                }

                return [
                    'role' => $role,
                    'speaker_id' => $speakerId,
                    'name' => $name !== '' ? $name : null,
                    'is_public' => (bool) ($entry['is_public'] ?? true),
                    'notes' => $notes !== '' ? $notes : null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<Tag>
     */
    private function resolveEventTags(array $payload): array
    {
        $tagFieldMap = [
            'domain_tags' => TagType::Domain,
            'discipline_tags' => TagType::Discipline,
            'source_tags' => TagType::Source,
            'issue_tags' => TagType::Issue,
        ];

        $tagIds = [];

        foreach ($tagFieldMap as $field => $type) {
            foreach ((array) ($payload[$field] ?? []) as $value) {
                if (is_string($value) && Str::isUuid($value)) {
                    $tagIds[] = $value;

                    continue;
                }

                if (! is_string($value) || trim($value) === '' || ! in_array($type, [TagType::Discipline, TagType::Issue], true)) {
                    continue;
                }

                $normalizedValue = trim($value);
                $tag = Tag::query()
                    ->where('type', $type->value)
                    ->whereRaw("LOWER(name->>'ms') = ?", [mb_strtolower($normalizedValue)])
                    ->first();

                if (! $tag instanceof Tag) {
                    $tag = Tag::create([
                        'name' => ['ms' => $normalizedValue, 'en' => $normalizedValue],
                        'type' => $type->value,
                        'status' => 'pending',
                    ]);
                }

                $tagIds[] = (string) $tag->getKey();
            }
        }

        return Tag::query()->whereIn('id', array_values(array_unique($tagIds)))->get()->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function addressState(?Address $address): array
    {
        if ($address === null) {
            return [
                'country_id' => 132,
                'state_id' => null,
                'district_id' => null,
                'subdistrict_id' => null,
                'line1' => null,
                'line2' => null,
                'postcode' => null,
                'google_maps_url' => null,
                'waze_url' => null,
            ];
        }

        return [
            'country_id' => $address->country_id ?? 132,
            'state_id' => $address->state_id,
            'district_id' => $address->district_id,
            'subdistrict_id' => $address->subdistrict_id,
            'line1' => $address->line1,
            'line2' => $address->line2,
            'postcode' => $address->postcode,
            'google_maps_url' => $address->google_maps_url,
            'waze_url' => $address->waze_url,
        ];
    }

    /**
     * @param  Collection<int, Contact>  $contacts
     * @return list<array<string, mixed>>
     */
    private function contactsState(Collection $contacts): array
    {
        return $contacts
            ->sortBy('created_at')
            ->map(fn (Contact $contact): array => [
                'category' => $contact->category instanceof \BackedEnum ? $contact->category->value : (string) $contact->category,
                'value' => $contact->value,
                'type' => $contact->type instanceof \BackedEnum ? $contact->type->value : (string) $contact->type,
                'is_public' => (bool) $contact->is_public,
            ])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, SocialMedia>  $entries
     * @return list<array<string, mixed>>
     */
    private function socialMediaState(Collection $entries): array
    {
        return $entries
            ->sortBy('created_at')
            ->map(fn (SocialMedia $entry): array => [
                'platform' => $entry->platform instanceof \BackedEnum ? $entry->platform->value : (string) $entry->platform,
                'username' => $entry->username,
                'url' => $entry->url,
            ])
            ->values()
            ->all();
    }
}
