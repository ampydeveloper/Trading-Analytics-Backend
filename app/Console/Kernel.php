<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Jobs\StoreZipImages;

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
//        $schedule->command('schedule:run command:getEbayItemsForCardsCron')->dailyAt('02:00'); //CHECK 
//        $schedule->command('schedule:run command:CardsDataComplieCron')->dailyAt('04:00'); //CHECK 
//        $schedule->command('schedule:run command:CompareEbayImagesCron')->dailyAt('04:30'); //CHECK 
//        $schedule->command('schedule:run command:EbayListingEndingAtComplieCron')->dailyAt('04:45'); //CHECK 
//        $schedule->command('schedule:run command:CalculateUserRankCron')->dailyAt('05:00'); //CHECK 
//        $schedule->command('schedule:run command:GetItemAffiliateWebUrlCron')->dailyAt('05:10'); //CHECK 
        
    //Refresh Trender Cache
        $schedule->call('App\Http\Controllers\Api\Ebay\EbayController@getItemsForStatus')
        ->name('add_request_listing_for_all')
        ->withoutOverlapping()
        ->everyMinute();
//        ->cron('0 */2 * * *');

        $schedule->call('App\Http\Controllers\Api\CardController@addRequestListingForAll')
        ->name('add_request_listing_for_all')
        ->withoutOverlapping()
        ->everyMinute();
        
       $schedule->call('App\Http\Controllers\Api\CardController@compareImages')
       ->name('add_request_listing_for_all')
       ->withoutOverlapping()
       ->everyMinute();
    
        
        $schedule->call('App\Http\Controllers\Api\CardController@cronForTrender')
        ->name('add_request_listing_for_all')
        ->withoutOverlapping()
        ->dailyAt('08:00');

        //        $schedule->call('App\Http\Controllers\Api\CardController@cronForTrender')->cron('0 */2 * * *');

        // $schedule->command('queue:restart')->everyMinute(); //CHECK
        // $schedule->command('queue:work --stop-when-empty')->withoutOverlapping()->everyMinute(); //CHECK 
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
