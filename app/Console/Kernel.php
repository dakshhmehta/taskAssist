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
	$schedule->command('app:sync-rc')->cron('0 */2 * * *');
	$schedule->command('make:tasks-schedule')->cron('0 8 * * *');
	$schedule->command('sites:check-ssl')->cron('0 9 * * *');
	$schedule->command('sites:detect')->cron('*/5 * * * *');
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
