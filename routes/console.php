<?php

use App\Domain\Orders\Jobs\GenerateDailyKPIsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule daily KPI generation
Schedule::job(new GenerateDailyKPIsJob)
    ->dailyAt('01:00')
    ->onOneServer()
    ->name('generate-daily-kpis')
    ->withoutOverlapping();

// Horizon snapshots for metrics
Schedule::command('horizon:snapshot')
    ->everyFiveMinutes();
