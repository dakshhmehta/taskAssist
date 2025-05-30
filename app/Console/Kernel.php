<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('app:sync-rc')->everyTwoHours();
        $schedule->command('make:tasks-schedule')->dailyAt('08:00');

        $schedule->command('sites:check-ssl')->dailyAt('09:00');
        $schedule->command('sites:detect')->dailyAt('09:30');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
