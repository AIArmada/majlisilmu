<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Concerns;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

trait ReturnsEventCoverPromptResponse
{
    /**
     * @return array<string, Type>
     */
    protected function eventCoverPromptInputSchema(JsonSchema $schema): array
    {
        return [
            'event_key' => $schema->string()
                ->required()
                ->min(1)
                ->description('Event UUID or slug/route key. Example: tadabbur-isu-semasa-ummah-qdkhqqn.'),
            'aspect_ratio' => $schema->string()
                ->nullable()
                ->enum(['auto', '16:9', '4:5'])
                ->default('auto')
                ->description('Target poster aspect ratio. Use auto to reuse an existing poster ratio when available, otherwise 16:9.'),
            'creative_direction' => $schema->string()
                ->nullable()
                ->description('Optional additional design direction from the user, such as mood, color preference, or typography style.'),
            'include_existing_poster' => $schema->boolean()
                ->nullable()
                ->default(true)
                ->description('Whether to include the current event poster as reference media when one exists.'),
            'embed_selected_media' => $schema->boolean()
                ->nullable()
                ->default(true)
                ->description('Whether to embed selected reference images in the MCP tool response content for ChatGPT to use as image inputs.'),
            'max_embedded_media' => $schema->integer()
                ->nullable()
                ->min(0)
                ->max(8)
                ->default(6)
                ->description('Maximum number of selected reference images to embed in the tool response content.'),
        ];
    }

    /**
     * @return array<string, Type>
     */
    protected function eventCoverPromptOutputSchema(JsonSchema $schema): array
    {
        return [
            'event' => $schema->object([
                'id' => $schema->string()->required(),
                'route_key' => $schema->string()->required(),
                'slug' => $schema->string()->required(),
                'title' => $schema->string()->required(),
                'public_url' => $schema->string()->required(),
            ])->required(),
            'prompt' => $schema->string()
                ->required()
                ->description('Ready-to-use image-generation prompt for creating the event cover image.'),
            'upload_spec' => $schema->object()->required(),
            'reference_media' => $schema->array()
                ->required()
                ->items($schema->object())
                ->description('Selected media references from the event and its relations. Embedded items also appear as MCP image content.'),
            'source_data' => $schema->object()
                ->required()
                ->description('Direct event attributes, computed values, relation data, and available media used to construct the prompt.'),
            'usage' => $schema->object()->required(),
        ];
    }

    /**
     * @param  array{
     *   payload: array<string, mixed>,
     *   content_media: list<array{media: Media, payload: array<string, mixed>}>
     * }  $result
     */
    protected function eventCoverPromptResponse(array $result, bool $embedSelectedMedia, int $maxEmbeddedMedia): ResponseFactory
    {
        $payload = $result['payload'];
        $responses = [
            Response::text((string) $payload['prompt']),
        ];

        if ($embedSelectedMedia && $maxEmbeddedMedia > 0) {
            $embeddedMediaIds = [];

            foreach (array_slice($result['content_media'], 0, $maxEmbeddedMedia) as $mediaItem) {
                $response = $this->mediaResponse($mediaItem['media'], $mediaItem['payload']);

                if (! $response instanceof Response) {
                    continue;
                }

                $responses[] = $response;
                $embeddedMediaIds[] = (string) $mediaItem['payload']['id'];
            }

            $payload['reference_media'] = array_map(
                function (array $media) use ($embeddedMediaIds): array {
                    $media['embedded_in_mcp_content'] = in_array((string) ($media['id'] ?? ''), $embeddedMediaIds, true);

                    return $media;
                },
                is_array($payload['reference_media'] ?? null) ? $payload['reference_media'] : [],
            );
        }

        return Response::make($responses)->withStructuredContent($payload);
    }

    protected function booleanArgument(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return is_bool($normalized) ? $normalized : $default;
    }

    protected function integerArgument(mixed $value, int $default, int $min, int $max): int
    {
        if (! is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function mediaResponse(Media $media, array $payload): ?Response
    {
        if (! is_string($media->mime_type) || ! str_starts_with($media->mime_type, 'image/')) {
            return null;
        }

        $path = $media->getPathRelativeToRoot();

        if ($path === '') {
            return null;
        }

        try {
            $contents = Storage::disk($media->disk)->get($path);
        } catch (Throwable) {
            return null;
        }

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        return Response::image($contents, $media->mime_type)
            ->withMeta([
                'id' => (string) ($payload['id'] ?? $media->getKey()),
                'role' => (string) ($payload['role'] ?? 'reference_media'),
                'label' => (string) ($payload['label'] ?? $media->name),
                'collection' => (string) $media->collection_name,
                'source' => $payload['source'] ?? null,
                'url' => $payload['url'] ?? null,
            ]);
    }
}
