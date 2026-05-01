<?php

declare(strict_types=1);

namespace App\Support\Mcp;

use App\Enums\EventAgeGroup;
use App\Enums\EventFormat;
use App\Enums\EventGenderRestriction;
use App\Enums\EventKeyPersonRole;
use App\Enums\EventStructure;
use App\Enums\EventType;
use App\Enums\EventVisibility;
use App\Enums\ReferenceType;
use App\Enums\TagType;
use App\Models\Address;
use App\Models\DonationChannel;
use App\Models\Event;
use App\Models\EventKeyPerson;
use App\Models\EventSettings;
use App\Models\Institution;
use App\Models\MediaLink;
use App\Models\Reference;
use App\Models\Series;
use App\Models\Space;
use App\Models\Speaker;
use App\Models\Tag;
use App\Models\Venue;
use App\Support\Location\AddressHierarchyFormatter;
use App\Support\Timezone\UserDateTimeFormatter;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Database\Eloquent\MissingAttributeException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Nnjeim\World\Models\Language;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use UnitEnum;

class EventCoverPromptBuilder
{
    /**
     * @var list<string>
     */
    public const array ASPECT_RATIOS = ['auto', '16:9', '4:5'];

    /**
     * @var array<class-string<Model>, array<string, list<string>>>
     */
    private const array MEDIA_COLLECTIONS = [
        Event::class => [
            'poster' => ['preview', 'card', 'thumb'],
            'gallery' => ['thumb'],
        ],
        Institution::class => [
            'logo' => ['thumb'],
            'cover' => ['banner'],
            'gallery' => ['gallery_thumb'],
        ],
        Speaker::class => [
            'avatar' => ['profile', 'thumb'],
            'cover' => ['banner'],
            'gallery' => ['gallery_thumb'],
        ],
        Venue::class => [
            'cover' => ['banner', 'thumb'],
            'gallery' => ['thumb'],
        ],
        Series::class => [
            'cover' => ['thumb'],
            'gallery' => ['thumb'],
        ],
        Reference::class => [
            'front_cover' => ['thumb'],
            'back_cover' => ['thumb'],
            'gallery' => ['gallery_thumb'],
        ],
        DonationChannel::class => [
            'qr' => ['thumb'],
        ],
    ];

    /**
     * @param  array{
     *   aspect_ratio?: string|null,
     *   creative_direction?: string|null,
     *   include_existing_poster?: bool|null
     * }  $options
     * @return array{
     *   payload: array<string, mixed>,
     *   content_media: list<array{media: Media, payload: array<string, mixed>}>
     * }
     */
    public function build(Event $event, array $options = []): array
    {
        $this->loadPromptContext($event);

        $aspectRatio = $this->resolveAspectRatio($event, $options['aspect_ratio'] ?? null);
        $creativeDirection = $this->optionalString($options['creative_direction'] ?? null);
        $includeExistingPoster = $options['include_existing_poster'] ?? true;
        $includeExistingPoster = is_bool($includeExistingPoster) ? $includeExistingPoster : true;

        $selectedMedia = $this->selectedMedia($event, $includeExistingPoster);
        $referenceMedia = array_map(
            fn (array $candidate): array => $candidate['payload'],
            $selectedMedia,
        );

        $sourceData = $this->sourceData($event);
        $prompt = $this->prompt($event, $aspectRatio, $referenceMedia, $creativeDirection);

        return [
            'payload' => [
                'event' => [
                    'id' => (string) $event->getKey(),
                    'route_key' => (string) $event->getRouteKey(),
                    'slug' => (string) $event->slug,
                    'title' => (string) $event->title,
                    'public_url' => route('events.show', ['event' => $event->slug], false),
                ],
                'prompt' => $prompt,
                'upload_spec' => $this->uploadSpec($aspectRatio),
                'reference_media' => $referenceMedia,
                'source_data' => $sourceData,
                'usage' => [
                    'intended_next_step' => 'Use the prompt as the image-generation prompt. Attach the embedded/selected reference_media images when the image model supports image inputs.',
                    'poster_save_target' => [
                        'model' => Event::class,
                        'record_key' => (string) $event->getRouteKey(),
                        'collection' => 'poster',
                        'admin_update_tool' => 'admin-update-record',
                        'member_update_tool' => 'member-update-record',
                    ],
                    'safety_notes' => [
                        'Do not invent speaker likenesses when no actual speaker image is provided.',
                        'Do not invent official logos, QR codes, sponsors, phone numbers, or registration claims.',
                        'Keep all visible event facts consistent with source_data.',
                    ],
                ],
            ],
            'content_media' => $selectedMedia,
        ];
    }

