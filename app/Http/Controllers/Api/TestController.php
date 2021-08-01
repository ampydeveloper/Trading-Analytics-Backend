<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Card;
use App\Models\CardDetails;
use App\Models\CardValues;
use App\Models\CardSales;
use App\Models\CardsTotalSx;
use App\Models\CardsSx;
use App\Models\Board;
use App\Models\BoardFollow;
use App\Models\Ebay\EbayItems;
use Carbon\Carbon;
use Validator;
use Illuminate\Support\Facades\Storage;
use DB;
use DateTime;
use DatePeriod;
use DateInterval;
use App\Models\AppSettings;
use App\Imports\CardsImport;
use App\Imports\ListingsImport;
use App\Services\EbayService;
use App\Jobs\ProcessCardsForRetrivingDataFromEbay;
use App\Jobs\ProcessCardsComplieData;
use App\Jobs\CompareEbayImagesWithCardImages;
use App\Jobs\StoreZipImages;
use App\Models\RequestSlab;
use App\Models\RequestListing;
use App\Models\ExcelUploads;
use Excel;
use ZipArchive;
use App\Jobs\ExcelImports;
use App\Models\Ebay\EbayItemSellingStatus;
use App\Models\Ebay\EbayItemListingInfo;
use App\Models\Ebay\EbayItemSellerInfo;
use App\Models\Ebay\EbayItemSpecific;

class TestController extends Controller {

    public function updateSxValueInTable(Request $request) {
        
        
//        dd(CardsSx::where('card_id', 9)->where('date', '2020-12-14')->get()->toArray());

        $cvs = CardSales::orderBy('timestamp', 'DESC')->get()->groupBy(function ($cs) {
                    return Carbon::parse($cs->timestamp)->format('Y-m-d');
                })->map(function ($cs, $timestamp) {
            return [
            'cost' => round((clone $cs)->avg('cost'), 2),
            'timestamp' => $timestamp,
            'quantity' => $cs->map(function ($qty) {
            return (int) $qty->quantity;
            })->sum()
            ];
        });
//        dump(count($cvs));
        foreach ($cvs as $key => $sxData) {
//            dump($key);
            CardsTotalSx::create([
                'date' => $sxData['timestamp'],
                'amount' => $sxData['cost'],
                'quantity' => $sxData['quantity'],
            ]);
        }
        
//        dump('end1');
        $cardIds = CardSales::distinct('card_id')->pluck('card_id');
        foreach ($cardIds as $cardId) {
            $cvs1 = CardSales::where('card_id', $cardId)->orderBy('timestamp', 'DESC')->get()->groupBy(function ($cs) {
                        return Carbon::parse($cs->timestamp)->format('Y-m-d');
                    })->map(function ($cs, $timestamp) {
                return [
                'cost' => round((clone $cs)->avg('cost'), 2),
                'timestamp' => $timestamp,
                'quantity' => $cs->map(function ($qty) {
                return (int) $qty->quantity;
                })->sum()
                ];
            });
//            dump(count($cvs1));
            foreach ($cvs1 as $sxData) {
                CardsSx::create([
                    'card_id' => $cardId,
                    'date' => $sxData['timestamp'],
                    'sx' => $sxData['cost'],
                    'quantity' => $sxData['quantity'],
                ]);
            }
        }
//die('sucess 2');
        $cardIds = CardSales::distinct('card_id')->pluck('card_id');
        $slab_total_sx = 0;
        foreach ($cardIds as $cardId) {
            $cvs = CardsSx::where('card_id', $cardId)->orderBy('date', 'DESC')->first();
            $slab_total_sx = $slab_total_sx + $cvs->sx;
        }
        AppSettings::first()->update(["total_sx_value" => $slab_total_sx]);

        dd('success');
    }

}
