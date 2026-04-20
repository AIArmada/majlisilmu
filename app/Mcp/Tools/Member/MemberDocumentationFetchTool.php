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
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[IsReadOnly]
#[IsIdempotent]
class MemberDocumentationFetchTool extends AbstractMemberTool
{
    protected string $name = 'fetch';

    protected string $title = 'Fetch Verified Documentation Page';

    protected string $description = 'Use this when you need the full text of a verified MajlisIlmu documentation page returned by the search tool. Do not use this for Ahli record fetches; use the member record tools for those.';

    public function __construct(
        private readonly VerifiedDocumentationCatalog $documentationCatalog,
    ) {
        $this->setMeta([
            'openai/toolInvocation/invoking' => 'Loading verified doc…',
            'openai/toolInvocation/invoked' => 'Documentation page ready.',
        ]);
    }

    public function handle(Request $request): ResponseFactory|Response
    {
        return $this->safeResponse(function () use ($request): Response {
            $this->authorizeMember($request);

            $validated = $this->validateArguments($request, [
                'id' => ['required', 'string', 'min:1'],
            ]);

            $document = $this->documentationCatalog->fetch((string) $validated['id']);

            if ($document === null) {
                throw new NotFoundHttpException('Documentation page not found.');
            }

            return Response::text($this->jsonEncode($document));
        });
    }

    /**
     * @return array<string, Type>
     */
    #[\Override]
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->string()->required()->min(1)->description('Stable documentation identifier returned by the search tool, such as docs-mcp-guide.'),
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
            return '{}';
        }
    }
}
