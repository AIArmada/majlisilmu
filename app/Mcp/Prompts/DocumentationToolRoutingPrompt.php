<?php

declare(strict_types=1);

namespace App\Mcp\Prompts;

use App\Enums\InstitutionType;
use App\Enums\VenueType;
use Illuminate\Support\Str;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Title;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Prompts\Argument;

#[Name('documentation-tool-routing')]
#[Title('Documentation Tool Routing')]
#[Description('Short guidance for deciding when to use the verified documentation search and fetch tools exposed by this server, with an optional topic hint for more targeted advice.')]
class DocumentationToolRoutingPrompt extends Prompt
{
    public function handle(Request $request): Response
    {
        $validated = $request->validate([
            'topic' => ['nullable', 'string', 'max:100'],
        ]);

        $topic = is_string($validated['topic'] ?? null)
            ? trim($validated['topic'])
            : '';

        return Response::text($this->buildPromptText($topic))->asAssistant();
    }

    /**
     * @return array<int, Argument>
     */
    #[\Override]
    public function arguments(): array
    {
        return [
            new Argument(
                name: 'topic',
                description: 'Optional focus area such as crud, auth, media uploads, runtime records, entity selection, search, or fetch.',
                required: false,
            ),
        ];
    }

    private function buildPromptText(string $topic): string
    {
        return implode("\n\n", array_filter([
            <<<'TEXT'
Use the verified documentation tools like this:

- Use `fetch` first when the question is clearly about MajlisIlmu MCP behavior, because `docs-mcp-guide` is the single exposed MCP-facing guide.
- Use `search` when you want confirmation before fetching, when the user asks a fuzzy topical question, or when you want a model-friendly discovery step before quoting the guide.

Known verified page id:
- `docs-mcp-guide` — auth, transport, discovery primitives, capability matrix, media descriptor rules, preview semantics, writable resources, and connector guidance.

Do not use `search` or `fetch` for live runtime records or mutations:
- use the admin/member resource, record, relation, and write-schema tools for runtime data
- use the docs tools only for verified documentation
TEXT,
            $this->entitySelectionGuidance(),
            <<<'TEXT'
Recommended flow:
1. If the question is obviously about the exposed MCP docs, call `fetch` with `docs-mcp-guide` directly.
2. Otherwise call `search` with a short topical query.
3. Call `fetch` with the best matching page id.
4. Base the answer or next runtime tool call on the fetched guide.

When the claim is about create/update/delete support, preview behavior, related-record traversal, or which surface owns a capability, confirm it from the fetched verified docs before answering.
TEXT,
            $this->topicSpecificGuidance($topic),
        ]));
    }

    private function entitySelectionGuidance(): string
    {
        $institutionTerms = $this->formattedTerms($this->institutionTerms());
        $venueTerms = $this->formattedTerms($this->venueTerms());

        return <<<TEXT
For named-place queries, use this entity-selection heuristic before guessing record types:
- Search `institutions` first when the noun matches an institution type such as {$institutionTerms}.
- Search `venues` first when the noun matches a venue type such as {$venueTerms}.
- Treat `spaces` as finer-grained sublocations inside an institution, not as the default first lookup target for named mosques, surau, or other institution identities.
- Example: `Masjid Abidin` should be searched in `institutions` first. If the noun does not match the institution-type terms and sounds like a standalone physical place, look in `venues` next.
TEXT;
    }

