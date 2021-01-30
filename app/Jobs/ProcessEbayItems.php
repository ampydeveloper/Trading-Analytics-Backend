<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

use App\Models\Ebay\EbayItemCategories;
use App\Models\Ebay\EbayItems;
use App\Models\Ebay\EbayItemListingInfo;
use App\Models\Ebay\EbayItemSellingStatus;
use App\Models\Ebay\EbayItemShippingInfo;
use App\Models\Ebay\EbayItemSellerInfo;
use App\Models\Ebay\EbayItemCondition;
use App\Http\Controllers\Ebay\EbayController;
use App\Models\Ebay\EbayItemSellingStatusHistory;
use App\Models\CardDetails;
use App\Models\CardValues;
use Log;
use DB;
use Exception;
use Carbon\Carbon;

class ProcessEbayItems implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $data;
    protected $card;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($data, $card)
    {
        $this->data = $data;
        $this->card = $card;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if ($this->data['@attributes'] > 1) {
            $this->_processEbayItems($this->data['item']);
        } else {
            if(isset($this->data['item']['itemId'])){
                $this->_createEbayItem($this->data['item']);
            }
        }
    }


    /**
     * For Single Item
     */
    private function _createEbayItem($data)
    {
        DB::beginTransaction();
        try {
            $item = EbayItems::where('card_id',$this->card->id)->where('itemId', $data['itemId'])->first();
            $res = $this->_getCreatedOrUpdatedArray($data);
            if($res['status'] == true){
                if (!$item) {
                    if (EbayItems::create($res['data'])) {
                        Log::debug('created item');
                    }
                } else {
                    if ($item->update($res['data'])) {
                        Log::debug('updated item');
                    }
                }
                DB::commit();
                EbayController::ebayItemsSpecificCronHandler($data['itemId']);

                $galleryURL = $res['data']['galleryURL'];
                if ($res['data']['pictureURLLarge'] != null) {
                    $galleryURL = $res['data']['pictureURLLarge'];
                } else if ($res['data']['pictureURLSuperSize'] != null) {
                    $galleryURL = $res['data']['pictureURLSuperSize'];
                }

                if($galleryURL != null) {
                    DownloadImages::dispatch($galleryURL,$res['data']['itemId']);
                }
                
            }else{
                DB::rollBack();
            }
        } catch (\Exception $e) {
            \Log::error($e);
            DB::rollBack();
        }
    }

    /**
     * For Multiple Item
     */
    private function _processEbayItems($items)
    {

        foreach ($items as $key => $item) {
            if(isset($item['itemId']) && $key <=5){
                $this->_createEbayItem($item);
            }
        }
    }

    private function _getCreatedOrUpdatedArray($data)
    {
        $array =  [
            'itemId' => $data['itemId'],
            'card_id' => $this->card->id,
            'title' => $data['title'] ,
            'globalId' => $data['globalId']  ?? null,
            'galleryURL' => $data['galleryURL']  ?? null,
            'viewItemURL' => $data['viewItemURL']  ?? null,
            'paymentMethod' => $data['paymentMethod']  ?? null, 
            'autoPay' => $data['autoPay']  ?? null,
            'postalCode' => $data['postalCode']  ?? null,
            'location' => $data['location']  ?? null,
            'country' => $data['country']  ?? null,
            'returnsAccepted' => $data['returnsAccepted']  ?? null,
            'isMultiVariationListing' => $data['isMultiVariationListing'] ?? null,
            'topRatedListing' => $data['topRatedListing'] ?? null,
            'pictureURLLarge' => $data['pictureURLLarge'] ?? null,
            'pictureURLSuperSize' => $data['pictureURLSuperSize'] ?? null,
        ];
        

        if(isset($data['primaryCategory'])){
            $array['category_id'] = $this->_createEbayItemcategory($data['primaryCategory'],$data['itemId']);
        }

        if(isset($data['sellerInfo'])){
            $array['seller_info_id'] = $this->_createEbayItemSellerInfo($data['sellerInfo'],$data['itemId']);            
        }

        if(isset($data['shippingInfo'])){
            $array['shipping_info_id'] = $this->_createOrUpdateShippingInfo($data['shippingInfo'],$data['itemId']);
        }

        if(isset($data['sellingStatus'])){
            $array['selling_status_id'] = $this->_createOrUpdateSellingStatus($data['sellingStatus'],$data['itemId']);
            $this->_createSellingStatusHistroy($data['sellingStatus'],$data['itemId']);

            // Update card current Price
            if(!(CardDetails::where('card_id', $array['card_id'])->update(['currentPrice' => $data['sellingStatus']['currentPrice']]))){
                throw new Exception("Error Processing Request on creating updating card current price for item id" . $data['itemId'], 1);
            }

            // Save Card's Average Value
            $today = Carbon::now()->format('Y-m-d');
            if(CardValues::where(['card_id' => $array['card_id'], 'date' => $today])->doesntExist()){
                $avg_value = EbayItemSellingStatusHistory::where('itemId', $data['itemId'])->orderBy('created_at', 'DESC')->limit(3)->avg('currentPrice');
                $cardVal = new CardValues();
                $cardVal->card_id = $array['card_id'];
                $cardVal->date = $today;
                $cardVal->avg_value = $avg_value;
                if(!$cardVal->save()){
                    throw new Exception("Error Processing Request on creating updating card avg price for item id" . $data['itemId'], 1);
                }
            }
        }

        if(isset($data['listingInfo'])){
            $array['listing_info_id'] = $this->_createOrUpdateListingInfo($data['listingInfo'],$data['itemId']);
        }

        if(isset($data['condition'])){
            $array['condition_id'] = $this->_createOrUpdateCondition($data['condition'],$data['itemId']);
        }


        return ['status'=>true, 'data'=>$array];
         
    }

    private function _createEbayItemcategory($category,$itemId)
    {
        $object = EbayItemCategories::where('categoryId', $category['categoryId'])->first();
        if ($object == null) {
            $object = EbayItemCategories::create([
                'categoryId' => $category['categoryId'] ,
                'name' => $category['categoryName'],
            ]);
            return $object->id;
        }else{
            return $object->id;
        }

        throw new Exception("Error Processing Request on creating updating item category for item ".$itemId, 1);
    }

    private function _createEbayItemSellerInfo($sellerInfo,$itemId)
    {
        $object = EbayItemSellerInfo::where('itemId',$itemId)->first();
        if($object==null){
            $object = new EbayItemSellerInfo();
            $object->itemId = $itemId;
            $object->sellerUserName = $sellerInfo['sellerUserName'] ?? null;
            $object->feedbackScore = $sellerInfo['feedbackScore'] ?? null;
            $object->positiveFeedbackPercent = $sellerInfo['positiveFeedbackPercent'] ?? null;
            $object->feedbackRatingStar = $sellerInfo['feedbackRatingStar'] ?? null;
            $object->topRatedSeller = $sellerInfo['topRatedSeller'] ?? null;
            if($object->save()){
                return $object->id; 
            }
        }else{
            $dataArray = [
                'sellerUserName' => $sellerInfo['sellerUserName']  ?? null,
                'feedbackScore' => $sellerInfo['feedbackScore']  ?? null,
                'positiveFeedbackPercent' => $sellerInfo['positiveFeedbackPercent']  ?? null,
                'feedbackRatingStar' => $sellerInfo['feedbackRatingStar']  ?? null,
                'topRatedSeller' => $sellerInfo['topRatedSeller']  ?? null,
            ];
            if($object->update($dataArray)) {
                return $object->id;
            }
        }
        throw new Exception("Error Processing Request on creating updating seller info for item id".$itemId, 1);
    }

    private function _createOrUpdateShippingInfo($shippingInfo,$itemId)
    {
        $object = EbayItemShippingInfo::where('itemId',$itemId)->first();
        if($object==null){
            $object = new EbayItemShippingInfo();
            $object->itemId = $itemId;
            $object->shippingServiceCost = $shippingInfo['shippingServiceCost'] ?? null;
            $object->shippingType = $shippingInfo['shippingType']  ?? null;
            $object->shipToLocations = $shippingInfo['shipToLocations']  ?? null;
            $object->expeditedShipping = $shippingInfo['expeditedShipping']  ?? null;
            $object->oneDayShippingAvailable = $shippingInfo['oneDayShippingAvailable']  ?? null;
            $object->handlingTime = $shippingInfo['handlingTime']  ?? null;
            if($object->save()){
                return $object->id; 
            }
        }else{
            $dataArray = [
                'shippingServiceCost' => $shippingInfo['shippingServiceCost']  ?? null,
                'shippingType' => $shippingInfo['shippingType']  ?? null, 
                'shipToLocations' => $shippingInfo['shipToLocations']  ?? null,
                'expeditedShipping' => $shippingInfo['expeditedShipping']  ?? null,
                'oneDayShippingAvailable' => $shippingInfo['oneDayShippingAvailable']  ?? null,
                'handlingTime' => $shippingInfo['handlingTime']  ?? null,
            ];
            if($object->update($dataArray)) {
                return $object->id;
            }
        }
        throw new Exception("Error Processing Request on creating updating shipping info for item id".$itemId, 1);
    }

    private function _createOrUpdateSellingStatus($sellingStatus,$itemId)
    {
        $object = EbayItemSellingStatus::where('itemId',$itemId)->first();
        if($object==null){
            $object = new EbayItemSellingStatus();
            $object->itemId = $itemId;
            $object->currentPrice = $sellingStatus['currentPrice']  ?? null;
            $object->convertedCurrentPrice = $sellingStatus['convertedCurrentPrice']  ?? null;
            $object->sellingState = $sellingStatus['sellingState']  ?? null;
            $object->timeLeft = $sellingStatus['timeLeft']  ?? null;
            if($object->save()){
                return $object->id; 
            }
        }else{
            $dataArray = [
                'currentPrice' => $sellingStatus['currentPrice']  ?? null,
                'convertedCurrentPrice' => $sellingStatus['convertedCurrentPrice']  ?? null,
                'sellingState' => $sellingStatus['sellingState']  ?? null,
                'timeLeft' => $sellingStatus['timeLeft']  ?? null,
            ];
            if($object->update($dataArray)) {
                return $object->id;
            }
        }
        throw new Exception("Error Processing Request on creating updating selling status for item id".$itemId, 1);
    }

    private function _createSellingStatusHistroy($sellingStatus,$itemId)
    {
        $object = new EbayItemSellingStatusHistory();
        $object->itemId = $itemId;
        $object->currentPrice = $sellingStatus['currentPrice']  ?? null;
        $object->convertedCurrentPrice = $sellingStatus['convertedCurrentPrice']  ?? null;
        $object->sellingState = $sellingStatus['sellingState']  ?? null;
        $object->timeLeft = $sellingStatus['timeLeft']  ?? null;
        if(!$object->save()){
            \Log::error("Error Processing Request on creating selling status histroy for item id ".$itemId); 
        }
    }

    private function _createOrUpdateListingInfo($listingInfo,$itemId)
    {
        $object = EbayItemListingInfo::where('itemId',$itemId)->first();
        if($object==null){
            $object = new EbayItemListingInfo();
            $object->itemId = $itemId;
            $object->bestOfferEnabled = $listingInfo['bestOfferEnabled']  ?? null;
            $object->buyItNowAvailable = $listingInfo['buyItNowAvailable']  ?? null;
            $object->startTime = $listingInfo['startTime']  ?? null;
            $object->endTime = $listingInfo['endTime']  ?? null;
            $object->listingType = $listingInfo['listingType']  ?? null;
            $object->gift = $listingInfo['gift']  ?? null;
            $object->watchCount = $listingInfo['watchCount']  ?? null;
            if($object->save()){
                return $object->id; 
            }
        }else{
            $dataArray = [
                'bestOfferEnabled' => $listingInfo['bestOfferEnabled']  ?? null,
                'buyItNowAvailable' => $listingInfo['buyItNowAvailable']  ?? null,
                'startTime' => $listingInfo['startTime']  ?? null,
                'endTime' => $listingInfo['endTime']  ?? null,
                'listingType' => $listingInfo['listingType']  ?? null,
                'gift' => $listingInfo['gift']  ?? null,
                'watchCount' => $listingInfo['watchCount']  ?? null,
            ];
            if($object->update($dataArray)) {
                return $object->id;
            }
        }
        throw new Exception("Error Processing Request on creating updating listing info for item id".$itemId, 1);
    }

    private function _createOrUpdateCondition($condition,$itemId)
    {
        $object = EbayItemCondition::where('itemId',$itemId)->first();
        if($object==null){
            $object = new EbayItemCondition();
            $object->itemId = $itemId;
            $object->conditionId = $condition['conditionId']  ?? null;
            $object->conditionDisplayName = $condition['conditionDisplayName']  ?? null;
            if($object->save()){
                return $object->id; 
            }
        }else{
            $dataArray = [
                'conditionId' => $condition['conditionId']  ?? null,
                'conditionDisplayName' => $condition['conditionDisplayName']  ?? null
            ];
            if($object->update($dataArray)) {
                return $object->id;
            }
        }
        throw new Exception("Error Processing Request on creating updating item condition for item id".$itemId, 1);
    }
}
