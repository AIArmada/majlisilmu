<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Concerns;

use App\Models\Event;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

trait UploadsEventImage
{
    /**
     * @return array<string, Type>
     */
    protected function eventImageUploadInputSchema(JsonSchema $schema): array
    {
        return [
            'event_key' => $schema->string()
                ->required()
                ->min(1)
                ->description('Event UUID or slug/route key. Example: tadabbur-isu-semasa-ummah-qdkhqqn.'),
            'image' => $schema->object([
                'filename' => $schema->string()->required()->description('Filename including extension, e.g. event-cover.jpg.'),
                'download_url' => $schema->string()->nullable()->description('Temporary download URL for the image (preferred for ChatGPT-generated images).'),
                'content_base64' => $schema->string()->nullable()->description('Base64-encoded image content (alternative to download_url).'),
                'file_id' => $schema->string()->nullable()->description('ChatGPT file_id stored as metadata (optional).'),
                'mime_type' => $schema->string()->nullable()->description('MIME type of the image, e.g. image/jpeg, image/png, image/webp. Auto-detected if omitted.'),
            ])
                ->required()
                ->description('Image file descriptor. For ChatGPT-generated images pass {download_url, file_id, filename}. For base64 images pass {content_base64, filename}.'),
            'creative_direction' => $schema->string()
                ->nullable()
                ->description('Optional note about the creative direction saved as metadata on the media item.'),
        ];
    }

    /**
     * @return array<string, Type>
     */
    protected function eventImageUploadOutputSchema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->object([
                'id' => $schema->string()->required(),
                'route_key' => $schema->string()->required(),
                'slug' => $schema->string()->required(),
                'title' => $schema->string()->required(),
                'public_url' => $schema->string()->required(),
            ])->required(),
            'collection' => $schema->string()->required()->description("The media collection the image was saved to ('cover' or 'poster')."),
            'media' => $schema->object([
                'id' => $schema->string()->required(),
                'url' => $schema->string()->required(),
                'collection' => $schema->string()->required(),
                'mime_type' => $schema->string()->required(),
                'file_name' => $schema->string()->required(),
                'name' => $schema->string()->required(),
                'size' => $schema->integer()->required(),
            ])->required()->description('The stored Spatie MediaLibrary item.'),
        ];
    }

    /**
     * Normalize the raw `image` argument into a file descriptor array.
     *
     * Some MCP clients (e.g. ChatGPT with `openai/fileParams`) serialize object
     * parameters as a JSON-encoded string instead of a JSON object. This method
     * accepts both formats so upload tools work correctly regardless of the client.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException if the value is neither a valid descriptor array
     *                             nor a JSON-encoded descriptor object
     */
    protected function normalizeImageDescriptor(mixed $value): array
    {
        if (is_array($value) && ! array_is_list($value)) {
            Log::debug('mcp.image_upload: descriptor received as associative array', [
                'keys' => array_keys($value),
            ]);

            return $value;
        }

        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded) && ! array_is_list($decoded)) {
                Log::debug('mcp.image_upload: descriptor received as JSON-encoded string (openai/fileParams), decoded successfully', [
                    'keys' => array_keys($decoded),
                    'json_length' => strlen($value),
                ]);

                return $decoded;
            }

            Log::debug('mcp.image_upload: descriptor received as string but could not be decoded as JSON object', [
                'type' => gettype($value),
                'json_error' => json_last_error_msg(),
                'preview' => substr($value, 0, 100),
            ]);
        } else {
            Log::debug('mcp.image_upload: descriptor received with unexpected type', [
                'type' => gettype($value),
                'is_list' => is_array($value) && array_is_list($value),
            ]);
        }

        throw ValidationException::withMessages([
            'image' => ['The image must be a valid file descriptor object.'],
        ]);
    }

    protected function eventImageUploadResponse(Event $event, Media $media, string $collection): ResponseFactory
    {
        $url = $media->getUrl();
        $text = "Uploaded {$collection} image for ".((string) $event->title).'.';

        if ($url !== '') {
            $text .= "\nSaved media URL: {$url}";
        }

        return Response::structured([
            'event' => [
                'id' => (string) $event->getKey(),
                'route_key' => (string) $event->getRouteKey(),
                'slug' => (string) $event->slug,
                'title' => (string) $event->title,
                'public_url' => route('events.show', ['event' => $event->slug], false),
            ],
            'collection' => $collection,
            'media' => [
                'id' => (string) $media->getKey(),
                'url' => $url,
                'collection' => (string) $media->collection_name,
                'mime_type' => (string) $media->mime_type,
                'file_name' => (string) $media->file_name,
                'name' => (string) $media->name,
                'size' => (int) $media->size,
            ],
        ]);
    }
}