    private function topicSpecificGuidance(string $topic): string
    {
        $normalizedTopic = Str::of($topic)->lower()->squish()->value();

        if ($normalizedTopic === '') {
            return '';
        }

        $displayTopic = trim($topic);
        $institutionTerms = $this->institutionTerms();
        $venueTerms = $this->venueTerms();
        $institutionTermsList = $this->formattedTerms($institutionTerms);
        $venueTermsList = $this->formattedTerms($venueTerms);

        return match (true) {
            $this->matchesAny($normalizedTopic, ['crud', 'create', 'update', 'delete', 'preview', 'parity', 'capability']) => <<<TEXT
Topic-specific guidance for "{$displayTopic}":
- Fetch `docs-mcp-guide` and focus on the MCP capability matrix, writable resource matrix, and preview sections.
- Suggested `search` query: `mcp capability matrix preview related records`.
- Confirm which surface actually supports create, update, delete, preview, or related-record traversal before answering.
TEXT,
            $this->matchesAny($normalizedTopic, ['auth', 'oauth', 'token', 'scope', 'connector', 'login']) => <<<TEXT
Topic-specific guidance for "{$displayTopic}":
- Fetch `docs-mcp-guide` and focus on the connection, auth, and connector sections.
- Suggested `search` query: `oauth token auth connector scopes`.
- Confirm whether the question is about bearer tokens, OAuth connector setup, redirect allowlists, or `mcp:use` scope handling before answering.
TEXT,
            $this->matchesAny($normalizedTopic, ['media', 'upload', 'file', 'descriptor', 'base64']) => <<<TEXT
Topic-specific guidance for "{$displayTopic}":
- Fetch `docs-mcp-guide` and focus on the MCP media/file upload contract and preview rules sections.
- Suggested `search` query: `media upload descriptor base64 preview`.
- Confirm `json base64 descriptor` transport, schema-advertised file fields, and whether `validate_only` preview applies on the target surface.
TEXT,
            $this->matchesAny($normalizedTopic, ['runtime', 'record', 'records', 'resource', 'relation', 'live data']) => <<<TEXT
Topic-specific guidance for "{$displayTopic}":
- Do not treat the docs tools as live data tools.
- Use admin/member list, get-record, related-record, and write-schema tools for runtime records.
- Use the verified docs only when you need to confirm what a tool or surface is supposed to do.
TEXT,
            $this->matchesAny($normalizedTopic, array_merge(['entity', 'selection', 'institution', 'venue', 'space', 'location'], $institutionTerms, $venueTerms)) => <<<TEXT
Topic-specific guidance for "{$displayTopic}":
- Search `institutions` first for institution-type nouns such as {$institutionTermsList}.
- Search `venues` first for venue-type nouns such as {$venueTermsList}.
- Only search `spaces` first when the user is clearly asking about an internal hall, room, or sublocation inside a known institution.
- Example: `Masjid Abidin` belongs in `institutions` first, not `venues` or `spaces`.
TEXT,
            $this->matchesAny($normalizedTopic, ['search']) => <<<TEXT
Topic-specific guidance for "{$displayTopic}":
- Start with `search` because the question is still discovery-oriented.
- Keep the query short and topical, then `fetch` the best match.
- If the result is clearly the MCP guide, fetch `docs-mcp-guide` and answer from that canonical page.
TEXT,
            $this->matchesAny($normalizedTopic, ['fetch']) => <<<TEXT
Topic-specific guidance for "{$displayTopic}":
- Use `fetch` when you already know the page id or want the canonical MCP guide immediately.
- If the question is about the exposed MajlisIlmu MCP docs, go straight to `docs-mcp-guide`.
- Quote or summarize from the fetched page before making capability claims.
TEXT,
            default => <<<TEXT
Topic-specific guidance for "{$displayTopic}":
- Start with `search` using that phrase or a shorter topical variant.
- `fetch` the strongest matching verified page before making capability or contract claims.
- Prefer `docs-mcp-guide`, because it is the canonical exposed MCP guide and includes the capability matrix and writable resource summaries.
TEXT,
        };
    }

    /**
     * @return list<string>
     */
    private function institutionTerms(): array
    {
        return array_map(
            static fn (InstitutionType $type): string => $type->value,
            InstitutionType::cases(),
        );
    }

    /**
     * @return list<string>
     */
    private function venueTerms(): array
    {
        return array_map(
            static fn (VenueType $type): string => $type->value,
            VenueType::cases(),
        );
    }

    /**
     * @param  list<string>  $terms
     */
    private function formattedTerms(array $terms): string
    {
        return implode(', ', array_map(
            static fn (string $term): string => sprintf('`%s`', $term),
            $terms,
        ));
    }

    /**
     * @param  list<string>  $needles
     */
    private function matchesAny(string $haystack, array $needles): bool
    {
        return array_any($needles, fn ($needle) => str_contains($haystack, (string) $needle));
    }
}
