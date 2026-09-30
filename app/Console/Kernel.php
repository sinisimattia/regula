<?php

declare(strict_types=1);

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Insights\Console\CanonCheckCommand;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        //
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        // Dev tooling: it ships with the insights, which are autoloaded only with dev dependencies.
        if (class_exists(CanonCheckCommand::class)) {
            $this->commands[] = CanonCheckCommand::class;
        }

        require base_path('routes/console.php');
    }
}
