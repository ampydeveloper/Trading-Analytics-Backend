<?php

namespace App\Console\Commands;

use App\Http\Controllers\Ebay\EbayController;
use Illuminate\Console\Command;

class GetItemAffiliateWebUrlCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:GetItemAffiliateWebUrlCron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'This command complie data for each card which added from excel';

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
        EbayController::getItemAffiliateWebUrl();
    }
}
