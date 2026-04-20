<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Member;

use App\Support\Mcp\VerifiedDocumentationCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use JsonException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsIdempotent]
class MemberDocumentationSearchTool extends AbstractMemberTool
{
    protected string $name = 'search';

    protected string $title = 'Search Verified Documentation';

    protected string $description = 'Use this when you need to search the verified MajlisIlmu MCP and CRUD documentation exposed by this server. Do not use this for Ahli resource, institution, speaker, reference, or event record searches.';

    public function __construct(
        private readonly VerifiedDocumentationCatalog $documentationCatalog,
    ) {
        $this->setMeta([
            'openai/toolInvocation/invoking' => 'Searching verified docs…',
            'openai/toolInvocation/invoked' => 'Documentation results ready.',
        ]);
    }

    public function handle(Request $request): ResponseFactory|Response
    {
        return $this->safeResponse(function () use ($request): Response {
            $this->authorizeMember($request);

            $validated = $this->validateArguments($request, [
                'query' => ['required', 'string', 'min:1'],
            ]);

            return Response::text($this->jsonEncode(
                $this->documentationCatalog->search((string) $validated['query']),
            ));
        });
    }

    /**
     * @return array<string, Type>
     */
    #[\Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->required()->min(1)->description('Natural-language query used to search the verified documentation pages exposed by this server.'),
        ];
    }

    public function shouldRegister(Request $request): bool
    {
        return $this->documentationCatalog->hasAnyDocuments();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function jsonEncode(array $payload): string
    {
        try {
            return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } catch (JsonException) {
            return '{"results":[]}';
        }
    }
}
