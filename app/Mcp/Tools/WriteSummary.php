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

class WriteSummary extends Tool
{
    protected string $description = 'Persist a generated summary to disk so it can be reused.';

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'content' => $schema->string('content')
                ->description('Text to write')
                ->required(),
            'path' => $schema->string('path')
                ->description('Destination path for the summary file')
                ->default('storage/app/agent-output.md'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'path' => $schema->string('path')->description('Full path to the saved summary'),
            'bytes' => $schema->integer('bytes')->description('Number of bytes written'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $content = (string) $request->get('content', '');
        $path = (string) $request->get('path', 'storage/app/agent-output.md');
        $target = $this->normalizePath($path);

        File::ensureDirectoryExists(dirname($target));
        File::put($target, $content);

        return Response::structured([
            'path' => $target,
            'bytes' => strlen($content),
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
