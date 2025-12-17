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

class ListFiles extends Tool
{
    protected string $description = 'List files in a directory, optionally filtered by extension.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string('path')
                ->description('Directory to scan')
                ->default('notes'),
            'extension' => $schema->string('extension')
                ->description('Optional extension filter such as "md" or "txt"; leave blank for all files')
                ->default('md'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $path = (string) $request->get('path', 'notes');
        $extension = (string) $request->get('extension', 'md');
        $directory = $this->normalizePath($path);

        if (! is_dir($directory)) {
            return Response::structured([
                'path' => $directory,
                'files' => [],
                'count' => 0,
                'warning' => 'Directory does not exist.',
            ]);
        }

        $files = collect(File::files($directory))
            ->filter(function (\SplFileInfo $file) use ($extension): bool {
                if ($extension === '' || $extension === '*') {
                    return true;
                }

                return strtolower($file->getExtension()) === strtolower(ltrim($extension, '.'));
            })
            ->map(fn (\SplFileInfo $file): array => [
                'name' => $file->getFilename(),
                'path' => $file->getPathname(),
                'size' => $file->getSize(),
            ])
            ->values()
            ->all();

        return Response::structured([
            'path' => $directory,
            'files' => $files,
            'count' => count($files),
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