    private function loadPromptContext(Event $event): void
    {
        $event->loadMissing([
            'media',
            'address.country',
            'address.state',
            'address.district',
            'address.subdistrict',
            'address.city',
            'institution.media',
            'institution.address.country',
            'institution.address.state',
            'institution.address.district',
            'institution.address.subdistrict',
            'institution.address.city',
            'venue.media',
            'venue.address.country',
            'venue.address.state',
            'venue.address.district',
            'venue.address.subdistrict',
            'venue.address.city',
            'space',
            'organizer',
            'speakers.media',
            'keyPeople.speaker.media',
            'tags',
            'references.media',
            'references.parentReference.media',
            'series.media',
            'languages',
            'settings',
            'donationChannel.media',
            'mediaLinks',
            'parentEvent.media',
            'childEvents.media',
            'childEvents.institution.media',
            'childEvents.venue.media',
        ]);
    }

    private function resolveAspectRatio(Event $event, mixed $requested): string
    {
        $requestedRatio = is_string($requested) ? trim($requested) : '';

        if (in_array($requestedRatio, ['16:9', '4:5'], true)) {
            return $requestedRatio;
        }

        if ($event->hasMedia('poster')) {
            $existingRatio = (string) $event->poster_display_aspect_ratio;

            if (in_array($existingRatio, ['16:9', '4:5'], true)) {
                return $existingRatio;
            }
        }

        return '16:9';
    }

    /**
     * @return array<string, mixed>
     */
    private function uploadSpec(string $aspectRatio): array
    {
        return [
            'target_model' => Event::class,
            'target_collection' => 'poster',
            'single_file' => true,
            'responsive_images' => true,
            'accepted_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
            'max_file_size_kb' => (int) ceil(((int) config('media-library.max_file_size', 10 * 1024 * 1024)) / 1024),
            'recommended_aspect_ratio' => $aspectRatio,
            'allowed_aspect_ratios' => ['16:9', '4:5'],
            'conversions' => [
                'thumb' => '600x400 cropped webp, sharpened',
                'card' => 'max 960x1200 webp',
                'preview' => 'max 1400x1800 webp',
            ],
            'storage_filename_pattern' => '<event-slug-or-title>-<8-char-ulid>.<ext>',
        ];
    }

    /**
     * @return list<array{media: Media, payload: array<string, mixed>}>
     */
    private function selectedMedia(Event $event, bool $includeExistingPoster): array
    {
        $selected = [];
        $seen = [];

        if ($includeExistingPoster) {
            $this->pushMediaCandidates($selected, $seen, $event, 'poster', ['preview', 'card', 'thumb'], 'existing_event_poster', 'Use for continuity with the current event poster. Improve it; do not copy low-quality text artifacts.', 1);
        }

        $this->pushMediaCandidates($selected, $seen, $event, 'gallery', ['thumb'], 'event_gallery', 'Use as real event/location atmosphere if visually helpful.', 3);

        if ($event->institution instanceof Institution) {
            $this->pushMediaCandidates($selected, $seen, $event->institution, 'cover', ['banner'], 'institution_cover', 'Use as the primary venue/organizer environment reference.', 1);
            $this->pushMediaCandidates($selected, $seen, $event->institution, 'logo', ['thumb'], 'institution_logo', 'Use only if a small authentic organizer mark is helpful.', 1);
            $this->pushMediaCandidates($selected, $seen, $event->institution, 'gallery', ['gallery_thumb'], 'institution_gallery', 'Use for mosque/institution visual atmosphere.', 2);
        }

        if ($event->venue instanceof Venue) {
            $this->pushMediaCandidates($selected, $seen, $event->venue, 'cover', ['banner', 'thumb'], 'venue_cover', 'Use as the physical location reference.', 1);
            $this->pushMediaCandidates($selected, $seen, $event->venue, 'gallery', ['thumb'], 'venue_gallery', 'Use for venue atmosphere and interior cues.', 2);
        }

        foreach ($event->keyPeople as $keyPerson) {
            if (! $keyPerson instanceof EventKeyPerson || ! $keyPerson->speaker instanceof Speaker) {
                continue;
            }

            $role = $keyPerson->role instanceof EventKeyPersonRole ? $keyPerson->role->value : (string) $keyPerson->role;
            $this->pushMediaCandidates(
                $selected,
                $seen,
                $keyPerson->speaker,
                'avatar',
                ['profile', 'thumb'],
                "speaker_avatar:{$role}",
                "Use as the likeness reference for {$keyPerson->display_name} ({$this->roleLabel($keyPerson->role)}).",
                1,
            );
        }

        foreach ($event->references as $reference) {
            if (! $reference instanceof Reference) {
                continue;
            }

            $this->pushMediaCandidates($selected, $seen, $reference, 'front_cover', ['thumb'], 'reference_front_cover', 'Use as a book/reference visual if the cover should include the study text.', 1);
            $this->pushMediaCandidates($selected, $seen, $reference, 'back_cover', ['thumb'], 'reference_back_cover', 'Use only if the front cover is unavailable or the back cover has useful identity cues.', 1);
        }

        foreach ($event->series as $series) {
            if ($series instanceof Series) {
                $this->pushMediaCandidates($selected, $seen, $series, 'cover', ['thumb'], 'series_cover', 'Use for visual continuity with the event series.', 1);
            }
        }

        return array_slice($selected, 0, 10);
    }

