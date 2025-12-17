<?php

declare(strict_types=1);

namespace App\Mcp\Tools;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Request;
use RuntimeException;

class SummarizeFiles extends Tool
{
    protected string $description = 'Read files and generate a concise summary using the local LLaMA server.';

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
                ->description('Optional extension filter such as "md"; leave blank for all files')
                ->default('md'),
        ];
    }

    public function handle(Request $request): ResponseFactory
    {
        $path = (string) $request->get('path', 'notes');
        $extension = (string) $request->get('extension', 'md');

        $files = $this->collectFiles($path, $extension);

        if ($files === []) {
            return Response::structured([
                'summary' => 'No files found to summarize.',
                'files' => [],
            ]);
        }

        $summary = $this->summarizeWithLlama($files);

        return Response::structured([
            'files' => $files,
            'summary' => $summary,
        ]);
    }

    /**
     * @return array<int, array{path: string, content: string}>
     */
    protected function collectFiles(string $path, string $extension): array
    {
        $directory = $this->normalizePath($path);

        if (! is_dir($directory)) {
            return [];
        }

        return collect(File::files($directory))
            ->filter(function (\SplFileInfo $file) use ($extension): bool {
                if ($extension === '' || $extension === '*') {
                    return true;
                }

                return strtolower($file->getExtension()) === strtolower(ltrim($extension, '.'));
            })
            ->map(fn (\SplFileInfo $file): array => [
                'path' => $file->getPathname(),
                'content' => File::get($file->getPathname()),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{path: string, content: string}>  $files
     */
    protected function summarizeWithLlama(array $files): string
    {
        $endpoint = Config::get('agent.llama_endpoint');
        $model = Config::get('agent.llama_model');
        $timeout = (int) Config::get('agent.llama_timeout', 10);

        if (! $endpoint) {
            throw new RuntimeException('LLAMA_ENDPOINT is not configured.');
        }

        $prompt = $this->buildPrompt($files);

        $response = Http::timeout($timeout)->post($endpoint, [
            'model' => $model,
            'prompt' => $prompt,
            'temperature' => 0.2,
            'max_tokens' => 256,
        ])->json();

        return (string) Arr::get($response, 'choices.0.text', 'No summary generated.');
    }

    /**
     * @param  array<int, array{path: string, content: string}>  $files
     */
    protected function buildPrompt(array $files): string
    {
        $chunks = collect($files)->map(function (array $file): string {
            $preview = Str::limit(trim($file['content']), 1000);
            return "File: {$file['path']}\n{$preview}";
        })->implode("\n\n");

        return <<<PROMPT
Summarize the following files into a concise report capturing all key facts and action items. Be terse and bullet the important points.

{$chunks}
PROMPT;
    }

    protected function normalizePath(string $path): string
    {
        if (Str::startsWith($path, ['/'])) {
            return $path;
        }

        return base_path(trim($path, '/'));
    }
}
