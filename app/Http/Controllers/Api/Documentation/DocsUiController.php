<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Documentation;

use App\Http\Controllers\Controller;
use App\Support\ApiDocumentation\ApiDocumentationConfigFactory;
use App\Support\ApiDocumentation\ReconnectCachedDatabaseConnections;
use Dedoc\Scramble\Generator;
use Illuminate\Contracts\View\View;

class DocsUiController extends Controller
{
    public function __invoke(
        Generator $generator,
        ReconnectCachedDatabaseConnections $reconnectCachedDatabaseConnections,
        ApiDocumentationConfigFactory $configFactory,
    ): View {
        $reconnectCachedDatabaseConnections();

        $config = $configFactory->make();

        return view()->file(base_path('vendor/dedoc/scramble/resources/views/docs.blade.php'), [
            'spec' => $generator($config),
            'config' => $config,
        ]);
    }
}