    /**
     * @param  list<array{media: Media, payload: array<string, mixed>}>  $selected
     * @param  array<string, true>  $seen
     * @param  list<string>  $preferredConversions
     */
    private function pushMediaCandidates(
        array &$selected,
        array &$seen,
        HasMedia&Model $model,
        string $collection,
        array $preferredConversions,
        string $role,
        string $reason,
        int $limit,
    ): void {
        /** @var Collection<int, Media> $mediaItems */
        $mediaItems = $model->getMedia($collection)->take($limit);

        foreach ($mediaItems as $media) {
            $mediaKey = (string) $media->getKey();

            if (isset($seen[$mediaKey])) {
                continue;
            }

            $seen[$mediaKey] = true;
            $selected[] = [
                'media' => $media,
                'payload' => $this->mediaPayload(
                    media: $media,
                    source: $this->modelSource($model),
                    role: $role,
                    preferredConversions: $preferredConversions,
                    selected: true,
                    selectionReason: $reason,
                ),
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $referenceMedia
     */
    private function prompt(Event $event, string $aspectRatio, array $referenceMedia, ?string $creativeDirection): string
    {
        $lines = [
            'Create a stunning, premium Majlis Ilmu event cover image.',
            '',
            'Output target:',
            '- Event poster for the `poster` media collection.',
            "- Aspect ratio: {$aspectRatio}.",
            '- Must be suitable for upload as image/jpeg, image/png, or image/webp.',
            '- Keep composition readable on mobile event cards and detail pages.',
            '',
            'Required visible event facts:',
            "- Title: {$event->title}",
            "- Date and time: {$this->dateTimeLine($event)}",
        ];

        $location = $this->locationLine($event);

        if ($location !== null) {
            $lines[] = "- Location: {$location}";
        }

        $speakerNames = $this->speakerNames($event);

        if ($speakerNames !== []) {
            $lines[] = '- Speaker(s): '.implode(', ', $speakerNames);
        }

        $keyPeople = $this->nonSpeakerKeyPeopleLine($event);

        if ($keyPeople !== null) {
            $lines[] = "- Other roles: {$keyPeople}";
        }

        $referenceTitles = $this->referenceTitles($event);

        if ($referenceTitles !== []) {
            $lines[] = '- Reference / kitab: '.implode(', ', $referenceTitles);
        }

        $taxonomy = $this->taxonomyLine($event);

        if ($taxonomy !== null) {
            $lines[] = "- Theme taxonomy: {$taxonomy}";
        }

        if ($creativeDirection !== null) {
            $lines[] = "- Additional creative direction: {$creativeDirection}";
        }

        $lines = [
            ...$lines,
            '',
            'Design direction:',
            '- Use refined Islamic editorial design: confident hierarchy, elegant Malay typography, generous negative space, and contemporary masjid/knowledge visual language.',
            '- Prefer authentic visual cues from the selected reference media over generic stock imagery.',
            '- If speaker reference images are provided, preserve likeness respectfully. If no actual speaker media is provided, use text-only speaker treatment and do not invent faces.',
            '- If book/reference cover images are provided, use them as subtle study-material cues rather than copying the full cover as the poster.',
            '- Avoid clutter, fake logos, fake QR codes, fake sponsors, fake phone numbers, and unprovided claims.',
            '- Keep the exact event facts above legible; do not add extra event details not present in source_data.',
        ];

        if ($referenceMedia !== []) {
            $lines[] = '';
            $lines[] = 'Selected reference media to attach/use:';

            foreach ($referenceMedia as $index => $media) {
                $position = $index + 1;
                $label = (string) ($media['label'] ?? $media['file_name'] ?? "Reference media {$position}");
                $role = (string) ($media['role'] ?? 'reference');
                $reason = (string) ($media['selection_reason'] ?? 'Use if helpful.');
                $lines[] = "{$position}. {$label} ({$role}) - {$reason}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceData(Event $event): array
    {
        return [
            'direct_attributes' => $this->normalizeArray($event->getAttributes()),
            'computed' => [
                'description_text' => $event->description_text,
                'timing_display' => $event->timing_display,
                'full_timing_display' => $event->full_timing_display,
                'reference_study_subtitle' => $event->reference_study_subtitle,
                'card_image_url' => $event->card_image_url,
                'poster_display_aspect_ratio' => $event->poster_display_aspect_ratio,
                'poster_orientation' => $event->poster_orientation,
                'event_type_labels' => $this->eventTypeLabels($event->event_type),
                'age_group_labels' => $this->ageGroupLabels($event->age_group),
                'gender_label' => $this->genderLabel($event->gender),
                'event_format_label' => $this->formatLabel($event->event_format),
                'visibility_label' => $this->visibilityLabel($event->visibility),
                'event_structure_label' => $this->structureLabel($event->event_structure),
            ],
            'relations' => [
                'address' => $this->addressPayload($event->addressModel),
                'institution' => $event->institution instanceof Institution ? $this->modelPayload($event->institution) : null,
                'venue' => $event->venue instanceof Venue ? $this->modelPayload($event->venue) : null,
                'space' => $event->space instanceof Space ? $this->modelPayload($event->space) : null,
                'organizer' => $event->organizer instanceof Model ? $this->modelPayload($event->organizer) : null,
                'key_people' => $event->keyPeople->map(fn (EventKeyPerson $keyPerson): array => $this->keyPersonPayload($keyPerson))->values()->all(),
                'speakers' => $event->speakers->map(fn (Speaker $speaker): array => $this->modelPayload($speaker))->values()->all(),
                'references' => $event->references->map(fn (Reference $reference): array => $this->referencePayload($reference))->values()->all(),
                'series' => $event->series->map(fn (Series $series): array => $this->modelPayload($series))->values()->all(),
                'tags' => $this->tagsPayload($event->tags),
                'languages' => $event->languages->map(fn (Language $language): array => $this->languagePayload($language))->values()->all(),
                'settings' => $event->settings instanceof EventSettings ? $this->modelPayload($event->settings) : null,
                'donation_channel' => $event->donationChannel instanceof DonationChannel ? $this->modelPayload($event->donationChannel) : null,
                'media_links' => $event->mediaLinks->map(fn (MediaLink $mediaLink): array => $this->modelPayload($mediaLink))->values()->all(),
                'parent_event' => $event->parentEvent instanceof Event ? $this->relatedEventPayload($event->parentEvent) : null,
                'child_events' => $event->childEvents->map(fn (Event $childEvent): array => $this->relatedEventPayload($childEvent))->values()->all(),
            ],
            'available_media' => $this->modelMediaPayloads($event),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function keyPersonPayload(EventKeyPerson $keyPerson): array
    {
        return [
            ...$this->modelPayload($keyPerson),
            'display_name' => $keyPerson->display_name,
            'role_label' => $this->roleLabel($keyPerson->role),
            'speaker' => $keyPerson->speaker instanceof Speaker ? $this->modelPayload($keyPerson->speaker) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function referencePayload(Reference $reference): array
    {
        return [
            ...$this->modelPayload($reference),
            'display_title' => $reference->displayTitle(),
            'type_label' => ReferenceType::tryFrom((string) $reference->typeValue())?->getLabel(),
            'is_part' => $reference->isPart(),
            'parent_reference' => $reference->parentReference instanceof Reference ? $this->modelPayload($reference->parentReference) : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function relatedEventPayload(Event $event): array
    {
        return [
            'type' => $event->getMorphClass(),
            'class' => $event::class,
            'attributes' => $this->normalizeArray($event->getAttributes()),
            'computed' => [
                'title' => (string) $event->title,
                'route_key' => (string) $event->getRouteKey(),
                'timing_display' => $event->timing_display,
                'card_image_url' => $event->card_image_url,
            ],
            'media' => $this->modelMediaPayloads($event),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function modelPayload(Model $model): array
    {
        return [
            'type' => $model->getMorphClass(),
            'class' => $model::class,
            'attributes' => $this->normalizeArray($model->getAttributes()),
            'label' => $this->modelLabel($model),
            'address' => $this->modelAddressPayload($model),
            'media' => $model instanceof HasMedia ? $this->modelMediaPayloads($model) : [],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function modelAddressPayload(Model $model): ?array
    {
        if (! method_exists($model, 'getAddressModelAttribute')) {
            return null;
        }

        $address = $model->getAttribute('addressModel');

        return $address instanceof Address ? $this->addressPayload($address) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function addressPayload(?Address $address): ?array
    {
        if (! $address instanceof Address) {
            return null;
        }

        return [
            'attributes' => $this->normalizeArray($address->getAttributes()),
            'display_line' => AddressHierarchyFormatter::format($address),
            'display_lines' => AddressHierarchyFormatter::displayLines($address),
            'country' => $address->country?->name,
            'state' => $address->state?->name,
            'district' => $address->district?->name,
            'subdistrict' => $address->subdistrict?->name,
            'city' => $address->city?->name,
        ];
    }

    /**
     * @param  Collection<int, Model>  $tags
     * @return array<string, list<array<string, mixed>>>
     */
    private function tagsPayload(Collection $tags): array
    {
        $grouped = [];

        foreach (TagType::cases() as $type) {
            $grouped[$type->value] = [];
        }

        foreach ($tags as $tag) {
            if (! $tag instanceof Tag) {
                continue;
            }

            $type = is_string($tag->type) && $tag->type !== '' ? $tag->type : 'unknown';
            $grouped[$type] ??= [];
            $grouped[$type][] = [
                'id' => (string) $tag->getKey(),
                'name' => $this->tagName($tag),
                'type' => $type,
                'status' => (string) $tag->status,
                'attributes' => $this->normalizeArray($tag->getAttributes()),
            ];
        }

        return $grouped;
    }

    /**
     * @return array<string, mixed>
     */
    private function languagePayload(Language $language): array
    {
        return [
            'id' => (string) $language->getKey(),
            'code' => $this->stringAttribute($language, 'code'),
            'name' => $this->stringAttribute($language, 'name'),
            'native' => $this->stringAttribute($language, 'native'),
            'attributes' => $this->normalizeArray($language->getAttributes()),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function modelMediaPayloads(HasMedia&Model $model): array
    {
        $collections = self::MEDIA_COLLECTIONS[$model::class] ?? [];
        $media = [];

        foreach ($collections as $collection => $preferredConversions) {
            /** @var Collection<int, Media> $items */
            $items = $model->getMedia($collection);

            foreach ($items as $item) {
                $media[] = $this->mediaPayload(
                    media: $item,
                    source: $this->modelSource($model),
                    role: "{$collection}_media",
                    preferredConversions: $preferredConversions,
                    selected: false,
                    selectionReason: null,
                );
            }
        }

        return $media;
    }

    /**
     * @param  array{type: string, class: class-string<Model>, id: string, label: string|null}  $source
     * @param  list<string>  $preferredConversions
     * @return array<string, mixed>
     */
    private function mediaPayload(
        Media $media,
        array $source,
        string $role,
        array $preferredConversions,
        bool $selected,
        ?string $selectionReason,
    ): array {
        $selectedConversion = $this->selectedConversion($media, $preferredConversions);
        $url = $preferredConversions === [] ? $media->getUrl() : $media->getAvailableUrl($preferredConversions);
        $sourceDimensions = $media->getCustomProperty('source_dimensions', []);

        return [
            'id' => (string) $media->getKey(),
            'role' => $role,
            'source' => $source,
            'collection' => (string) $media->collection_name,
            'conversion' => $selectedConversion,
            'preferred_conversions' => $preferredConversions,
            'label' => $media->name !== '' ? $media->name : $media->file_name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size_bytes' => (int) $media->size,
            'url' => $url !== '' ? $url : null,
            'original_url' => $media->getUrl() !== '' ? $media->getUrl() : null,
            'custom_properties' => $this->normalizeArray($media->custom_properties ?? []),
            'source_dimensions' => is_array($sourceDimensions) ? $this->normalizeArray($sourceDimensions) : [],
            'selected_for_prompt' => $selected,
            'selection_reason' => $selectionReason,
        ];
    }

    /**
     * @param  list<string>  $preferredConversions
     */
    private function selectedConversion(Media $media, array $preferredConversions): ?string
    {
        foreach ($preferredConversions as $conversion) {
            if ($media->hasGeneratedConversion($conversion)) {
                return $conversion;
            }
        }

        return null;
    }

    /**
     * @return array{type: string, class: class-string<Model>, id: string, label: string|null}
     */
    private function modelSource(Model $model): array
    {
        return [
            'type' => $model->getMorphClass(),
            'class' => $model::class,
            'id' => (string) $model->getKey(),
            'label' => $this->modelLabel($model),
        ];
    }

    private function modelLabel(Model $model): ?string
    {
        foreach (['display_name', 'formatted_name', 'display_title', 'title', 'name', 'label'] as $attribute) {
            $value = $this->stringAttribute($model, $attribute);

            if ($value !== null && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }

    private function dateTimeLine(Event $event): string
    {
        $date = $event->starts_at instanceof DateTimeInterface
            ? UserDateTimeFormatter::translatedFormat($event->starts_at, 'l, j F Y')
            : null;
        $start = $event->timing_display;
        $end = $event->ends_at instanceof DateTimeInterface
            ? UserDateTimeFormatter::format($event->ends_at, 'g:i A')
            : null;

        return trim(implode(' ', array_filter([
            $date,
            $end !== null ? "{$start} - {$end}" : $start,
            "({$event->timezone})",
        ])));
    }

    private function locationLine(Event $event): ?string
    {
        $parts = [];

        if ($event->institution instanceof Institution) {
            $parts[] = $event->institution->display_name;
        }

        if ($event->venue instanceof Venue) {
            $parts[] = (string) $event->venue->name;
        }

        if ($event->space instanceof Space) {
            $parts[] = (string) $event->space->name;
        }

        $address = null;

        if ($event->venue instanceof Venue) {
            $address = $event->venue->addressModel;
        }

        if (! $address instanceof Address && $event->institution instanceof Institution) {
            $address = $event->institution->addressModel;
        }

        if (! $address instanceof Address) {
            $address = $event->addressModel;
        }

        $addressLine = AddressHierarchyFormatter::format($address);

        if ($addressLine !== '') {
            $parts[] = $addressLine;
        }

        $line = trim(implode(', ', array_filter($parts)));

        return $line !== '' ? $line : null;
    }

    /**
     * @return list<string>
     */
    private function speakerNames(Event $event): array
    {
        return $event->keyPeople
            ->filter(fn (EventKeyPerson $keyPerson): bool => $this->roleValue($keyPerson->role) === EventKeyPersonRole::Speaker->value)
            ->map(fn (EventKeyPerson $keyPerson): string => $keyPerson->display_name)
            ->filter(fn (string $name): bool => trim($name) !== '')
            ->unique()
            ->values()
            ->all();
    }

    private function nonSpeakerKeyPeopleLine(Event $event): ?string
    {
        $people = $event->keyPeople
            ->reject(fn (EventKeyPerson $keyPerson): bool => $this->roleValue($keyPerson->role) === EventKeyPersonRole::Speaker->value)
            ->map(fn (EventKeyPerson $keyPerson): string => trim($this->roleLabel($keyPerson->role).': '.$keyPerson->display_name))
            ->filter(fn (string $value): bool => $value !== '')
            ->values()
            ->all();

        return $people !== [] ? implode('; ', $people) : null;
    }

    /**
     * @return list<string>
     */
    private function referenceTitles(Event $event): array
    {
        return $event->references
            ->map(fn (Reference $reference): string => $reference->displayTitle())
            ->filter(fn (string $title): bool => trim($title) !== '')
            ->values()
            ->all();
    }

    private function taxonomyLine(Event $event): ?string
    {
        $labels = array_merge(
            $this->eventTypeLabels($event->event_type),
            $event->tags
                ->filter(fn (Model $tag): bool => $tag instanceof Tag)
                ->map(fn (Tag $tag): string => $this->tagName($tag))
                ->filter()
                ->values()
                ->all(),
        );

        return $labels !== [] ? implode(', ', array_unique($labels)) : null;
    }

    /**
     * @return list<string>
     */
    private function eventTypeLabels(mixed $values): array
    {
        return $this->enumLabels($values, EventType::class);
    }

    /**
     * @return list<string>
     */
    private function ageGroupLabels(mixed $values): array
    {
        return $this->enumLabels($values, EventAgeGroup::class);
    }

    /**
     * @param  class-string<BackedEnum>  $enumClass
     * @return list<string>
     */
    private function enumLabels(mixed $values, string $enumClass): array
    {
        $items = $values instanceof Collection ? $values->all() : Arr::wrap($values);
        $labels = [];

        foreach ($items as $item) {
            $enum = $item instanceof $enumClass ? $item : (is_string($item) ? $enumClass::tryFrom($item) : null);

            if ($enum instanceof EventType || $enum instanceof EventAgeGroup) {
                $labels[] = $enum->getLabel();
            } elseif ($enum instanceof BackedEnum) {
                $labels[] = (string) $enum->value;
            }
        }

        return array_values(array_unique(array_filter($labels)));
    }

    private function roleLabel(mixed $role): string
    {
        if ($role instanceof EventKeyPersonRole) {
            return $role->getLabel();
        }

        return EventKeyPersonRole::tryFrom((string) $role)?->getLabel() ?? (string) $role;
    }

    private function roleValue(mixed $role): string
    {
        return $role instanceof EventKeyPersonRole ? $role->value : (string) $role;
    }

    private function genderLabel(mixed $gender): ?string
    {
        if ($gender instanceof EventGenderRestriction) {
            return $gender->getLabel();
        }

        return EventGenderRestriction::tryFrom((string) $gender)?->getLabel();
    }

    private function formatLabel(mixed $format): ?string
    {
        if ($format instanceof EventFormat) {
            return $format->label();
        }

        return EventFormat::tryFrom((string) $format)?->label();
    }

    private function visibilityLabel(mixed $visibility): ?string
    {
        if ($visibility instanceof EventVisibility) {
            return $visibility->getLabel();
        }

        return EventVisibility::tryFrom((string) $visibility)?->getLabel();
    }

    private function structureLabel(mixed $structure): ?string
    {
        if ($structure instanceof EventStructure) {
            return $structure->label();
        }

        return EventStructure::tryFrom((string) $structure)?->label();
    }

    private function tagName(Tag $tag): string
    {
        $name = $tag->getAttribute('name');

        if (is_string($name)) {
            return $name;
        }

        if (is_array($name)) {
            $currentLocale = app()->getLocale();
            $localized = data_get($name, $currentLocale) ?? data_get($name, 'ms') ?? data_get($name, 'en');

            if (is_string($localized) && $localized !== '') {
                return $localized;
            }

            foreach ($name as $value) {
                if (is_string($value) && $value !== '') {
                    return $value;
                }
            }

            return '';
        }

        return '';
    }

    private function stringAttribute(Model $model, string $attribute): ?string
    {
        try {
            $value = $model->getAttribute($attribute);
        } catch (MissingAttributeException) {
            return null;
        }

        return is_string($value) ? $value : null;
    }

    private function optionalString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== '' ? $trimmed : null;
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function normalizeArray(array $values): array
    {
        return array_map($this->normalizeValue(...), $values);
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof Collection) {
            return $value->map($this->normalizeValue(...))->values()->all();
        }

        if (is_array($value)) {
            return $this->normalizeArray($value);
        }

        return $value;
    }
}
