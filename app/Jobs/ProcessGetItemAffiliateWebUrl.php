<?php

namespace App\Jobs;

use App\Models\Ebay\EbayItems;
use App\Services\EbayService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Log;

class ProcessGetItemAffiliateWebUrl implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $epid;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($epid)
    {
        $this->epid = $epid;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $data = EbayService::getItemAffiliateWebUrl($this->epid);
            Log::debug($data);
            // EbayItems::where('itemId', $this->epid)->update(['itemAffiliateWebUrl', '']);
        } catch (\Exception $e) {
            Log::error($e);
        }
    }
}
