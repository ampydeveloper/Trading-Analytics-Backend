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


class ProcessGetEbayItemSpecific implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;
    protected $itemId;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data, $itemId)
    {
        $this->data = $data;
        $this->itemId = $itemId;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (isset($this->data['ItemSpecifics']['NameValueList'])) {
            $this->_createOrUpdate($this->data['ItemSpecifics']['NameValueList']);
        }
    }


    /**
     * For Single Item
     */
    private function _createOrUpdate($data)
    {
        DB::beginTransaction();
        try {
            DB::commit();
            foreach ($data as $item) {
                if ($item['Value'] != "N/A") {
                    $ebaySpecific = EbayItemSpecific::firstOrNew(['itemId' => $this->itemId, 'name' => $item['Name']]);
                    $ebaySpecific->value = $item['Value'];
                    $ebaySpecific->save();
                }
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error($e);
        }
    }
}
