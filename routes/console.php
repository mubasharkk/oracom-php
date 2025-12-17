<?php

use App\Console\Commands\RunAgentCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Console\Application as ArtisanApplication;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

ArtisanApplication::starting(function ($artisan): void {
    $artisan->resolveCommands([
        RunAgentCommand::class,
    ]);
});
