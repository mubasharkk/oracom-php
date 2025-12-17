<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\LlamaPlanner;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Transport\FakeTransporter;
use Laravel\Mcp\Server\Transport\JsonRpcRequest;
use RuntimeException;

class TaskRunner
{
    public function __construct(
        protected LlamaPlanner   $planner,
    ) {
        //
    }

    public function run(string $goal, ?string $path = null): string
    {
        $steps = $this->planner->plan($goal);
        $directory = $this->normalizePath($path ?? $this->guessPath($goal));

        if ($steps === []) {
            throw new RuntimeException('Planner returned no steps.');
        }

        $results = collect();
        $summary = '';

        foreach ($steps as $step) {
            $tool = $step['tool'] ?? null;
            $args = $step['args'] ?? [];

            if (! is_string($tool) || $tool === '') {
                throw new RuntimeException('Planner step is missing tool name.');
            }

            $toolClass = match ($tool) {
                'list-files' => \App\Mcp\Tools\ListFiles::class,
                'search-markdown' => \App\Mcp\Tools\SearchMarkdown::class,
                'summarize-files' => \App\Mcp\Tools\SummarizeFiles::class,
                'write-summary' =>\App\Mcp\Tools\WriteSummary::class,
                default => throw new RuntimeException("Unknown tool in plan: {$tool}"),
            };

            $structuredKey = match ($tool) {
                'list-files' => 'files',
                'search-markdown' => 'results',
                'summarize-files' => 'summary',
                default => null,
            };

            $args['path'] = $this->normalizePath($args['path'] ?? $directory);
            if ($tool === 'search-markdown') {
                $args['query'] = (string) ($args['query'] ?? $this->guessQuery($goal));
            }
            if ($tool === 'summarize-files') {
                $args['extension'] = (string) ($args['extension'] ?? 'md');
            }

            $result = $this->callTool($toolClass, $args, $structuredKey);

            if ($tool === 'list-files') {
                $files = $result;
                if ($files === [] || count($files) === 0) {
                    throw new RuntimeException("No files found in {$args['path']}.");
                }
            }

            if ($tool === 'search-markdown') {
                $results = collect($result);
                $summary = $this->summarize($goal, $directory, $results);
            }

            if ($tool === 'summarize-files') {
                $summary = is_string($result) ? $result : (string) Arr::get($result, 'summary', '');
            }

            if ($tool === 'write-summary') {
                if ($summary === '') {
                    $summary = $this->summarize($goal, $directory, $results);
                }
                $args['content'] = (string) ($args['content'] ?? $summary);
                $this->callTool($toolClass, $args);
            }
        }

        if ($summary === '') {
            $summary = 'Plan executed, but no summary was generated.';
        }

        return $summary."\n\nPlanned steps:\n- ".implode("\n- ", $this->formatPlannedSteps($steps));
    }

    protected function summarize(string $goal, string $directory, Collection $results): string
    {
        $lines = [
            '# Task Summary',
            "Goal: {$goal}",
            "Directory: {$directory}",
            '',
            '## Findings',
        ];

        foreach ($results as $result) {
            $lines[] = "- {$result['file']}: ".($result['preview'] ?? '');
        }

        if ($results->isEmpty()) {
            $lines[] = '- No matches found.';
        }

        $lines[] = '';
        $lines[] = '## Action Plan';

        $plan = $results->take(3)->map(
            fn (array $result): string => "- Review {$result['file']} for next steps."
        );

        $plan = $plan->isEmpty() ? collect(['- Add new notes to get started.']) : $plan;

        return implode(PHP_EOL, [
            ...$lines,
            ...$plan->all(),
            '- Capture outcomes in storage/app/agent-output.md',
        ]);
    }

    protected function extract(ResponseFactory $response, string $key): array
    {
        $structured = $response->getStructuredContent() ?? [];

        return Arr::get($structured, $key, []);
    }

    protected function guessPath(string $goal): string
    {
        preg_match('/\\.\\/[^\\s]+|\\s([A-Za-z0-9_\\-\\/]+notes)/', $goal, $matches);

        $path = $matches[0] ?? 'notes';

        return trim($path);
    }

    protected function normalizePath(string $path): string
    {
        if (Str::startsWith($path, ['/'])) {
            return $path;
        }

        return base_path(trim($path, '/'));
    }

    protected function guessQuery(string $goal): string
    {
        return Str::of($goal)
            ->replaceMatches('/summarize|generate|create|plan/i', '')
            ->trim()
            ->limit(60)
            ->toString();
    }

    /**
     * @param  array<int, array{tool: string, args: array<string, mixed>}>  $steps
     * @return array<int, string>
     */
    protected function formatPlannedSteps(array $steps): array
    {
        return array_map(
            fn (array $step): string => "{$step['tool']} ".json_encode($step['args']),
            $steps
        );
    }

    /**
     * Execute an MCP tool through the TaskServer and return structured content.
     *
     * @param  class-string  $toolClass
     */
    protected function callTool(string $toolClass, array $arguments = [], ?string $structuredKey = null): mixed
    {
        $server = app()->make(\App\Mcp\Servers\TaskServer::class, [
            'transport' => new FakeTransporter(),
        ]);

        $server->start();

        /** @var \Laravel\Mcp\Server\Tool $tool */
        $tool = app()->make($toolClass);

        $request = new JsonRpcRequest(
            uniqid(),
            'tools/call',
            [
                ...$tool->toMethodCall(),
                'arguments' => $arguments,
            ],
        );

        $response = (fn () => $this->runMethodHandle($request, $this->createContext()))->call($server);

        $payload = is_iterable($response)
            ? collect(iterator_to_array($response))->first()?->toArray()
            : $response->toArray();

        $structured = Arr::get($payload, 'result.structuredContent', []);

        return $structuredKey ? Arr::get($structured, $structuredKey, []) : $structured;
    }
}
