<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\TaskRunner;
use Illuminate\Console\Command;

class RunAgentCommand extends Command
{
    protected $signature = 'agent:run {goal : High-level goal for the agent} {--path=notes : Directory with markdown notes}';

    protected $description = 'Run the MCP-powered task runner against local markdown notes.';

    public function handle(TaskRunner $runner): int
    {
        $goal = (string) $this->argument('goal');
        $path = (string) $this->option('path');

        $summary = $runner->run($goal, $path);

        $this->info($summary);
        $this->newLine();
        $this->comment('Saved to storage/app/agent-output.md');

        return self::SUCCESS;
    }
}
