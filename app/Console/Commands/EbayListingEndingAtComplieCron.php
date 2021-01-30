<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\ProcessGetListingEndingAt;
use App\Models\Ebay\EbayItems;

class EbayListingEndingAtComplieCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'command:EbayListingEndingAtComplieCron';

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
        $this->_createJobs(1);
    }

    private function _createJobs(int $page)
    {
        try {
            $take = 100;
            $skip = $take * $page;  
            $skip = $skip - $take;
            $items = EbayItems::select('id','selling_status_id')->get();
            $items = $items->skip($skip)->take($take);
            if(count($items) > 0) {
                ProcessGetListingEndingAt::dispatch($items->toArray());
                $this->_createJobs($page+1);
            }else{
                die();
            }
        } catch (\Exception $e)  {
            \Log::error($e);
        }
        
    }
}
