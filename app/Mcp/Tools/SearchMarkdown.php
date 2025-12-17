<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Request;

class SearchMarkdown extends Tool
{
    protected string $description = 'Scan markdown files for a query and return short previews.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string('path')
                ->description('Directory containing markdown notes')
                ->default('notes'),
            'query' => $schema->string('query')
                ->description('Case-insensitive search text')
                ->default(''),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $path = (string) $request->get('path', 'notes');
        $query = (string) $request->get('query', '');
        $directory = $this->normalizePath($path);

        if (! is_dir($directory)) {
            return Response::structured([
                'path' => $directory,
                'query' => $query,
                'results' => [],
                'warning' => 'Directory does not exist.',
            ]);
        }

        $needle = Str::lower($query);

        $results = collect(File::glob($directory.'/*.md'))
            ->map(function (string $file) use ($needle): ?array {
                $content = File::get($file);
                $match = $needle === '' || str_contains(Str::lower($content), $needle);

                if (! $match && $needle !== '') {
                    return null;
                }

                return [
                    'file' => basename($file),
                    'path' => $file,
                    'matches' => $match,
                    'preview' => Str::limit(trim($content), 280),
                ];
            })
            ->filter()
            ->values()
            ->all();

        return Response::structured([
            'path' => $directory,
            'query' => $query,
            'results' => $results,
            'count' => count($results),
        ]);
    }

    protected function normalizePath(string $path): string
    {
        if (Str::startsWith($path, ['/'])) {
            return $path;
        }

        return base_path(trim($path, '/'));
    }
}
