<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Services\TaskRunner;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TaskRunnerTest extends TestCase
{
    public function test_agent_summarizes_markdown_and_writes_file(): void
    {
        $notesPath = base_path('storage/framework/testing/notes');
        $planJson = json_encode([
            'steps' => [
                ['tool' => 'list-files', 'args' => ['path' => $notesPath, 'extension' => 'md']],
                ['tool' => 'search-markdown', 'args' => ['path' => $notesPath, 'query' => 'weekly']],
                ['tool' => 'write-summary', 'args' => ['path' => 'storage/app/agent-output.md']],
            ],
        ]);

        Config::set('agent.llama_endpoint', 'http://llama.test/v1/completions');
        Http::fake([
            'http://llama.test/*' => Http::response([
                'choices' => [
                    ['text' => $planJson],
                ],
            ]),
        ]);

        File::ensureDirectoryExists($notesPath);
        File::put($notesPath.'/monday.md', "# Monday\nShip auth page\nFix onboarding bugs");
        File::put($notesPath.'/tuesday.md', "# Tuesday\nPlan sprint\nWrite retrospective notes");

        $runner = $this->app->make(TaskRunner::class);

        $summary = $runner->run('Summarize weekly notes and build an action plan', $notesPath);

        $this->assertStringContainsString('Task Summary', $summary);
        $this->assertStringContainsString('Action Plan', $summary);
        $this->assertStringContainsString('Planned steps', $summary);
        $this->assertStringContainsString('list-files', $summary);
        $this->assertFileExists(base_path('storage/app/agent-output.md'));

        File::deleteDirectory($notesPath);
        File::delete(base_path('storage/app/agent-output.md'));
    }
}
