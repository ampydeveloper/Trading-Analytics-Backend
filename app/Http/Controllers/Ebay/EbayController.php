<?php
namespace App\Http\Controllers\Ebay;

use App\Http\Controllers\Controller;
use App\Services\EbayService;
use App\Jobs\ProcessEbayItems;
use App\Jobs\ProcessGetEbayItemSpecific;
use App\Jobs\ProcessGetItemAffiliateWebUrl;
use App\Models\Ebay\EbayItems;

class EbayController extends Controller
{
    public static function replaceStr($var, $search, $replace)
    {
        return str_replace($search, $replace, $var);
    }
    public static function ebayItemsCronHandler($card, $pageNumber = 1)
    {
        $keyword = $card->player." ";
        $keyword .= $card->year." ";
        $keyword .= $card->brand." ";
        if($card->rc == 'yes') {
            $keyword .= "RC ";
        }
        $keyword .= $card->grade." ";
        $keyword .= self::replaceStr($card->qualifiers, '"', '')." ";
        if($card->qualifiers2 != null) {$keyword .= self::replaceStr($card->qualifiers2, '"', '')."";}
        if($card->qualifiers3 != null) {$keyword .= self::replaceStr($card->qualifiers3, '"', '')."";}
        if($card->qualifiers4 != null) {$keyword .= self::replaceStr($card->qualifiers4, '"', '')."";}
        if($card->qualifiers5 != null) {$keyword .= self::replaceStr($card->qualifiers5, '"', '')."";}
        if($card->qualifiers6 != null) {$keyword .= self::replaceStr($card->qualifiers6, '"', '')."";}
        if($card->qualifiers7 != null) {$keyword .= self::replaceStr($card->qualifiers7, '"', '')."";}
        if($card->qualifiers8 != null) {$keyword .= self::replaceStr($card->qualifiers8, '"', '')."";}


        if(strlen($keyword) > 349) {
            $keyword = substr($keyword, 0, 340);
        }
        
        $response = EbayService::findItemsByKeywords($keyword, $pageNumber);

        if (isset($response['data'])) {
            ProcessEbayItems::dispatch($response['data'], $card);
            // if(isset($response['pagination'])) {
            //     $res = $response['pagination'];
            //     $pageNo = (int) $res['pageNumber'];
            //     $totalPages = (int) $res['totalPages'];
            //     if($pageNo < $totalPages){
            //         self::ebayItemsCronHandler($card, $pageNo);
            //     }
            // }
        }
    }

    public static function ebayItemsSpecificCronHandler($itemId)
    {
        $response = EbayService::getSingleItemDetails($itemId);
        if (isset($response['data'])) {
            ProcessGetEbayItemSpecific::dispatch($response['data'], $itemId);
        }
    }

    public static function getItemAffiliateWebUrl()
    {
        $items = EbayItems::limit(1)->get();
        foreach ($items as $item) {
            ProcessGetItemAffiliateWebUrl::dispatch($item->itemId);
        }
    }
}
