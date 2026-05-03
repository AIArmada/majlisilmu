<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Member;

use App\Mcp\Tools\Concerns\UploadsEventImage;
use App\Models\Event;
use App\Support\Api\Member\MemberResourceRegistry;
use App\Support\Mcp\EventImageUploadService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly(false)]
#[IsIdempotent(false)]
#[IsDestructive(true)]
#[IsOpenWorld(true)]
class MemberUploadEventCoverImageTool extends AbstractMemberTool
{
    use UploadsEventImage;

    protected string $name = 'member-upload-event-cover-image';

    protected string $title = 'Upload Event Cover Image';

    protected string $description = 'Upload and save a 16:9 website/mobile-app cover image for an accessible Ahli event. Accepts image descriptors via {content_base64} (preferred in proxied clients) or {download_url, file_id}. Use the member-event-cover-image-prompt to get the recommended prompt and reference images before generating.';

    public function __construct(
        private readonly MemberResourceRegistry $registry,
        private readonly EventImageUploadService $uploadService,
    ) {
        $this->setMeta([
            'openai/toolInvocation/invoking' => 'Uploading event cover image...',
            'openai/toolInvocation/invoked' => 'Event cover image uploaded.',
            'openai/note' => 'Pass {content_base64, filename} for maximum client/proxy compatibility, or {download_url, file_id, filename} when direct URL descriptors are supported. Required aspect ratio: 16:9.',
        ]);
    }

    public function handle(Request $request): ResponseFactory|Response
    {
        return $this->safeResponse(function () use ($request): ResponseFactory {
            $this->authorizeMember($request);

            $validated = $this->validateArguments($request, [
                'event_key' => ['required', 'string', 'min:1'],
                'image' => ['required'],
                'creative_direction' => ['nullable', 'string', 'max:2000'],
            ]);

            $event = $this->resolveEvent((string) $validated['event_key']);

            abort_unless($event instanceof Event, 404);

            $imageDescriptor = $this->normalizeImageDescriptor($validated['image']);

            $media = $this->uploadService->upload(
                event: $event,
                collection: 'cover',
                descriptor: $imageDescriptor,
                creativeDirection: is_string($validated['creative_direction'] ?? null) ? $validated['creative_direction'] : null,
            );

            return $this->eventImageUploadResponse($event, $media, 'cover');
        });
    }

    private function resolveEvent(string $eventKey): ?Event
    {
        $resourceClass = $this->registry->resolve('events');

        abort_unless(is_string($resourceClass) && $this->registry->canAccessResource($resourceClass), 404);

        $query = $this->registry->queryFor($resourceClass);
        $model = $query->getModel();

        $record = $query
            ->where(function (Builder $query) use ($model, $eventKey): void {
                $query
                    ->where($model->qualifyColumn($model->getRouteKeyName()), $eventKey)
                    ->orWhere($model->qualifyColumn('slug'), $eventKey);
            })
            ->first();

        return $record instanceof Event ? $record : null;
    }

    /**
     * @return array<string, Type>
     */
    #[\Override]
    public function schema(JsonSchema $schema): array
    {
        return $this->eventImageUploadInputSchema($schema);
    }

    /**
     * @return array<string, Type>
     */
    #[\Override]
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->eventImageUploadOutputSchema($schema);
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
                'openai/fileParams' => ['image'],
            ],
        );

        return $tool;
    }
}
