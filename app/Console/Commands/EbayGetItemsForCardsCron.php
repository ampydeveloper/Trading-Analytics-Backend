<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Api\CardController;

class EbayGetItemsForCardsCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:getEbayItemsForCardsCron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command get lis of items matching the keyword and store it in our Database';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        CardController::getDataFromEbay();
    }
}
