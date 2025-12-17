<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\ListFiles;
use App\Mcp\Tools\SearchMarkdown;
use App\Mcp\Tools\WriteSummary;
use App\Mcp\Tools\SummarizeFiles;
use Laravel\Mcp\Server;

class TaskServer extends Server
{
    protected string $name = 'Mini Agent MCP Server';

    protected string $version = '0.1.0';

    protected string $instructions = <<<'MARKDOWN'
        Lightweight agent that reads markdown notes, searches for relevant details, and writes action plans using MCP tools.
    MARKDOWN;

    protected array $tools = [
        ListFiles::class,
        SearchMarkdown::class,
        WriteSummary::class,
        SummarizeFiles::class,
    ];
}
