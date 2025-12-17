<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class LlamaPlanner
{
    /**
     * Ask a local llama server for a step-by-step plan.
     *
     * @return array<int, array{tool: string, args: array<string, mixed>}>
     */
    public function plan(string $goal): array
    {
        $endpoint = Config::get('agent.llama_endpoint');
        $model = Config::get('agent.llama_model');
        $timeout = (int) Config::get('agent.llama_timeout', 10);

        if (! $endpoint) {
            throw new RuntimeException('LLAMA_ENDPOINT is not configured.');
        }

        try {
            $response = Http::timeout($timeout)->post($endpoint, [
                'model' => $model,
                'prompt' => $this->buildPrompt($goal),
                'temperature' => 0.3,
                'max_tokens' => 256,
            ])->json();

            $text = Arr::get($response, 'choices.0.text', '');
            $steps = $this->parsePlan((string) $text);

            return $steps;
        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Failed to call LLaMA planner: '.$e->getMessage(),
                previous: $e
            );
        }
    }

    protected function buildPrompt(string $goal): string
    {
        return <<<PROMPT
You are a concise planner for a local MCP agent. Propose a short sequence of tool calls with arguments.

Available tools:
- list-files(path="notes", extension="md"): list files in a directory; set extension to "" for all files.
- search-markdown(path="notes", query=""): search .md files for text and return previews.
- summarize-files(path="notes", extension="md"): read files and summarize their contents using the local LLaMA server.
- write-summary(content, path="storage/app/agent-output.md"): write a summary to disk.

Goal: "{$goal}"

Return ONLY valid JSON (no code fences, no prose) in the following shape:
{
  "steps": [
    {"tool": "list-files", "args": {"path": "notes", "extension": "md"}},
    {"tool": "search-markdown", "args": {"path": "notes", "query": "context"}},
    {"tool": "summarize-files", "args": {"path": "notes", "extension": "md"}},
    {"tool": "write-summary", "args": {"path": "storage/app/agent-output.md"}}
  ]
}
PROMPT;
    }

    /**
     * @return array<int, array{tool: string, args: array<string, mixed>}>
     */
    protected function parsePlan(string $text): array
    {
        $json = $this->extractJson($text);
        $decoded = json_decode($json, true);

        if (! is_array($decoded)) {
            throw new RuntimeException('Planner response was not valid JSON.');
        }

        $steps = $decoded['steps'] ?? null;

        if (! is_array($steps) || $steps === []) {
            throw new RuntimeException('Planner returned an empty or invalid steps array.');
        }

        return collect($steps)->map(function (mixed $step, int $index): array {
            if (! is_array($step)) {
                throw new RuntimeException("Planner step {$index} is not an object.");
            }

            $tool = $step['tool'] ?? null;
            if (! is_string($tool) || $tool === '') {
                throw new RuntimeException("Planner step {$index} is missing a tool name.");
            }

            $args = $step['args'] ?? [];
            if (! is_array($args)) {
                throw new RuntimeException("Planner step {$index} args must be an object.");
            }

            return [
                'tool' => $tool,
                'args' => $args,
            ];
        })->values()->all();
    }

    protected function extractJson(string $text): string
    {
        $stripped = str_replace(['```json', '```'], '', $text);
        $start = strpos($stripped, '{');
        $end = strrpos($stripped, '}');

        if ($start === false || $end === false || $end < $start) {
            throw new RuntimeException('Planner response did not contain JSON.');
        }

        return substr($stripped, $start, $end - $start + 1);
    }
}
