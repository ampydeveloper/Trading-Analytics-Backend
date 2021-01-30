<?php

namespace App\Jobs;

use App\Models\Ebay\EbayItems;
use App\Models\Ebay\EbayItemSellingStatus;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Ebay\EbayItemSpecific;
use Carbon\Carbon;
use Log;

class ProcessGetListingEndingAt implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $ids;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($ids)
    {
        $this->ids = $ids;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            foreach ($this->ids as $id) {
                $array = [
                    'days' => 0,
                    'hours' => 0,
                    'mins' => 0,
                    'secs' => 0,
                ];
                $data = EbayItemSellingStatus::where('id', $id['selling_status_id'])->first();
                if ($data) {
                    $a = explode('P', $data->timeLeft);
                    if (strpos($a[1], 'D') !== false) {
                        $a = explode('D', $a[1]);
                        if (count($a) == 2) {
                            $array['days'] = (int)$a[0];
                        }
                    }
                    if (strpos($a[1], 'T') !== false) {
                        $a = explode('T', $a[1]);
                    }
                    if (strpos($a[1], 'H') !== false) {
                        $a = explode('H', $a[1]);
                        if (count($a) == 2) {
                            $array['hours'] = (int)$a[0];
                        }
                    }
                    if (strpos($a[1], 'M') !== false) {
                        $a = explode('M', $a[1]);
                        if (count($a) == 2) {
                            $array['mins'] = (int)$a[0];
                        }
                    }
                    if (strpos($a[1], 'S') !== false) {
                        $a = explode('S', $a[1]);
                        if (count($a) == 2) {
                            $array['secs'] = (int)$a[0];
                        }
                    }
                    $date = Carbon::parse($data->updated_at);
                    $date->addDays($array['days']);
                    $date->addHours($array['hours']);
                    $date->addMinutes($array['mins']);
                    $date->addSeconds($array['secs']);
                    $ending_at = $date->format('Y-m-d H:i:s');
                    $ebayItem = EbayItems::where('id', $id['id'])->first();
                    if ($ebayItem) {
                        $ebayItem->update(['listing_ending_at' => $ending_at]);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error($e);
        }
    }
}
