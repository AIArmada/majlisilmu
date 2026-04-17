<?php

declare(strict_types=1);

namespace App\Support\ApiDocumentation;

use Dedoc\Scramble\GeneratorConfig;
use Dedoc\Scramble\Scramble;
use Illuminate\Routing\Route as IlluminateRoute;
use Illuminate\Support\Str;

class ApiDocumentationConfigFactory
{
    public function __construct(
        private readonly ApiDocumentationUrlResolver $urlResolver,
    ) {}

    public function make(): GeneratorConfig
    {
        $apiPath = trim((string) config('scramble.api_path', 'api/v1'), '/');
        $config = config('scramble');
        $config['info']['description'] = $this->docsDescription(
            base_path('docs/MAJLISILMU_MOBILE_API_REFERENCE.md'),
            (string) ($config['info']['description'] ?? ''),
        );

        return Scramble::configure()
            ->useConfig($config)
            ->withOperationTransformers([
                ApiSecurityRequirementExtension::class,
                ApiRequestBodyExamplesExtension::class,
            ])
            ->withDocumentTransformers([
                PublicDirectorySchemasTransformer::class,
                ApiWorkflowSchemasTransformer::class,
            ])
            ->routes(static fn (IlluminateRoute $route): bool => $apiPath === '' || Str::startsWith($route->uri(), $apiPath));
    }

    private function docsDescription(string $mobileRefPath, string $baseDescription): string
    {
        $description = trim($baseDescription);
        $description .= "\n\n---\n\n".$this->aiQuickstartMarkdown();

        if (file_exists($mobileRefPath)) {
            $mobileRefContent = file_get_contents($mobileRefPath);

            if ($mobileRefContent !== false) {
                $description .= "\n\n---\n\n".trim($mobileRefContent);
            }
        }

        return $description;
    }

    private function aiQuickstartMarkdown(): string
    {
        $apiBaseUrl = $this->urlResolver->apiBaseUrl();
        $publicManifestUrl = $apiBaseUrl.'/manifest';
        $adminManifestUrl = $apiBaseUrl.'/admin/manifest';

        return <<<MD
AI QUICKSTART:

1. Read {$this->urlResolver->docsJsonUrl()} first for the complete OpenAPI contract.
2. Use {$this->urlResolver->docsUrl()} for the human-readable overview and integration notes.
3. For public and client workflows, discover the live contract at {$publicManifestUrl}.
4. For admin workflows, discover writable resources at {$adminManifestUrl}.
5. Before any write, fetch the exact schema first: `GET /forms/*` on the public surface, or `GET /admin/{resourceKey}/schema` on the admin surface.
6. For admin record-specific schema and mutation paths, use the admin record `route_key` returned by collection or record endpoints. The legacy `id` remains accepted as a compatibility fallback.
7. Treat `error.code` as the machine-readable failure type and `meta.request_id` as the trace identifier.
MD;
    }
}
