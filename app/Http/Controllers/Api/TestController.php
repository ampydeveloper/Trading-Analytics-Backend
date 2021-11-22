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
use App\Models\Ebay\EbayShortItem;
use App\Models\ExcelUploads;
use Excel;
use ZipArchive;
use App\Jobs\ExcelImports;
use App\Models\Ebay\EbayItemSellingStatus;
use App\Models\Ebay\EbayItemListingInfo;
use App\Models\Ebay\EbayItemSellerInfo;
use App\Models\Ebay\EbayItemSpecific;
use Grafika\Grafika;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Illuminate\Support\Facades\Cache;

class TestController extends Controller {

    public function updateSxValueInTable(Request $request) {

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
        foreach ($cvs as $key => $sxData) {
            CardsTotalSx::create([
                'date' => $sxData['timestamp'],
                'amount' => $sxData['cost'],
                'quantity' => $sxData['quantity'],
            ]);
        }
        die('success 1');

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
            foreach ($cvs1 as $sxData) {
                CardsSx::create([
                    'card_id' => $cardId,
                    'date' => $sxData['timestamp'],
                    'sx' => $sxData['cost'],
                    'quantity' => $sxData['quantity'],
                ]);
            }
        }
        die('success 2');

        $cardIds = CardSales::distinct('card_id')->pluck('card_id');
        $slab_total_sx = 0;
        foreach ($cardIds as $cardId) {
            $cvs = CardsSx::where('card_id', $cardId)->orderBy('date', 'DESC')->first();
            $slab_total_sx = $slab_total_sx + $cvs->sx;
        }
        AppSettings::first()->update(["total_sx_value" => $slab_total_sx]);

