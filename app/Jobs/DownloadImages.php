<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Ebay\EbayItemSpecific;
use Log;
use DB;
use Storage;

class DownloadImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $url;
    protected $itemId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($url,$itemId)
    {
        $this->url = $url;
        $this->itemId = $itemId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $ext = pathinfo($this->url, PATHINFO_EXTENSION);
            $contents = file_get_contents($this->url);
            Storage::put('public/ebay/'.$this->itemId.'.'.$ext, $contents, 'public');
        } catch (\Exception $e) {
            Log::error($e);
        }
    }
}
