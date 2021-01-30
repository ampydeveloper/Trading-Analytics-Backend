<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

/**
 * Class Kernel.
 */
class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        Commands\EbayGetItemsForCardsCron::class,
        Commands\CardsDataComplieCron::class,
        Commands\CompareEbayImagesCron::class,
        Commands\EbayListingEndingAtComplieCron::class,
        Commands\CalculateUserRankCron::class,
        Commands\GetItemAffiliateWebUrlCron::class,
        
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     */
    protected function schedule(Schedule $schedule)
    {
        // $schedule->command('inspire')->hourly();
        $schedule->command('schedule:run command:getEbayItemsForCardsCron')->dailyAt('02:00');
        $schedule->command('schedule:run command:CardsDataComplieCron')->dailyAt('04:00');
        $schedule->command('schedule:run command:CompareEbayImagesCron')->dailyAt('04:30');
        $schedule->command('schedule:run command:EbayListingEndingAtComplieCron')->dailyAt('04:45');
        $schedule->command('schedule:run command:CalculateUserRankCron')->dailyAt('05:00');
        $schedule->command('schedule:run command:GetItemAffiliateWebUrlCron')->dailyAt('05:10');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
