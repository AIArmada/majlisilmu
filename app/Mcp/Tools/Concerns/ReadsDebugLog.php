<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Concerns;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Facades\File;

trait ReadsDebugLog
{
    /**
     * Read recent log lines filtered to the `mcp.image_upload` namespace.
     *
     * Reads the application Laravel log file, returns only lines that contain
     * the given `filter` string (defaulting to `mcp.image_upload`), and returns
     * at most `lines` of the most recent matching entries.
     *
     * @return array{lines: list<string>, total_matched: int, log_path: string, filter: string, error?: string}
     */
    protected function readFilteredDebugLog(string $filter, int $lines): array
    {
        $logPath = storage_path('logs/laravel.log');

        if (! File::exists($logPath)) {
            return [
                'lines' => [],
                'total_matched' => 0,
                'log_path' => $logPath,
                'filter' => $filter,
            ];
        }

        /** @var list<string> $matched */
        $matched = [];

        $handle = fopen($logPath, 'r');

        if ($handle === false) {
            return [
                'lines' => [],
                'total_matched' => 0,
                'log_path' => $logPath,
                'filter' => $filter,
                'error' => 'Could not open log file (check file permissions).',
            ];
        }

        try {
            while (($line = fgets($handle)) !== false) {
                if (str_contains($line, $filter)) {
                    $matched[] = rtrim($line);
                }
            }
        } finally {
            fclose($handle);
        }

        $totalMatched = count($matched);
        $recent = array_slice($matched, -$lines);

        return [
            'lines' => $recent,
            'total_matched' => $totalMatched,
            'log_path' => $logPath,
            'filter' => $filter,
        ];
    }

    /**
     * @return array<string, Type>
     */
    protected function debugLogInputSchema(JsonSchema $schema): array
    {
        return [
            'filter' => $schema->string()
                ->min(3)
                ->description('String to filter log lines. Must contain at least 3 characters. Defaults to "mcp.image_upload" to show only image upload diagnostics.'),
            'lines' => $schema->integer()
                ->min(1)
                ->max(200)
                ->description('Maximum number of matching log lines to return (most recent first). Defaults to 50.'),
        ];
    }

    /**
     * @return array<string, Type>
     */
    protected function debugLogOutputSchema(JsonSchema $schema): array
    {
        return [
            'lines' => $schema->array($schema->string())->required()->description('Matching log lines in chronological order (most recent entry is last).'),
            'total_matched' => $schema->integer()->required()->description('Total number of log lines matched before truncating to the requested limit.'),
            'log_path' => $schema->string()->required()->description('Absolute path of the log file that was read.'),
            'filter' => $schema->string()->required()->description('Filter string that was applied.'),
            'error' => $schema->string()->description('Present only when the log file could not be opened (e.g. permission denied).'),
        ];
    }
}
