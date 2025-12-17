# Oracom PHP (Laravel MCP Agent)

A Laravel-powered MCP agent that can list files, search markdown content, and write summaries through a custom MCP server (`App\Mcp\Servers\TaskServer`) and tools under `App\Mcp\Tools`. A lightweight planner (`App\Services\LlamaPlanner`) can call an external LLaMA-compatible endpoint to orchestrate tasks, and results are saved to `storage/app/agent-output.md`.

## Prerequisites

- PHP 8.2+ with `composer`
- Node.js 18+ with `npm`
- SQLite (default) or another database supported by Laravel

## Quick start

```bash
git clone https://github.com/mubasharkk/oracom-php.git
cd oracom-php/app
cp .env.example .env
composer install
npm install
php artisan key:generate
php artisan storage:link
php artisan migrate
```

## Configuration

- Set database credentials in `.env` (defaults target SQLite).
- Configure the planner endpoint if you want LLaMA planning:
  - `LLAMA_ENDPOINT` (default `http://localhost:11434/v1/completions`)
  - `LLAMA_MODEL` (model name on your endpoint)
  - `LLAMA_TIMEOUT` (seconds, optional)
- To connect an MCP-compatible UI client, use the following client config:

```json
{
  "mcpServers": {
    "laravel-taskrunner": {
      "command": "npx",
      "args": ["-y", "mcp-remote", "http://localhost/mcp/task"]
    }
  }
}
```

## Running the app

- Serve API/UI: `php artisan serve`
- Frontend assets (dev): `npm run dev`
- Run the MCP agent CLI:

```bash
php artisan agent:run "Summarize all .md files in ./notes and generate an action plan." --path=notes
```

The agent prints output to the console and writes `storage/app/agent-output.md`.

## Testing

```bash
php artisan test
```