        die('success 3');
    }

    //add Request Listing for all if not exists
    public function addRequestListingForAll() {
        try {
            $allListing = RequestListing::get();

            foreach ($allListing as $list) {
                dump($list->id);
                if (!EbayShortItem::where("request_listing_id", $list->id)->exists()) {
                    $script_link = '/home/' . env('SCRAP_USER') . '/ebay_short/ebayFetch/bin/python3 /home/' . env('SCRAP_USER') . '/ebay_short/core.py """' . $list->link . '"""';
                    $scrap_response = shell_exec($script_link . " 2>&1");
                    $response = json_decode($scrap_response);
                    if (!empty($response)) {
                        if (!empty($response->timeLeft)) {
                            date_default_timezone_set("America/Los_Angeles");
                            $auction_end_str = $response->timeLeft / 1000;
                            $auction_end = date('Y-m-d H:i:s', $auction_end_str);
                        }
                        EbayShortItem::create([
                            'request_listing_id' => $list->id,
                            'title' => $response->name,
                            'ebay_id' => $response->ebay_id,
                            'price' => $response->price,
                            'image' => $response->image,
                            'timeLeft' => isset($auction_end) ? $auction_end : "",
                            'specifics' => json_encode($response->specifics),
                        ]);
                    }
                }
            }
            die("end");
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function compareImages() {
        // $process = new Process(['python3', '/home/ubuntu/ebay_short/textextract.py','100.jpg']);
//        $process = new Process(["/home/" . env('SCRAP_USER') . "/ebay_short/ebayFetch/bin/pip3", 'list']);
//        $process->run();
//        $process2 = new Process(["/home/" . env('SCRAP_USER') . "/ebay/ebayFetch/bin/pip3", 'list']);
//        $process2->run();
//
//        // executes after the command finishes
//        if (!$process->isSuccessful()) {
//            throw new ProcessFailedException($process);
//        }
//
//        dump($process->getOutput());
//        dd($process2->getOutput());


//        $name = "F157.jpg";
        // $script_link = '/home/' . env('SCRAP_USER') . "/ebay_short/";
        // $script_link = '/home/' . env('SCRAP_USER') . '/ebay_short/ebayFetch/bin/python3 /home/' . env('SCRAP_USER') . "/ebay_short/textextract.py 100.jpg";
        // $scrap_response = shell_exec($script_link);
//        $scrap_response = shell_exec("/home/" . env('SCRAP_USER') . "/ebay_short/ebayFetch/bin/python3 /home/" . env('SCRAP_USER') . "/ebay_short/textextract.py 100.jpg 2>&1");
        $scrap_response = shell_exec("/home/" . env('SCRAP_USER') . "/ebay_image_compare/ebayFetch/bin/python3 /home/" . env('SCRAP_USER') . "/ebay_image_compare/textextract.py /home/ubuntu/ebay_image_compare/100.jpg 2>&1");
//        dd("/home/" . env('SCRAP_USER') . "/ebay_image_compare/ebayFetch/bin/python3 /home/" . env('SCRAP_USER') . "/ebay_image_compare/textextract.py 100.jpg 2>&1");
        dd($scrap_response);
        // $script_link = '/home/' . env('SCRAP_USER') . '/ebay_short/ebayFetch/bin/python3 /home/' . env('SCRAP_USER') . '/ebay_short/textextract.py';
        // // $script_link = '/home/' . env('SCRAP_USER') . '/ebay_short/ebayFetch/bin/python3 /home/' . env('SCRAP_USER') . '/ebay_short/core.py """'.$link.'"""';
        // $scrap_response = shell_exec($script_link . " 2>&1");
        dump($script_link);
        $scrap_response = shell_exec($script_link);
        dd($scrap_response);

        exec('python3 compareImg.py /home/ubuntu/ebay_short/', $output);
        dd($output);

        $process = new Process(['python3 compareImg.py', '/home/ubuntu/ebay_short/']);
        $process->run();
        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
        // echo $process->getOutput();

        dd($process->getOutput());

        $scrap_response = shell_exec("python3 compareImg.py /home/ubuntu/ebay_short/");
        dump($scrap_response);
        $response = json_decode($scrap_response);

        dd($response);

        $allListing = RequestListing::with("cardForMatch", "ebayShortItem")->take(51)->get();
        $total = 6;
        foreach ($allListing as $listing) {
            $count = 0;
            if (!empty($listing->cardForMatch) && !empty($listing->ebayShortItem)) {

                if (!empty($listing->cardForMatch->player) && !empty($listing->ebayShortItem->specifics['Player']) && strtolower($listing->cardForMatch->player) == strtolower($listing->ebayShortItem->specifics['Player'])) {
                    //matched
                    $count = $count + 1;
                    // dump("Player");
                } else {
                    //not matched
                }
                if (!empty($listing->cardForMatch->year) && !empty($listing->ebayShortItem->specifics['Year']) && $listing->cardForMatch->year == $listing->ebayShortItem->specifics['Year']) {
                    //matched
                    $count = $count + 1;
                    // dump("Year");
                } else {
                    //not matched
                }

                if (isset($listing->cardForMatch->brand) && !empty($listing->cardForMatch->brand) && isset($listing->ebayShortItem->specifics['Brand']) && !empty($listing->ebayShortItem->specifics['Brand']) && strtolower($listing->cardForMatch->brand) == strtolower($listing->ebayShortItem->specifics['Brand'])) {
                    //matched
                    $count += 1;
                } else {
                    //not matched
                }
                if (isset($listing->cardForMatch->variation) && !empty($listing->cardForMatch->variation) && isset($listing->ebayShortItem->specifics['Variation']) && !empty($listing->ebayShortItem->specifics['Variation']) && strtolower($listing->cardForMatch->variation) == strtolower($listing->ebayShortItem->specifics['Variation'])) {
                    //matched
                    $count += 1;
                } else {
                    //not matched
                }
                if (isset($listing->cardForMatch->grade) && !empty($listing->cardForMatch->grade) && isset($listing->ebayShortItem->specifics['Grade']) && !empty($listing->ebayShortItem->specifics['Grade']) && strtolower($listing->cardForMatch->grade) == strtolower($listing->ebayShortItem->specifics['Grade'])) {
                    //matched
                    $count += 1;
                } else {
                    //not matched
                }
                if (isset($listing->cardForMatch->cardImage) && !empty($listing->cardForMatch->cardImage) && isset($listing->ebayShortItem->image) && !empty($listing->ebayShortItem->image) && strtolower($listing->cardForMatch->cardImage) == strtolower($listing->ebayShortItem->image)) {
                    //matched
                    $count += 1;
                } else {
                    //not matched
                }
            } else {
                
            }

            $percentage = ($count * 100) / $total;
            dump($percentage);
            // dump("count: ".$count);
        }


        dd($allListing->toArray());

        dd('end');


        $editor = Grafika::createEditor(); // Create editor
        $image1 = storage_path("app/public/F2702.jpg");
        // $image2 = storage_path("app/public/s-l300.jpg");
        $image2 = storage_path("app/public/s-l500.jpg");
        // $image2 = storage_path("app/public/Default-Slab-avatar-s-8.6f2c7e85.jpg");
        // $image2 = storage_path("app/public/Default-Slab-avatar-s-8.6f2c7e85.jpg");
        // $image1 = "https://slabstaxweb.s3.us-east-2.amazonaws.com/Football/F2702.jpg";
        // $image2 = "https://i.ebayimg.com/images/g/cJ8AAOSw4nRhMAF6/s-l300.jpg";
        $hammingDistance = $editor->compare($image1, $image2);

        dump($image1);
        dump($image2);
        dd($hammingDistance);
    }

    public function cronTestt() {

        // $ip = gethostbyname('emga.easygateway.net');
        $ip = gethostbyaddr('174.3.20.141');


        dd($ip);
        dd($_SERVER['REMOTE_ADDR']);

        // heritagevalleycapital.fortiddns.com => 209.91.86.211
        // dsmillwoods.fortiddns.com => 174.3.20.141
        // emga.easygateway.net => 198.166.36.2

        $this->cronForTrenderSpecificSport('Hockey');
    }

    public function cronForTrenderSpecificSport($singleSport) {
        // Log::info('CRON START');
        $days = [
            1 => [
                'from' => date('Y-m-d H:i:s'),
                'to' => date('Y-m-d H:i:s', strtotime('-1 day')),
                'daysForSx' => 0
            ],
            2 => [
                'from' => date('Y-m-d H:i:s'),
                'to' => date('Y-m-d H:i:s', strtotime('-8 day')),
                'daysForSx' => 7
            ],
            3 => [
                'from' => date('Y-m-d H:i:s'),
                'to' => date('Y-m-d H:i:s', strtotime('-30 day')),
                'daysForSx' => 30
            ],
            4 => [
                'from' => date('Y-m-d H:i:s'),
                'to' => date('Y-m-d H:i:s', strtotime('-90 day')),
                'daysForSx' => 90
            ],
            5 => [
                'from' => date('Y-m-d H:i:s'),
                'to' => date('Y-m-d H:i:s', strtotime('-180 day')),
                'daysForSx' => 180
            ],
            6 => [
                'from' => date('Y-m-d H:i:s'),
                'to' => date('Y-m-d H:i:s', strtotime('-365 day')),
                'daysForSx' => 365
            ],
            7 => [
                'from' => date('Y-m-d H:i:s'),
                'to' => date('Y-m-d H:i:s', strtotime('-1825 day')),
                'daysForSx' => 1825
            ],
        ];

        $sportsList = AppSettings::select('sports')->first();
        json_decode($sportsList);
        $sports = $sportsList->sports;

        foreach ($days as $daykey => $day) {
            foreach ($sports as $sport) {

                if (strtolower($singleSport) == strtolower($sport)) {
                    dump($singleSport . '==' . $sport);

                    $name = 'trenders_' . $daykey . '_' . $sport;
                    Cache::forget($name);
                    $trender = Cache::rememberForever($name, function() use($day, $sport) {
                                $cards = [];
                                $card_sales = CardSales::whereBetween('timestamp', [$day['to'], $day['from']])
                                        ->groupBy('card_id')
                                        ->select('card_id', DB::raw('SUM(quantity) as qty'))
                                        ->orderBy('qty', 'DESC')
                                        ->pluck('card_id')
                                        ->toArray();

                                if (!empty($card_sales)) {
                                    $cards = Card::whereHas('sales', function($q) use($day, $sport) {
                                                $q->whereBetween('timestamp', [$day['to'], $day['from']]);
                                            }, '>=', 2)->whereIn('id', $card_sales)->where('sport', $sport)->where('active', 1)->with('details')->get();

                                    $cards = $cards->map(function ($card, $key) use($day) {
                                        $data = $card;
                                        $sx_data = CardSales::getSxAndOldestSx($card->id, $day['to'], $day['from'], $day['daysForSx']);
                                        $sx = $sx_data['sx'];
                                        $lastSx = $sx_data['oldestSx'];
                                        $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                                        $data['price'] = number_format((float) $sx, 2, '.', '');
                                        $data['sx_value_signed'] = (float) $sx - $lastSx;
                                        $data['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                                        $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                                        $data['sx_percent_signed'] = $sx_percent;
                                        $data['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                                        $data['sx_icon'] = $sx_icon;
                                        $data['sale_qty'] = CardSales::where('card_id', $card->id)->sum('quantity');
                                        return $data;
                                    });
                                }
                                return $cards;
                            });
                }
            }
        }
        // Log::info('CRON END');
    }

}
