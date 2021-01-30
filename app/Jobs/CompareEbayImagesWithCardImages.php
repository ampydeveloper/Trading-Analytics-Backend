<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Ebay\EbayItems;
use App\Models\Ebay\EbayItemCondition;
use App\Models\Ebay\EbayItemListingInfo;
use App\Models\Ebay\EbayItemSellerInfo;
use App\Models\Ebay\EbayItemSellingStatus;
use App\Models\Ebay\EbayItemSellingStatusHistory;
use App\Models\Ebay\EbayItemShippingInfo;
use App\Models\Ebay\EbayItemSpecific;
use BigV\ImageCompare;
use DB;
use Log;

class CompareEbayImagesWithCardImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $id;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($id)
    {
        $this->id = $id;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            $image = new ImageCompare();
            $items = EbayItems::with('card')->where('card_id',$this->id)->get();
            $itemsIds = [];
            foreach ($items as $item) {
                $card_url = $this->getImageFileName($item->card->row_id, $item->card->sport);
                if($card_url) {
                    $item_url = public_path("storage/ebay/" . $item->itemId . ".jpg");
                    $match = $image->compare($card_url, $item_url);
                    if ($match < 95) {
                        \Log::info('Match less then 80');
                        $itemsIds[] = $item->id;
                    }
                    // if (file_exists($item_url)) {
                    //     \Log::info('Exist'. $item_url);
                    // }else{
                    //     \Log::info('Not Exist'.$item_url);
                    // }
                }else{
                    \Log::info('Not Exist card url');
                }
            }
            if (!empty($itemsIds)) {
                $ebayItems = EbayItems::whereIn('id', $itemsIds)->update(['status'=>1]);
                // DB::beginTransaction();
                // foreach ($ebayItems as $item) {
                //     EbayItems::where('id',$item->id)->update(['status',1]);
                //     $item->delete();
                //     EbayItemCondition::where('itemId', $item->itemId)->delete();    
                //     EbayItemListingInfo::where('itemId', $item->itemId)->delete();    
                //     EbayItemSellerInfo::where('itemId', $item->itemId)->delete();    
                //     EbayItemSellingStatus::where('itemId', $item->itemId)->delete();    
                //     EbayItemSellingStatusHistory::where('itemId', $item->itemId)->delete();    
                //     EbayItemShippingInfo::where('itemId', $item->itemId)->delete();    
                //     EbayItemSpecific::where('itemId', $item->itemId)->delete();  
                // }
                // DB::commit();
            }
        } catch (\Exception $e) {
            // DB::rollBack();
            Log::error($e->getMessage());
        }
    }

    public function getImageFileName($id, $sport)
    {
        $card_url = public_path('storage/'.$sport.'/F' . $id . '.jpg');
        if(file_exists($card_url)){
            return $card_url;
        }else{
            if($id > 0) {
                return $this->getImageFileName(($id-1), $sport);
            }else{
                return false;
            }
        }
    }
}
