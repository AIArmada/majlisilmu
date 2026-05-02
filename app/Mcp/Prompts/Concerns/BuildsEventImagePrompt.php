<?php

declare(strict_types=1);

namespace App\Mcp\Prompts\Concerns;

use App\Models\Event;
use App\Support\Mcp\EventCoverPromptBuilder;
use Illuminate\Support\Facades\Storage;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Prompts\Argument;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Throwable;

trait BuildsEventImagePrompt
{
    /**
     * @return array<int, Argument>
     */
    protected function eventImagePromptArguments(): array
    {
        return [
            new Argument(
                name: 'event_key',
                description: 'Event UUID or slug/route key. Example: tadabbur-isu-semasa-ummah-qdkhqqn.',
                required: true,
            ),
            new Argument(
                name: 'creative_direction',
                description: 'Optional additional design direction such as mood, color preference, typography style, or what to emphasize.',
                required: false,
            ),
            new Argument(
                name: 'include_existing_media',
                description: 'Whether to include existing event media as reference images. Defaults to true.',
                required: false,
            ),
            new Argument(
                name: 'max_reference_media',
                description: 'Maximum number of reference images to include (0–8). Defaults to 4.',
                required: false,
            ),
        ];
    }

    /**
     * Build the array of MCP prompt messages for the given event and target collection.
     *
     * Returns the prompt text as a user message followed by reference images as individual
     * user messages, so ChatGPT can use them as input when generating the event image.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<int, Response>
     */
    protected function buildEventImagePromptMessages(Event $event, string $targetCollection, array $arguments): array
    {
        $builder = app(EventCoverPromptBuilder::class);

        $maxReferenceMedia = max(0, min(8, (int) ($arguments['max_reference_media'] ?? 4)));

        $result = $builder->build($event, [
            'target_collection' => $targetCollection,
            'creative_direction' => is_string($arguments['creative_direction'] ?? null) && trim((string) $arguments['creative_direction']) !== ''
                ? trim((string) $arguments['creative_direction'])
                : null,
            'include_existing_media' => $this->parseBoolArgument($arguments['include_existing_media'] ?? null, true),
        ]);

        $payload = $result['payload'];
        $contentMedia = array_slice($result['content_media'], 0, $maxReferenceMedia);

        $promptText = $this->buildPromptMessageText($payload, $targetCollection);

        $messages = [Response::text($promptText)];

        foreach ($contentMedia as $mediaItem) {
            $imageResponse = $this->imageResponseForMedia($mediaItem['media']);

            if ($imageResponse instanceof Response) {
                $messages[] = $imageResponse;
            }
        }

        return $messages;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildPromptMessageText(array $payload, string $targetCollection): string
    {
        $prompt = is_string($payload['prompt'] ?? null) ? $payload['prompt'] : '';
        $target = is_array($payload['target'] ?? null) ? $payload['target'] : [];
        $aspectRatio = is_string($target['aspect_ratio'] ?? null) ? $target['aspect_ratio'] : ($targetCollection === 'poster' ? '4:5' : '16:9');
        $outputWidth = is_int($target['output_width'] ?? null) ? $target['output_width'] : ($targetCollection === 'poster' ? 1600 : 1600);
        $outputHeight = is_int($target['output_height'] ?? null) ? $target['output_height'] : ($targetCollection === 'poster' ? 2000 : 900);
        $event = is_array($payload['event'] ?? null) ? $payload['event'] : [];
        $eventTitle = is_string($event['title'] ?? null) ? $event['title'] : '';
        $eventKey = is_string($event['route_key'] ?? null) ? $event['route_key'] : '';

        $collectionLabel = $targetCollection === 'poster' ? 'marketing poster (4:5 portrait)' : 'website/mobile cover (16:9 landscape)';
        $uploadTool = $targetCollection === 'poster' ? 'upload-event-poster-image' : 'upload-event-cover-image';

        $safetyNotes = is_array($payload['usage']['safety_notes'] ?? null)
            ? implode("\n", array_map(fn (mixed $n): string => '- '.(string) $n, $payload['usage']['safety_notes']))
            : '';

        $referenceNote = '';

        if (is_array($payload['reference_media'] ?? null) && count($payload['reference_media']) > 0) {
            $referenceNote = "\n\nReference images are attached to this message. Use them for brand/style/identity consistency.";
        }

        return <<<TEXT
        Generate a {$collectionLabel} for the following event:

        {$prompt}

        ---
        **Output requirements:**
        - Aspect ratio: **{$aspectRatio}**
        - Recommended output size: {$outputWidth}×{$outputHeight} px
        - Do NOT add letterboxing or padding — fill the entire canvas

        **Safety rules:**
        {$safetyNotes}{$referenceNote}

        ---
        Once you have generated the image, use the **{$uploadTool}** tool to save it to the event:
        - event_key: `{$eventKey}`
        - event_title: {$eventTitle}
        TEXT;
    }

    private function imageResponseForMedia(Media $media): ?Response
    {
        $mimeType = is_string($media->mime_type) ? $media->mime_type : null;

        if ($mimeType === null || ! str_starts_with($mimeType, 'image/')) {
            return null;
        }

        $contents = $this->loadMediaContents($media);

        if ($contents === null) {
            return null;
        }

        return Response::image($contents, $mimeType);
    }

    private function loadMediaContents(Media $media): ?string
    {
        $relativePath = $media->getPathRelativeToRoot();
        $disk = is_string($media->disk) ? $media->disk : null;

        if ($relativePath !== '' && is_string($disk) && $disk !== '') {
            try {
                if (Storage::disk($disk)->exists($relativePath)) {
                    $contents = Storage::disk($disk)->get($relativePath);

                    if (is_string($contents) && $contents !== '') {
                        return $contents;
                    }
                }
            } catch (Throwable) {
                // Fall through to local path fallback.
            }
        }

        $path = $media->getPath();

        if ($path !== '' && is_file($path)) {
            $contents = file_get_contents($path);

            if (is_string($contents) && $contents !== '') {
                return $contents;
            }
        }

        return null;
    }

    private function parseBoolArgument(mixed $value, bool $default): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return in_array(strtolower(trim($value)), ['true', '1', 'yes'], true);
        }

        return $default;
    }
}
