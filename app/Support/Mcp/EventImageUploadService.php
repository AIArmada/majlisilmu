<?php

declare(strict_types=1);

namespace App\Support\Mcp;

use App\Models\Event;
use Illuminate\Http\UploadedFile;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class EventImageUploadService
{
    public const array ACCEPTED_COLLECTIONS = ['cover', 'poster'];

    /**
     * Upload a single image from an MCP file descriptor to an event's cover or poster collection.
     *
     * The descriptor must contain either:
     *   - {download_url, filename} — fetches the image from the URL (ChatGPT download_url pattern)
     *   - {content_base64, filename} — decodes the base64-encoded image
     *
     * Optional descriptor keys: file_id (ChatGPT reference metadata), mime_type.
     *
     * @param  array<string, mixed>  $descriptor
     */
    public function upload(
        Event $event,
        string $collection,
        array $descriptor,
        ?string $creativeDirection = null,
    ): Media {
        abort_unless(in_array($collection, self::ACCEPTED_COLLECTIONS, true), 422, 'Collection must be one of: '.implode(', ', self::ACCEPTED_COLLECTIONS));

        $normalizer = app(McpFilePayloadNormalizer::class);

        $contract = [
            'type' => 'file',
            'accepted_mimetypes' => ['image/jpeg', 'image/png', 'image/webp'],
            'max_size_kb' => 20480,
        ];

        $result = $normalizer->normalize(
            [$collection => $descriptor],
            [$collection => $contract],
        );

        /** @var UploadedFile $uploadedFile */
        $uploadedFile = $result['payload'][$collection];

        try {
            return $this->storeMedia($event, $collection, $uploadedFile, $descriptor, $creativeDirection);
        } finally {
            $normalizer->cleanup($result['temporary_paths']);
        }
    }

    /**
     * @param  array<string, mixed>  $descriptor
     */
    private function storeMedia(
        Event $event,
        string $collection,
        UploadedFile $uploadedFile,
        array $descriptor,
        ?string $creativeDirection,
    ): Media {
        $label = $collection === 'poster' ? 'Event Poster Image' : 'Event Cover Image';
        $fileId = $this->stringFromDescriptor($descriptor, ['file_id', 'fileId']);

        $customProperties = [
            'collection' => $collection,
            'original_file_name' => $uploadedFile->getClientOriginalName(),
            'source' => 'mcp_uploaded',
        ];

        if ($fileId !== null) {
            $customProperties['chatgpt_file_id'] = $fileId;
        }

        if ($creativeDirection !== null && $creativeDirection !== '') {
            $customProperties['creative_direction'] = $creativeDirection;
        }

        $title = is_string($event->title) && $event->title !== '' ? $event->title : (string) $event->getKey();

        return $event
            ->addMedia($uploadedFile)
            ->usingName("{$label} - {$title}")
            ->withCustomProperties($customProperties)
            ->toMediaCollection($collection);
    }

    /**
     * @param  array<string, mixed>  $descriptor
     * @param  list<string>  $keys
     */
    private function stringFromDescriptor(array $descriptor, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $descriptor[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
