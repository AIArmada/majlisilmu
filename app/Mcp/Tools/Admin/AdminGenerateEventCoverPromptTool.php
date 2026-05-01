<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Admin;

use App\Mcp\Tools\Concerns\ReturnsEventCoverPromptResponse;
use App\Models\Event;
use App\Support\Api\Admin\AdminResourceRegistry;
use App\Support\Mcp\EventCoverPromptBuilder;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class AdminGenerateEventCoverPromptTool extends AbstractAdminTool
{
    use ReturnsEventCoverPromptResponse;

    protected string $name = 'admin-generate-event-cover-prompt';

    protected string $title = 'Generate Event Cover Prompt';

    protected string $description = 'Use this when an admin asks ChatGPT to create a new Majlis Ilmu event cover/poster image for a specific event. It returns a ready image-generation prompt, selected reference images, and direct event/relation/media context. Do not use this to save the generated poster; after user approval, use admin-update-record with the Event poster media field.';

    public function __construct(
        private readonly AdminResourceRegistry $registry,
        private readonly EventCoverPromptBuilder $promptBuilder,
    ) {
        $this->setMeta([
            'openai/toolInvocation/invoking' => 'Building event cover prompt...',
            'openai/toolInvocation/invoked' => 'Event cover prompt ready.',
        ]);
    }

    public function handle(Request $request): ResponseFactory|Response
    {
        return $this->safeResponse(function () use ($request): ResponseFactory {
            $this->authorizeAdmin($request);

            $validated = $this->validateArguments($request, [
                'event_key' => ['required', 'string', 'min:1'],
                'aspect_ratio' => ['nullable', 'string', 'in:auto,16:9,4:5'],
                'creative_direction' => ['nullable', 'string', 'max:2000'],
                'include_existing_poster' => ['nullable', 'boolean'],
                'embed_selected_media' => ['nullable', 'boolean'],
                'max_embedded_media' => ['nullable', 'integer', 'min:0', 'max:8'],
            ]);

            $resourceClass = $this->registry->resolve('events');

            abort_unless(is_string($resourceClass) && $this->registry->canAccessResource($resourceClass), 404);

            $record = $this->resolveEvent($resourceClass, (string) $validated['event_key']);

            abort_unless($record instanceof Event, 404);

            $result = $this->promptBuilder->build($record, [
                'aspect_ratio' => $validated['aspect_ratio'] ?? 'auto',
                'creative_direction' => $validated['creative_direction'] ?? null,
                'include_existing_poster' => $this->booleanArgument($validated['include_existing_poster'] ?? null, true),
            ]);

            return $this->eventCoverPromptResponse(
                result: $result,
                embedSelectedMedia: $this->booleanArgument($validated['embed_selected_media'] ?? null, true),
                maxEmbeddedMedia: $this->integerArgument($validated['max_embedded_media'] ?? null, 6, 0, 8),
            );
        });
    }

    private function resolveEvent(string $resourceClass, string $eventKey): ?Event
    {
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
        return $this->eventCoverPromptInputSchema($schema);
    }

    /**
     * @return array<string, Type>
     */
    #[\Override]
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->eventCoverPromptOutputSchema($schema);
    }
}
