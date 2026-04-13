<?php

namespace App\Data\Api\Event;

use App\Models\Event;
use App\Models\Speaker;
use App\Support\Timezone\UserDateTimeFormatter;
use DateTimeInterface;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Support\Transformation\TransformationContext;
use Spatie\LaravelData\Support\Transformation\TransformationContextFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class EventPayloadData extends Data
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        private readonly array $payload,
    ) {}

    public static function fromModel(Event $event): self
    {
        /** @var array<string, mixed> $payload */
        $payload = [
            ...$event->toArray(),
            'reference_study_subtitle' => $event->reference_study_subtitle,
            'card_image_url' => $event->card_image_url,
            'poster_url' => self::preferredMediaUrl($event->getFirstMedia('poster'), ['preview', 'card', 'thumb']),
            'has_poster' => $event->hasMedia('poster'),
            'timing_display' => $event->timing_display,
            'end_time_display' => $event->ends_at instanceof DateTimeInterface
                ? UserDateTimeFormatter::format($event->ends_at, 'h:i A')
                : null,
        ];

        if ($event->relationLoaded('speakers')) {
            $event->speakers->loadMissing('media');

            $payload['speakers'] = $event->speakers
                ->map(fn (Speaker $speaker): array => EventSpeakerData::fromModel($speaker)->toArray())
                ->values()
                ->all();
        }

        return new self(payload: $payload);
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function transform(
        null|TransformationContextFactory|TransformationContext $transformationContext = null,
    ): array {
        return $this->payload;
    }

    /**
     * @param  list<string>  $preferredConversions
     */
    private static function preferredMediaUrl(?Media $media, array $preferredConversions = []): ?string
    {
        if (! $media instanceof Media) {
            return null;
        }

        $availableUrl = $preferredConversions === []
            ? $media->getUrl()
            : $media->getAvailableUrl($preferredConversions);

        if ($availableUrl !== '') {
            return $availableUrl;
        }

        $originalUrl = $media->getUrl();

        return $originalUrl !== '' ? $originalUrl : null;
    }
}
