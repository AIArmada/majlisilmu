<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Admin;

use App\Mcp\Tools\Concerns\GeneratesEventImageResponse;
use App\Models\Event;
use App\Support\Api\Admin\AdminResourceRegistry;
use App\Support\Mcp\EventImageGenerationService;
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
class AdminGenerateEventCoverImageTool extends AbstractAdminTool
{
    use GeneratesEventImageResponse;

    protected string $name = 'admin-generate-event-cover-image';

    protected string $title = 'Generate Event Cover Image';

    protected string $description = 'Generate and save a 16:9 website/mobile-app cover image for one admin-accessible event. The tool builds a prompt from event data, relation data, and selected available media, attaches suitable reference images, generates the image, normalizes it to 16:9, and stores it in the Event cover media collection.';

    public function __construct(
        private readonly AdminResourceRegistry $registry,
        private readonly EventImageGenerationService $imageGenerationService,
    ) {
        $this->setMeta([
            'openai/toolInvocation/invoking' => 'Generating event cover image...',
            'openai/toolInvocation/invoked' => 'Event cover image generated.',
        ]);
    }

    public function handle(Request $request): ResponseFactory|Response
    {
        return $this->safeResponse(function () use ($request): ResponseFactory {
            $this->authorizeAdmin($request);

            $validated = $this->validateArguments($request, [
                'event_key' => ['required', 'string', 'min:1'],
                'creative_direction' => ['nullable', 'string', 'max:2000'],
                'include_existing_media' => ['nullable', 'boolean'],
                'max_reference_media' => ['nullable', 'integer', 'min:0', 'max:8'],
            ]);

            $event = $this->resolveEvent((string) $validated['event_key']);

            abort_unless($event instanceof Event, 404);

            return $this->eventImageResponse($this->imageGenerationService->generate($event, 'cover', [
                'creative_direction' => $validated['creative_direction'] ?? null,
                'include_existing_media' => $this->booleanArgument($validated['include_existing_media'] ?? null, true),
                'max_reference_media' => $this->integerArgument($validated['max_reference_media'] ?? null, 6, 0, 8),
            ]));
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
        return $this->eventImageInputSchema($schema);
    }

    /**
     * @return array<string, Type>
     */
    #[\Override]
    public function outputSchema(JsonSchema $schema): array
    {
        return $this->eventImageOutputSchema($schema);
    }
}
