<?php

namespace App\Providers;

use App\Mcp\Servers\TaskServer;
use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Facades\Mcp;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Mcp::local('agent', TaskServer::class);
        Mcp::web('mcp/task', TaskServer::class);
    }
}
