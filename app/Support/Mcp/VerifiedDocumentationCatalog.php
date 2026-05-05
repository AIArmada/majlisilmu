<?php

declare(strict_types=1);

namespace App\Support\Mcp;

use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * @phpstan-type DocumentationRecord array{
 *   id: string,
 *   title: string,
 *   description: string,
 *   resource_uri: string,
 *   url: string,
 *   relative_path: string,
 *   mime_type: string
 * }
 */
class VerifiedDocumentationCatalog
{
    /**
     * @return list<DocumentationRecord>
     */
    public function all(): array
    {
        return array_values(array_filter([
            [
                'id' => 'docs-admin-mcp-guide',
                'title' => 'MajlisIlmu Admin MCP Agent Guide',
                'description' => 'Verified guide for admin MCP auth, transport rules, discovery primitives, capability matrix, writable resources, and workflow guidance.',
                'resource_uri' => 'file://docs/MAJLISILMU_MCP_ADMIN_AGENT_GUIDE.md',
                'url' => 'file://docs/MAJLISILMU_MCP_ADMIN_AGENT_GUIDE.md',
                'relative_path' => 'docs/MAJLISILMU_MCP_ADMIN_AGENT_GUIDE.md',
                'mime_type' => 'text/markdown',
            ],
            [
                'id' => 'docs-admin-event-csv-json-create-guide',
                'title' => 'MajlisIlmu MCP CSV / JSON Event Creation Playbook',
                'description' => 'Verified workflow for creating events from CSV or JSON payloads through admin MCP tools, including correction handling, entity resolution, duplicate checks, and chunked validate-then-create execution.',
                'resource_uri' => 'file://docs/MAJLISILMU_MCP_EVENT_CSV_JSON_CREATION_GUIDE.md',
                'url' => 'file://docs/MAJLISILMU_MCP_EVENT_CSV_JSON_CREATION_GUIDE.md',
                'relative_path' => 'docs/MAJLISILMU_MCP_EVENT_CSV_JSON_CREATION_GUIDE.md',
                'mime_type' => 'text/markdown',
            ],
        ], fn (array $document): bool => is_file(base_path($document['relative_path']))));
    }

    public function hasAnyDocuments(): bool
    {
        return $this->all() !== [];
    }

    public function has(string $id): bool
    {
        return $this->find($id) !== null;
    }

    /**
     * @return DocumentationRecord|null
     */
    public function find(string $id): ?array
    {
        foreach ($this->all() as $document) {
            if ($document['id'] === $id) {
                return $document;
            }
        }

        return null;
    }

    /**
     * @return array{results: list<array{id: string, title: string, url: string}>}
     */
    public function search(string $query): array
    {
        $normalizedQuery = Str::lower(trim($query));

        $results = collect($this->all())
            ->map(function (array $document) use ($normalizedQuery): ?array {
                $contents = $this->readContents($document);

                if ($contents === null) {
                    return null;
                }

                $score = $this->score($normalizedQuery, $document, $contents);

                if ($score <= 0) {
                    return null;
                }

                return [
                    'score' => $score,
                    'result' => [
                        'id' => $document['id'],
                        'title' => $document['title'],
                        'url' => $document['url'],
                    ],
                ];
            })
            ->filter()
            ->sortByDesc('score')
            ->pluck('result')
            ->values()
            ->all();

        return [
            'results' => $results,
        ];
    }

    /**
     * @return array{id: string, title: string, text: string, url: string, metadata: array<string, string>}|null
     */
    public function fetch(string $id): ?array
    {
        $document = $this->find($id);

        if ($document === null) {
            return null;
        }

        $contents = $this->readContents($document);

        if ($contents === null) {
            return null;
        }

        return [
            'id' => $document['id'],
            'title' => $document['title'],
            'text' => $contents,
            'url' => $document['url'],
            'metadata' => [
                'description' => $document['description'],
                'mime_type' => $document['mime_type'],
                'resource_uri' => $document['resource_uri'],
                'relative_path' => $document['relative_path'],
                'last_modified' => CarbonImmutable::createFromTimestampUTC(filemtime(base_path($document['relative_path'])) ?: time())->toIso8601String(),
            ],
        ];
    }

    /**
     * @param  DocumentationRecord  $document
     */
    private function readContents(array $document): ?string
    {
        $contents = file_get_contents(base_path($document['relative_path']));

        return is_string($contents) ? $contents : null;
    }

    /**
     * @param  DocumentationRecord  $document
     */
    private function score(string $normalizedQuery, array $document, string $contents): int
    {
        if ($normalizedQuery === '') {
            return 1;
        }

        $score = 0;
        $title = Str::lower($document['title']);
        $description = Str::lower($document['description']);
        $identifier = Str::lower($document['id']);
        $body = Str::lower($contents);

        if (str_contains($title, $normalizedQuery)) {
            $score += 100;
        }

        if (str_contains($description, $normalizedQuery)) {
            $score += 50;
        }

        if (str_contains($identifier, $normalizedQuery)) {
            $score += 40;
        }

        if (str_contains($body, $normalizedQuery)) {
            $score += 20;
        }

        $tokens = array_values(array_filter(array_unique(preg_split('/\s+/', $normalizedQuery) ?: [])));

        foreach ($tokens as $token) {
            if (mb_strlen($token) < 2) {
                continue;
            }

            if (str_contains($title, $token)) {
                $score += 25;
            }

            if (str_contains($description, $token)) {
                $score += 10;
            }

            if (str_contains($body, $token)) {
                $score += 5;
            }
        }

        return $score;
    }
}
