<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\CardsImport;
use App\Imports\ListingsImport;
//use App\Imports\BaseballCardsImport;
//use App\Imports\BasketballCardsImport;
//use App\Imports\FootballCardsImport;
//use App\Imports\SoccerCardsImport;
use Illuminate\Http\Request;
use App\Services\EbayService;
use App\Models\Card;
use App\Models\CardDetails;
use App\Models\CardValues;
use App\Models\CardSales;
use App\Jobs\ProcessCardsForRetrivingDataFromEbay;
use App\Jobs\ProcessCardsComplieData;
use App\Jobs\CompareEbayImagesWithCardImages;
use App\Jobs\StoreZipImages;
//use App\Jobs\StoreImage;
//use App\Jobs\StoreImagesZip;
use App\Models\Ebay\EbayItems;
use App\Models\Ebay\EbayShortItem;
use App\Models\RequestSlab;
use App\Models\RequestListing;
use App\Models\ExcelUploads;
use App\Models\AppSettings;
use App\Models\CardsSx;
use App\Models\CardsTotalSx;
use Carbon\Carbon;
use Excel;
use Validator;
use Illuminate\Support\Facades\Storage;
use ZipArchive;
use Illuminate\Support\Facades\DB;
use App\Jobs\ExcelImports;
use App\Models\Ebay\EbayItemSellingStatus;
use App\Models\Ebay\EbayItemListingInfo;
use App\Models\Ebay\EbayItemSellerInfo;
use App\Models\Ebay\EbayItemSpecific;
use DateTime;
use Illuminate\Support\Facades\Cache;
use App\Jobs\JobForTrender;
use Log;
use Illuminate\Support\Str;

//use DB;

class CardController extends Controller {

    public function getFetchItemForAdmin(Request $request) {
        $itemId = $request->input('itemid', null);
        if ($itemId != null) {
            try {
                $response = EbayService::getSingleItemDetails($itemId);
                if (isset($response['data'])) {
                    return response()->json(['status' => 200, 'data' => $response['data']], 200);
                } else {
                    return response()->json('No record found.', 500);
                }
            } catch (\Exception $e) {
                return response()->json($e->getMessage(), 500);
            }
        } else {
            return response()->json('Invalid item id', 500);
        }
    }

    public function getItemScarpForAdmin(Request $request) {
        $itemId = $request->input('itemid', null);
        if ($itemId != null) {
            try {
                $script_link = '/home/' . env('SCRAP_USER') . '/ebay/ebayFetch/bin/python3 /home/' . env('SCRAP_USER') . '/ebay/core.py """' . $itemId . '"""';
                $scrap_response = shell_exec($script_link . " 2>&1");
                $response = json_decode($scrap_response);
                return response()->json(['status' => 200, 'data' => $response], 200);
            } catch (\Exception $e) {
                return response()->json($e->getMessage(), 500);
            }
        } else {
            return response()->json('Invalid item id', 500);
        }
    }

    public function getCardListForAdmin(Request $request) {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $search = $request->input('search', null);
        $skip = $take * $page;
        $skip = $skip - $take;

        try {
            $cards = Card::where(function ($q) use ($request) {
                        if ($request->has('sport') && $request->input('sport') != null) {
                            $q->where('sport', $request->input('sport'));
                        }
                        if ($request->has('search') && $request->get('search') != '' && $request->get('search') != null) {
                            $keyword_list = explode(' ', $request->input('search'));
                            foreach ($keyword_list as $kw) {
                                $q->where('title', 'like', "%$kw%");
                            }
                            $q->orWhere('id', $request->get('search'));
                        }
                    });
            $cards_count = $cards->count();
            $all_pages = ceil($cards_count / $take);
            $card_results = $cards->skip($skip)->take($take)->get();
            $data = [];
            foreach ($card_results as $card) {
                $data[] = [
                    'id' => $card->id,
                    'sport' => $card->sport,
                    'player' => $card->player,
                    'year' => $card->year,
                    'brand' => $card->brand,
                    'card' => $card->card,
                    'rc' => $card->rc,
                    'variation' => $card->variation,
                    'grade' => $card->grade,
                    'is_featured' => $card->is_featured,
                    'active' => $card->active,
                    'is_sx' => $card->is_sx,
                    'image' => $card->cardImage,
                ];
            }
            // $sportsList = Card::select('sport')->distinct()->pluck('sport');
            $sportsList = AppSettings::select('sports')->first();
            json_decode($sportsList);
            return response()->json(['status' => 200,
                        'data' => $data,
                        'all_pages' => $all_pages,
                        'next' => ($page + 1),
                        'sportsList' => $sportsList->sports], 200);
        } catch (\Exception $e) {
            return response()->json(['status' => 500, 'error' => $e->getMessage()], 500);
        }
    }

    public function getCardList(Request $request) {
        try {
            $search = $request->input('search', null);
            $length = $request->input('length', 25);
            $start = $request->input('start', 0);

            $cards = Card::where(function ($q) use ($request) {
                        if ($request->has('sport') && $request->input('sport') != null) {
                            $q->where('sport', $request->input('sport'));
                        }
                    });

            if ($search != null && $search != '') {
                $cards = $cards->get()->filter(function ($item) use ($search) {
                            $playerCheck = (strpos(strtolower($item->player), strtolower($search)) !== false);
                            $yearCheck = (strpos(strtolower($item->year), strtolower($search)) !== false);
                            $brandCheck = (strpos(strtolower($item->brand), strtolower($search)) !== false);
                            $cardCheck = (strpos(strtolower($item->card), strtolower($search)) !== false);
                            $crCheck = (strpos(strtolower($item->cr), strtolower($search)) !== false);
                            $variationCheck = (strpos(strtolower($item->variation), strtolower($search)) !== false);
                            $gradeCheck = (strpos(strtolower($item->grade), strtolower($search)) !== false);
                            $qualifiersCheck = (strpos(strtolower($item->qualifiers), strtolower($search)) !== false);

                            return ($playerCheck || $yearCheck || $brandCheck || $cardCheck || $crCheck || $variationCheck || $gradeCheck || $qualifiersCheck);
                        })->values();
                $cards = $cards->splice($start)->take($length);
            } else {
                $cards = $cards->splice($start)->take($length)->get();
            }
            $total = count($cards);
            $lastPage = ceil($total / $length);

            return response()->json(['status' => 200, 'data' => [
                            "cards" => $cards,
                            "per_page" => (int) $length,
                            "start" => (int) ($start + $length),
                            "total" => $total]
                            ], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public static function getDataFromEbay() {
        $cards = Card::where('readyforcron', 1)->get();
        foreach ($cards as $card) {
            ProcessCardsForRetrivingDataFromEbay::dispatch($card);
        }
    }

    public static function complieCardsData() {
        $cards = Card::where('readyforcron', 1)->get();
        foreach ($cards as $card) {
            ProcessCardsComplieData::dispatch($card->id);
        }
    }

    public static function compareEbayImages() {
        $cards = EbayItems::where('status', 0)->distinct('card_id')->get();
        foreach ($cards as $card) {
            CompareEbayImagesWithCardImages::dispatch($card->card_id);
        }
    }

    public function getCardDetails(int $id, Request $request) {
        try {
//            $rank = 'N/A';
//            $card_sales = CardSales::where('card_id', $id)->pluck('id')->toArray();
//            if (!empty($card_sales)) {
//                $cs = CardSales::groupBy('card_id')->select('id', 'card_id', DB::raw('SUM(quantity) as qty'))->orderBy('qty', 'DESC')->get()->map(function($item, $key) use($id, &$rank) {
//                    if ($item['card_id'] == $id) {
//                        $rank = ++$key;
//                        return;
//                    }
//                });
//            }
            $cards = Card::where('id', $id)->with('details')->firstOrFail()->toArray();
//            $cards['rank'] = $rank;

            return response()->json(['status' => 200, 'data' => $cards], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getRecentList(Request $request) {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $search = $request->input('search', null);
        $top_trend = $request->input('top_trend', false);
        $days = $request->input('filterval', 4);
        $orderby = $request->input('orderby', false);
        $skip = $take * $page;
        $skip = $skip - $take;

        if ($days == 1) {
            $grpFormat = 'H:i';
            $from = date('Y-m-d H:i:s');
            $to = date('Y-m-d H:i:s', strtotime('-1 day'));
            $daysForSx = 0;
        } elseif ($days == 2) {
            $grpFormat = 'Y-m-d';
            $from = date('Y-m-d H:i:s');
            $to = date('Y-m-d H:i:s', strtotime('-8 days'));
            $daysForSx = 7;
        } elseif ($days == 3) {
            $grpFormat = 'Y-m-d';
            $lblFormat = 'H:i';
            $from = date('Y-m-d H:i:s');
            $to = date('Y-m-d H:i:s', strtotime('-30 days'));
            $daysForSx = 30;
        } elseif ($days == 4) {
            $grpFormat = 'Y-m';
            $from = date('Y-m-d H:i:s');
            $to = date('Y-m-d H:i:s', strtotime('-90 days'));
            $daysForSx = 90;
        } elseif ($days == 5) {
            $grpFormat = 'Y-m';
            $from = date('Y-m-d H:i:s');
            $to = date('Y-m-d H:i:s', strtotime('-180 days'));
            $daysForSx = 180;
        } elseif ($days == 6) {
            $grpFormat = 'Y-m';
            $from = date('Y-m-d H:i:s');
            $to = date('Y-m-d H:i:s', strtotime('-365 days'));
            $daysForSx = 365;
        } elseif ($days == 7) {
            $grpFormat = 'Y';
            $from = date('Y-m-d H:i:s');
            $to = date('Y-m-d H:i:s', strtotime('-1825 days'));
            $daysForSx = 1825;
        }
        try {
            $cards = [];
            $card_sales = CardSales::whereBetween('timestamp', [$to, $from])->groupBy('card_id')->select('card_id', DB::raw('SUM(quantity) as qty'))->orderBy('qty', 'DESC')->pluck('card_id')->toArray();
            if (!empty($card_sales)) {
                $cards = Card::where(function ($q) use ($request, $search) {
                            if ($request->has('sport') && $request->input('sport') != null) {
                                $q->where('sport', $request->input('sport'));
                            }
                            if ($search != null) {
                                $q->where('title', 'like', '%' . $search . '%');
                            }
                        })->whereHas('sales', function($q) use($to, $from) {
                            $q->whereBetween('timestamp', [$to, $from]);
                        })->whereIn('id', $card_sales)->where('active', 1)->with('details');
                $cards = $cards->get()->map(function ($card, $key) use($orderby, $to, $from, $daysForSx) {
                    $data = $card;
                    $sx_data = CardSales::getSxAndOldestSx($card->id, $to, $from, $daysForSx);
                    $sx = $sx_data['sx'];
                    $lastSx = $sx_data['oldestSx'];
                    $show_perentage = false;
                    if ($orderby == 'percentup' || $orderby == 'percentdown') {
                        $show_perentage = true;
                    }
                    $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                    $data['price'] = number_format((float) $sx, 2, '.', '');
                    $data['sx_value_signed'] = (float) $sx - $lastSx;
                    $data['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                    $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                    $data['sx_percent_signed'] = $sx_percent;
                    $data['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                    $data['sx_icon'] = $sx_icon;
                    $data['show_perentage'] = $show_perentage;
                    $data['sale_qty'] = CardSales::where('card_id', $card->id)->sum('quantity');
                    return $data;
                });
                if ($request->input('orderby') == 'priceup') {
                    $cards = $cards->sortByDesc('sx_value_signed');
                    $cards = $cards->values()->all();
                } elseif ($request->input('orderby') == 'pricedown') {
                    $cards = $cards->sortBy('sx_value_signed');
                    $cards = $cards->values()->all();
                } else if ($request->input('orderby') == 'percentup') {
                    $cards = $cards->sortByDesc('sx_percent_signed');
                    $cards = $cards->values()->all();
                } else if ($request->input('orderby') == 'percentdown') {
                    $cards = $cards->sortBy('sx_percent_signed');
                    $cards = $cards->values()->all();
                }
                if ($top_trend) {
                    $cards = Collect($cards)->unique('sport');
                    foreach ($cards as $key => $item) {
                        if ($item->sport == 'Pokémon') {
                            $cards->forget($key);
                        }
                    }
                }
                if (!$top_trend) {
                    $cards = Collect($cards)->skip($skip)->take($take);
                }
            }
            $AppSettings = AppSettings::first();
            // $order = ['basketball', 'soccer', 'baseball', 'football', 'pokemon'];
            if ($AppSettings) {
                $order = $AppSettings->trenders_order;
            }
            return response()->json(['status' => 200, 'data' => $cards, 'next' => ($page + 1), 'order' => $order], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    // public function test() {
    //     Log::info('CRON START');
    //       $days = [
    //         1 => [
    //             'from' => date('Y-m-d H:i:s'),
    //             'to' => date('Y-m-d H:i:s', strtotime('-1 day')),
    //             'daysForSx' => 0
    //         ],
    //         2 => [
    //             'from' => date('Y-m-d H:i:s'),
    //             'to' => date('Y-m-d H:i:s', strtotime('-8 day')),
    //             'daysForSx' => 7
    //         ],
    //         3 => [
    //             'from' => date('Y-m-d H:i:s'),
    //             'to' => date('Y-m-d H:i:s', strtotime('-30 day')),
    //             'daysForSx' => 30
    //         ],
    //         4 => [
    //             'from' => date('Y-m-d H:i:s'),
    //             'to' => date('Y-m-d H:i:s', strtotime('-90 day')),
    //             'daysForSx' => 90
    //         ],
    //         5 => [
    //             'from' => date('Y-m-d H:i:s'),
    //             'to' => date('Y-m-d H:i:s', strtotime('-180 day')),
    //             'daysForSx' => 180
    //         ],
    //         6 => [
    //             'from' => date('Y-m-d H:i:s'),
    //             'to' => date('Y-m-d H:i:s', strtotime('-365 day')),
    //             'daysForSx' => 365
    //         ],
    //         7 => [
    //             'from' => date('Y-m-d H:i:s'),
    //             'to' => date('Y-m-d H:i:s', strtotime('-1825 day')),
    //             'daysForSx' => 1825
    //         ],
    //     ];
    //     $sports = [
    //         0 => 'basketball',
    //         1 => 'soccer',
    //         2 => 'baseball',
    //         3 => 'football',
    //         4 => 'hockey',
    //         5 => 'pokemon',
    //     ];
    //     foreach ($days as $daykey => $day) {
    //         foreach ($sports as $sport) {
    //             $name = 'trenders_' . $daykey . '_' . $sport;
    //             Cache::forget($name);
    //             $trender = Cache::rememberForever($name, function() use($day, $sport) {
    //                         $cards = [];
    //                         $card_sales = CardSales::whereBetween('timestamp', [$day['to'], $day['from']])->groupBy('card_id')->select('card_id', DB::raw('SUM(quantity) as qty'))->orderBy('qty', 'DESC')->pluck('card_id')->toArray();
    //                         if (!empty($card_sales)) {
    //                             $cards = Card::whereHas('sales', function($q) use($day, $sport) {
    //                                         $q->whereBetween('timestamp', [$day['to'], $day['from']]);
    //                                     }, '>=', 2)->whereIn('id', $card_sales)->where('sport', $sport)->where('active', 1)->with('details')->get();
    //                             $cards = $cards->map(function ($card, $key) use($day) {
    //                                 $data = $card;
    //                                 $sx_data = CardSales::getSxAndOldestSx($card->id, $day['to'], $day['from'], $day['daysForSx']);
    //                                 $sx = $sx_data['sx'];
    //                                 $lastSx = $sx_data['oldestSx'];
    //                                 $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
    //                                 $data['price'] = number_format((float) $sx, 2, '.', '');
    //                                 $data['sx_value_signed'] = (float) $sx - $lastSx;
    //                                 $data['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
    //                                 $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
    //                                 $data['sx_percent_signed'] = $sx_percent;
    //                                 $data['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
    //                                 $data['sx_icon'] = $sx_icon;
    //                                 $data['sale_qty'] = CardSales::where('card_id', $card->id)->sum('quantity');
    //                                 return $data;
    //                             });
    //                         }
    //                         return $cards;
    //                     });
    //         }
    //     }
    // }
    public function cronForTrender() {
        Log::info('CRON START');
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
                $name = 'trenders_' . $daykey . '_' . $sport;
                Cache::forget($name);
                $trender = Cache::rememberForever($name, function() use($day, $sport) {
                            $cards = [];
                            $card_sales = CardSales::whereBetween('timestamp', [$day['to'], $day['from']])->groupBy('card_id')->select('card_id', DB::raw('SUM(quantity) as qty'))->orderBy('qty', 'DESC')->pluck('card_id')->toArray();
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
        
        $data = CardsSx::orderBy('date', 'DESC')->get()->groupBy('card_id')->map(function ($item, $key) {
            if(isset($item[1])) {
                return [
                    'cardId' => $item[0]->card_id,
                    'sxDifference' => $item[0]->sx - $item[1]->sx,
                    '%SxDifference' => (($item[0]->sx - $item[1]->sx)/$item[1]->sx)*100,
                    ];
            } else {
                return [
                    'cardId' => $item[0]->card_id,
                    'sxDifference' => '-99999999999999999999',
                    '%SxDifference' => '-9999999999999999999',
                    'remark' => 'rank N/A'
                    ];
            }
        });
        
        $myNewArray = array_values($data->toArray());
        $keys = array_column($myNewArray, 'sxDifference');
        array_multisort($keys, SORT_DESC, $myNewArray);
        $rankBasedOnSxDifference = $myNewArray;
        $keys = array_column($myNewArray, '%SxDifference');
        array_multisort($keys, SORT_DESC, $myNewArray);
        $rankBasedOnSxDifferencePercentage = $myNewArray;
        
        $name = 'card_rank_based_on_sx_difference';
        Cache::forget($name);
        $trender = Cache::rememberForever($name, function() use($rankBasedOnSxDifference) {
                            $ranks = [];
                            foreach($rankBasedOnSxDifference as $key => $rankSxDifference) {
                                if(!isset($rank['remark'])) {
                                    $ranks[$rankSxDifference['cardId']] = $key+1;
                                } else {
                                    $ranks[$rankSxDifference['cardId']] = 'N/A';
                                }
                            }
                            return $ranks;
                        });
        $name = 'card_rank_based_on_sx_difference_percentage';
        Cache::forget($name);
        $trender = Cache::rememberForever($name, function() use($rankBasedOnSxDifferencePercentage){
                            $ranks = [];
                            foreach($rankBasedOnSxDifferencePercentage as $key => $rankSxDifferencePercentage) {
                                if(!isset($rank['remark'])) {
                                    $ranks[$rankSxDifferencePercentage['cardId']] = $key+1;
                                } else {
                                    $ranks[$rankSxDifferencePercentage['cardId']] = 'N/A';
                                }
                            }
                            return $ranks;
                        });
        
        Log::info('CRON END');
    }

    public function cronTest() {
        dd('sadf');
        $this->cronForTrenderSpecificSport('Hockey');
    }

    public function cronForTrenderSpecificSport($singleSport) {
        Log::info('CRON START');
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
//                    dump($singleSport . '==' . $sport);
                }
//                dd('sdf');
                $name = 'trenders_' . $daykey . '_' . $sport;
                Cache::forget($name);
                $trender = Cache::rememberForever($name, function() use($day, $sport) {
                            $cards = [];
                            $card_sales = CardSales::whereBetween('timestamp', [$day['to'], $day['from']])->groupBy('card_id')->select('card_id', DB::raw('SUM(quantity) as qty'))->orderBy('qty', 'DESC')->pluck('card_id')->toArray();
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
        Log::info('CRON END');
    }

    //create trender cache from scratch
    public function createTrenderCache() {
//        Cache::flush();
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
        $sports = [
            0 => 'basketball',
//            1 => 'soccer',
//            2 => 'baseball',
//            3 => 'football',
//            4 => 'hockey',
//            5 => 'pokemon',
        ];
//        foreach ($days as $daykey => $day) {
//            foreach ($sports as $sport) {
//                $name = 'trenders_' . $daykey . '_' . $sport;
//                Cache::forget($name);
//                $trender = Cache::rememberForever($name, function() use($day, $sport) {
//                            $cards = [];
//                            $card_sales = CardSales::whereBetween('timestamp', [$day['to'], $day['from']])->groupBy('card_id')->select('card_id', DB::raw('SUM(quantity) as qty'))->orderBy('qty', 'DESC')->pluck('card_id')->toArray();
//                            if (!empty($card_sales)) {
//                                $cards = Card::whereHas('sales', function($q) use($day, $sport) {
//                                            $q->whereBetween('timestamp', [$day['to'], $day['from']]);
//                                        }, '>=', 2)->whereIn('id', $card_sales)->where('sport', $sport)->where('active', 1)->with('details')->limit(5)->get();
//
//                                $cards = $cards->map(function ($card, $key) use($day) {
//                                    $data = $card;
//                                    $sx_data = CardSales::getSxAndOldestSx($card->id, $day['to'], $day['from'], $day['daysForSx']);
//                                    $sx = $sx_data['sx'];
//                                    $lastSx = $sx_data['oldestSx'];
//                                    $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
//                                    $data['price'] = number_format((float) $sx, 2, '.', '');
//                                    $data['sx_value_signed'] = (float) $sx - $lastSx;
//                                    $data['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
//                                    $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
//                                    $data['sx_percent_signed'] = $sx_percent;
//                                    $data['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
//                                    $data['sx_icon'] = $sx_icon;
//                                    $data['sale_qty'] = CardSales::where('card_id', $card->id)->sum('quantity');
//                                    return $data;
//                                });
//                            }
//                            return $cards;
//                        });
//            }
//        }
//        die('success 1');

        Cache::forget('trenders_all_cards');
        $trender = Cache::rememberForever('trenders_all_cards', function() {
                    $cards = [];
                    $card_sales = CardSales::groupBy('card_id')->select('card_id', DB::raw('SUM(quantity) as qty'))->orderBy('qty', 'DESC')->pluck('card_id')->toArray();
                    if (!empty($card_sales)) {
                        $cards = Card::whereHas('sales', function($q) {
                                    $q->where('timestamp', '!=', null);
                                }, '>=', 2)->whereIn('id', $card_sales)->where('active', 1)->with('details')->get();

                        $cards = $cards->map(function ($card, $key) {
                            $data = $card;
                            $sx_data = CardSales::getSxAndOldestSx($card->id);
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
        die('DONE');
    }

    public function getRecentListRedis(Request $request) {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $search = $request->input('search', null);
        $top_trend = $request->input('top_trend', false);
        $days = $request->input('filterval', 4);
        $orderby = $request->input('orderby', false);
        $skip = $take * $page;
        $skip = $skip - $take;

        if ($days == 1) {
            $grpFormat = 'H:i';
            $from = date('Y-m-d H:i:s');
            $to = date('Y-m-d H:i:s', strtotime('-1 day'));
            $daysForSx = 0;
        } elseif ($days == 2) {
            $grpFormat = 'Y-m-d';
            $from = date('Y-m-d H:i:s');
            $to = date('Y-m-d H:i:s', strtotime('-8 days'));
            $daysForSx = 7;
        } elseif ($days == 3) {
            $grpFormat = 'Y-m-d';
            $lblFormat = 'H:i';
            $from = date('Y-m-d H:i:s');
            $to = date('Y-m-d H:i:s', strtotime('-30 days'));
            $daysForSx = 30;
        } elseif ($days == 4) {
            $grpFormat = 'Y-m';
            $from = date('Y-m-d H:i:s');
            $to = date('Y-m-d H:i:s', strtotime('-90 days'));
            $daysForSx = 90;
        } elseif ($days == 5) {
            $grpFormat = 'Y-m';
            $from = date('Y-m-d H:i:s');
            $to = date('Y-m-d H:i:s', strtotime('-180 days'));
            $daysForSx = 180;
        } elseif ($days == 6) {
            $grpFormat = 'Y-m';
            $from = date('Y-m-d H:i:s');
            $to = date('Y-m-d H:i:s', strtotime('-365 days'));
            $daysForSx = 365;
        } elseif ($days == 7) {
            $grpFormat = 'Y';
            $from = date('Y-m-d H:i:s');
            $to = date('Y-m-d H:i:s', strtotime('-1825 days'));
            $daysForSx = 1825;
        }
        try {
            if ($search != null) {
                $cards = [];
                $card_sales = CardSales::whereBetween('timestamp', [$to, $from])->groupBy('card_id')->select('card_id', DB::raw('SUM(quantity) as qty'))->orderBy('qty', 'DESC')->pluck('card_id')->toArray();
                if (!empty($card_sales)) {
                    $trender = Card::whereHas('sales', function($q) use($to, $from) {
                                        $q->whereBetween('timestamp', [$to, $from]);
                                    }, '>=', 2)
                                    ->whereIn('id', $card_sales)->where('active', 1)
                                    ->with('details')
                                    ->where(function ($q) use ($request, $search) {
                                        if ($request->has('sport') && $request->input('sport') != null) {
                                            $q->where('sport', $request->input('sport'));
                                        }
                                        if ($search != null) {
                                            $q->where('player', 'like', '%' . $search . '%');
                                        }
                                    })->get();

                    $trender = $trender->map(function ($card, $key) use($orderby, $to, $from, $daysForSx, $request) {
                        $data = $card;
                        $sx_data = CardSales::getSxAndOldestSx($card->id, $to, $from, $daysForSx);
                        $sx = $sx_data['sx'];
                        $lastSx = $sx_data['oldestSx'];
                        $show_perentage = false;
                        if ($orderby == 'percentup' || $orderby == 'percentdown') {
                            $show_perentage = true;
                        }
                        $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                        $data['price'] = number_format((float) $sx, 2, '.', '');
                        $data['sx_value_signed'] = (float) $sx - $lastSx;
                        $data['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                        $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                        $data['sx_percent_signed'] = $sx_percent;
                        $data['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                        $data['sx_icon'] = $sx_icon;
                        $data['show_perentage'] = $show_perentage;
                        $data['sale_qty'] = CardSales::where('card_id', $card->id)->sum('quantity');
                        return $data;
                    });
                }
            } else {
                if ($request->has('sport') && $request->input('sport') != null) {
                    $sport = $request->input('sport');
                    $name = 'trenders_' . $days . '_' . $sport;
                } elseif ($top_trend == true) {
                    $AppSettings = AppSettings::first();
                    if ($AppSettings) {
                        $order = $AppSettings->trenders_order;
                    }
                    $trending = [];
                    foreach ($order as $ord) {
                        if ($ord != 'pokemon') {
                            $name = 'trenders_' . $days . '_' . $ord;
                            $trender = Cache::get($name);
                            if (!$trender instanceof Illuminate\Database\Eloquent\Collection) {
                                $trender = Collect($trender);
                            }
                            if (count($trender) > 0) {
                                if ($request->input('orderby') == 'priceup') {
                                    $trender = $trender->sortByDesc('sx_value_signed');
                                    $trender = $trender->values()->all();
                                } elseif ($request->input('orderby') == 'pricedown') {
                                    $trender = $trender->sortBy('sx_value_signed');
                                    $trender = $trender->values()->all();
                                } else if ($request->input('orderby') == 'percentup') {
                                    $trender = $trender->sortByDesc('sx_percent_signed');
                                    $trender = $trender->values()->all();
                                } else if ($request->input('orderby') == 'percentdown') {
                                    $trender = $trender->sortBy('sx_percent_signed');
                                    $trender = $trender->values()->all();
                                }
                                $trending[] = $trender[0];
                            }
                        }
                    }
                    return response()->json(['status' => 200, 'data' => $trending, 'next' => ($page + 1), 'order' => $order], 200);
                } else {
                    $name = 'trenders_all_cards';
                }
                $trender = Cache::get($name);
            }

//            foreach($trender as $key => $val) {
////                dd($val->id);
//                if($val->id == 19043) {
//                    dump($key);
//                    dd($val->toArray());
//                }
//            }


            if (!$trender instanceof Illuminate\Database\Eloquent\Collection) {
                $trender = Collect($trender);
            }
            if ($request->input('orderby') == 'priceup') {
                $trender = $trender->sortByDesc('sx_value_signed');
                $trender = $trender->values()->all();
            } elseif ($request->input('orderby') == 'pricedown') {
                $trender = $trender->sortBy('sx_value_signed');
                $trender = $trender->values()->all();
            } else if ($request->input('orderby') == 'percentup') {
                $trender = $trender->sortByDesc('sx_percent_signed');
                $trender = $trender->values()->all();
            } else if ($request->input('orderby') == 'percentdown') {
                $trender = $trender->sortBy('sx_percent_signed');
                $trender = $trender->values()->all();
            }
            $trender = Collect($trender)->skip($skip)->take($take);
            $AppSettings = AppSettings::first();
            if ($AppSettings) {
                $order = $AppSettings->trenders_order;
            }
            return response()->json(['status' => 200, 'data' => $trender, 'next' => ($page + 1), 'order' => $order], 200);
        } catch (Exception $ex) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getFeaturedList(Request $request) {
        $take = $request->input('take', 30);
        try {
            $cards = Card::where(function ($q) use ($request) {
                        if ($request->has('is_featured') && $request->input('is_featured') != null) {
                            $q->where('is_featured', $request->input('is_featured'));
                        }
                    })->where('active', 1)->with('details');

            $cards = $cards->take($take)->get();
            $cards = $cards->map(function($card, $key) {
                $data = $card;
                $sx_data = CardSales::getSxAndLastSx($card->id);
                $sx = $sx_data['sx'];
                $lastSx = $sx_data['lastSx'];

                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                $data['price'] = number_format((float) $sx, 2, '.', '');
                $data['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                $data['sx_icon'] = $sx_icon;
                return $data;
            });

            return response()->json(['status' => 200, 'data' => $cards], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getSmartKeyword(Request $request) {
        try {
            $keyword_list = explode(' ', $request->input('keyword'));
            $list = [];
            $data = Card::whereHas('sales')->where(function ($query) use($keyword_list) {
                        foreach ($keyword_list as $kw) {
                            $query->where('title', 'like', "%$kw%");
                        }
                    })->distinct('player')->where('active', 1)->take(10)->get()->map(function($res) use(&$list) {
                $name = explode(' ', $res->player);
                $list[] = [
                    'id' => $res->id,
                    'player' => $name[0],
                    'title' => $res->title
                ];
            });
            //                    whereIn('title', 'like', '%' . $keyword_list . '%')
            //                    ->orWhere('variation', 'like', '%' . $request->input('keyword') . '%')
            //                    ->orWhere('grade', 'like', '%' . $request->input('keyword') . '%')
            //                    ->orWhere('player', 'like', '%' . $request->input('keyword') . '%')
            return response()->json(['status' => 200, 'data' => $list, 'keyword' => $request->input('keyword')], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getSmartKeywordWithData(Request $request) {
        try {
            $keyword_list = explode(' ', $request->input('search'));
            $data = Card::whereHas('sales')->where(function($query) use ($keyword_list) {
                        foreach ($keyword_list as $kw) {
                            $query->where('title', 'like', "%$kw%");
                        }
                    })->distinct('player')->where('active', 1)->take(10)->get();
            return response()->json(['status' => 200, 'data' => $data, 'keyword' => $request->input('search')], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage() . ' ' . $e->getLine(), 500);
        }
    }

    public function getPopularPickCards(Request $request) {
        try {
            $data = Card::with(['details'])->orderBy('views', 'desc')->orderBy('created_at', 'desc')->take($request->input('take', 10))->get();
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage() . ' ' . $e->getLine(), 500);
        }
    }

    public function getCardRank($id) {
        $rank = 'N/A';
        $cs = CardSales::groupBy('card_id')->select('id', 'card_id', DB::raw('SUM(quantity) as qty'))->orderBy('qty', 'DESC')->get()->map(function($item, $key) use($id, &$rank) {
            if ($item['card_id'] == $id) {
                $rank = ++$key;
                return;
            }
        });
        return $rank;
    }

    public function getSmartKeywordOnlyName(Request $request) {
        try {
            if ($request->input('sport') != null) {
                $data = Card::select('player')->where('player', 'like', '%' . $request->input('keyword') . '%')
                                ->where('sport', $request->input('sport'))
                                ->where('active', 1)->distinct()->get()->take(10);
            } else {
                $data = Card::select('player')->where('player', 'like', '%' . $request->input('keyword') . '%')
                                ->where('active', 1)->distinct()->get()->take(10);
            }

            return response()->json(['status' => 200, 'data' => $data, 'keyword' => $request->input('keyword')], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function setFeatured(Request $request) {
        try {
            $is_featured = (bool) $request->input('is_featured');
            $data = Card::where('id', $request->input('id'))->first();
            if (isset($data->id)) {
                $cardSport = trim($data->sport);
                //echo $cardSport.' : '.$is_featured;
                /** update is featured start * */
                (Card::where('id', $request->input('id'))->first())->update(array('is_featured' => $is_featured));
                if ($is_featured) {
                    (Card::where('sport', $cardSport)->where('id', '<>', $request->input('id'))->first())->update(array('is_featured' => 0));
                }
                /** update is featured end * */
                return response()->json(['status' => 200, 'message' => (($is_featured) ? 'Card set as featured' : 'Card remove from featured')], 200);
            } else {
                return response()->json(['status' => 500, 'message' => 'Invalid card id!'], 500);
            }
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function setStatus(Request $request) {
        try {
            $status = (bool) $request->input('status');
            $data = Card::where('id', $request->input('id'))->first();
            if (isset($data->id)) {
                /** update is featured start * */
                (Card::where('id', $request->input('id'))->first())->update(array('active' => $status));
                /** update is featured end * */
                return response()->json(['status' => 200, 'message' => (($status) ? 'Card active successfully' : 'Card in-active successfully')], 200);
            } else {
                return response()->json(['status' => 500, 'message' => 'Invalid card id!'], 500);
            }
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function setSx(Request $request) {
        try {
            $is_sx = (bool) $request->input('is_sx');
            $data = Card::where('id', $request->input('id'))->first();
            if (isset($data->id)) {
                $data->update(array('is_sx' => $is_sx));

                return response()->json(['status' => 200, 'message' => ('Card SX pro value updated.')], 200);
            } else {
                return response()->json(['status' => 500, 'message' => 'Invalid card id!'], 500);
            }
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function create(Request $request) {
        try {
            $data = Card::where('sport', $request->input('sport'))->orderBy('updated_at', 'desc')->first();
            if ($request->hasFile('image')) {
                $filename = $request->sport . '/' . date("mdYHis") . '.' . $request->image->extension();
                Storage::disk('s3')->put($filename, file_get_contents($request->image), 'public');
            }
            //Case: when Card gets added from slabRequest
            if (!$request->hasFile('image') && $request->input('image')) {
                $image_path = $request->input('image');
                $image_name = str_replace("stoxRequests/", "", $image_path);
                $filename = $request->input('sport') . '/' . $image_name;
                Storage::disk('s3')->put($filename, file_get_contents(storage_path('app/public/' . $image_path)), 'public');
            }

            Card::create([
                'row_id' => (int) $data->row_id + 1, //dont need row_id now
                'sport' => $request->input('sport'),
                'player' => $request->input('player'),
                'year' => $request->input('year'),
                'brand' => $request->input('brand'),
                'card' => $request->input('card'),
                'rc' => $request->input('rc'),
                'variation' => $request->input('variation'),
                'title' => $request->input('title'),
                'grade' => $request->input('grade'),
                'active' => 1,
                'qualifiers' => $request->input('qualifiers'),
                'qualifiers2' => $request->input('qualifiers2'),
                'qualifiers3' => $request->input('qualifiers3'),
                'qualifiers4' => $request->input('qualifiers4'),
                'qualifiers5' => $request->input('qualifiers5'),
                'qualifiers6' => $request->input('qualifiers6'),
                'qualifiers7' => $request->input('qualifiers7'),
                'qualifiers8' => $request->input('qualifiers8'),
                'is_featured' => ((bool) $request->input('is_featured')),
                'image' => $request->hasFile('image') || $request->has('image') ? $filename : null,
            ]);
            if ($request->has('request_slab') && strlen(trim($request->get('request_slab'))) > 0) {
                $req = RequestSlab::whereId($request->input('request_slab'))->first();
                $req->update(['status' => 1]);
            }

            return response()->json(['status' => 200, 'message' => 'Card created successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getEditCard($card_id) {
        try {
            $data = Card::where('id', $card_id)->first();
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function editCard(Request $request) {
        try {
            if ($request->hasFile('image')) {
                $filename = $request->sport . '/' . date("mdYHis") . '.' . $request->image->extension();
                Storage::disk('s3')->put($filename, file_get_contents($request->image), 'public');
                $oldImage = Card::where('id', $request->input('id'))->first('image');
                Storage::disk('s3')->delete($oldImage['image']);
            }
            $update_array = [
                'sport' => $request->input('sport'),
                'player' => $request->input('player'),
                'year' => $request->input('year'),
                'brand' => $request->input('brand'),
                'card' => $request->input('card'),
                'rc' => $request->input('rc'),
                'title' => $request->input('title'),
                'variation' => $request->input('variation'),
                'grade' => $request->input('grade'),
                'qualifiers' => $request->input('qualifiers'),
                'qualifiers2' => $request->input('qualifiers2'),
                'qualifiers3' => $request->input('qualifiers3'),
                'qualifiers4' => $request->input('qualifiers4'),
                'qualifiers5' => $request->input('qualifiers5'),
                'qualifiers6' => $request->input('qualifiers6'),
                'qualifiers7' => $request->input('qualifiers7'),
                'qualifiers8' => $request->input('qualifiers8'),
            ];
            if ($request->hasFile('image')) {
                $update_array['image'] = $filename;
            }
            Card::where('id', $request->input('id'))->first()->update($update_array);
            return response()->json(['status' => 200, 'message' => 'Card updated successfully.'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

//    private function __setTotalSxValueByDate($date) {
//        $saleTotal = CardSales::where('timestamp', 'like', '%' . Carbon::create($date)->format('Y-m-d') . '%')->get();
//
//        if (CardsTotalSx::where('date', Carbon::create($date)->format('Y-m-d'))->exists()) {
//            CardsTotalSx::where('date', Carbon::create($date)->format('Y-m-d'))
//                    ->update(["amount" => $saleTotal->avg('cost'), "quantity" => $saleTotal->sum('quantity')]);
//        } else {
//            CardsTotalSx::create([
//                "date" => Carbon::create($date)->format('Y-m-d'),
//                "quantity" => $saleTotal->sum('quantity'),
//                "amount" => $saleTotal->avg('cost'),
//            ]);
//        }
//
//        $total = CardSales::avg("cost");
////        $total = CardsSx::sum("sx");
//        AppSettings::first()->update(["total_sx_value" => $total]);
//    }


    public function createSales(Request $request) {
        \DB::beginTransaction();
        try {
            $data = $request->all();
            $carbonNow = Carbon::create($data['timestamp']);
            $cardSport = Card::where('id', $data['card_id'])->pluck('sport');

            CardSales::create([
                'card_id' => $data['card_id'],
                'timestamp' => $carbonNow->format('Y-m-d H:i:s'),
                'quantity' => $data['quantity'],
                'cost' => $data['cost'],
                'source' => $data['source'],
                'type' => $data['type'],
            ]);

            $saleDateYmd = $carbonNow->format('Y-m-d');
            Log::info('CREATE SALE - CARD ' . $data['card_id'] . ' DATE ' . $saleDateYmd . ' ++++++++++++');
//            $cardAllTimestamps = CardSales::where('card_id', $data['card_id'])->groupBy(DB::raw('DATE(timestamp)'))->orderBy('timestamp', 'DESC')->pluck('timestamp');
//            $latestTimestamp = Carbon::create($cardAllTimestamps[0])->format('Y-m-d');
//            if($saleDateYmd >= $latestTimestamp) {
            $cardSXExistingvalue = 0;
            if (CardsSx::where('card_id', $data['card_id'])->exists()) {
                $cardSXExisting = CardsSx::where('card_id', $data['card_id'])->orderBy('date', 'DESC')->first();
                $cardExistingLatestSaleDate = $cardSXExisting->date;
                $cardSXExistingvalue = $cardSXExisting->sx;
            }
//            }

            $cardsSxValue = CardSales::where('card_id', $data['card_id'])->where('timestamp', 'like', '%' . $saleDateYmd . '%')->get();
            if (CardsSx::where('card_id', $data['card_id'])->where('date', $saleDateYmd)->exists()) {
                CardsSx::where('card_id', $data['card_id'])->where('date', $saleDateYmd)->update(['sx' => $cardsSxValue->avg('cost'), 'quantity' => $cardsSxValue->sum('quantity')]);
                Log::info('CardsSx ID ' . $data['card_id'] . ' SX ' . $cardsSxValue->avg('cost') . ' quantity ' . $cardsSxValue->sum('quantity'));
            } else {
                CardsSx::create([
                    'card_id' => $data['card_id'],
                    'date' => $saleDateYmd,
                    'sx' => $data['cost'],
                    'quantity' => $data['quantity'],
                ]);
                Log::info('CardsSx ID ' . $data['card_id'] . ' SX ' . $data['cost'] . ' quantity ' . $data['quantity']);
            }

            if (CardsTotalSx::where('date', $saleDateYmd)->exists()) {
                $sxValue = CardSales::where('timestamp', 'like', '%' . $saleDateYmd . '%')->get();
                CardsTotalSx::where('date', $saleDateYmd)->update(['amount' => $sxValue->avg('cost'), 'quantity' => $sxValue->sum('quantity')]);
                Log::info('CardsTotalSx amount ' . $sxValue->avg('cost') . ' quantity ' . $sxValue->sum('quantity'));
            } else {
                CardsTotalSx::create([
                    'date' => $saleDateYmd,
                    'amount' => $data['cost'],
                    'quantity' => $data['quantity'],
                ]);
                Log::info('CardsTotalSx amount ' . $data['cost'] . ' quantity ' . $data['quantity']);
            }

if(isset($cardExistingLatestSaleDate)){
            if ($saleDateYmd >= $cardExistingLatestSaleDate) {
                $old_total_sx_value = AppSettings::select('total_sx_value')->first();
                $changed_total_sx_value = ($old_total_sx_value->total_sx_value - $cardSXExistingvalue) + $cardsSxValue->avg('cost');

                AppSettings::first()->update(["total_sx_value" => $changed_total_sx_value]);
                Log::info('AppSettings total_sx_value ' . $changed_total_sx_value);
            }
}
            $current_card_id = $data['card_id'];
            $days = config('constant.days');
            foreach ($days as $daykey => $day) {
                if (($carbonNow >= $day['to']) && ($carbonNow <= $day['from'])) {
                    $checkSalesCount = CardSales::where('card_id', $current_card_id)->whereBetween('timestamp', [$day['to'], $day['from']])->count();
                    $name = 'trenders_' . $daykey . '_' . strtolower($cardSport[0]);
                    $value = Cache::get($name);
                    if ($value != null && !empty($value) && count($value) > 0) {
                        if ($checkSalesCount >= 3) {
                            $flag = 0;
                            foreach ($value as $key => $val) {
                                if (isset($val['id']) && $current_card_id == $val['id']) {
                                    $sx_data = CardSales::getSxAndOldestSx($current_card_id, $day['to'], $day['from'], $day['daysForSx']);
                                    $sx = $sx_data['sx'];
                                    $lastSx = $sx_data['oldestSx'];
                                    $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';

                                    $value[$key]['price'] = number_format((float) $sx, 2, '.', '');
                                    $value[$key]['sx_value_signed'] = (float) $sx - $lastSx;
                                    $value[$key]['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                                    $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                                    $value[$key]['sx_percent_signed'] = $sx_percent;
                                    $value[$key]['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                                    $value[$key]['sx_icon'] = $sx_icon;
                                    $value[$key]['sale_qty'] = CardSales::where('card_id', $current_card_id)->sum('quantity');
                                    $flag = 1;
                                    Log::info('Cache >= 3 ID ' . $current_card_id . ' SX ' . $sx . ' LASTSX ' . $lastSx);
                                    break;
                                }
                            }
                            if ($flag == 1) {
                                Cache::put($name, $value);
                                Log::info('Cache >= 3 added');
                            }
                        } elseif ($checkSalesCount == 2) {
                            $flag = 0;
                            $cards = Card::where('id', $current_card_id)->with('details')->first();
                            if (!empty($cards)) {
                                $sx_data = CardSales::getSxAndOldestSx($cards->id, $day['to'], $day['from'], $day['daysForSx']);
                                $sx = $sx_data['sx'];
                                $lastSx = $sx_data['oldestSx'];
                                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                                $cards['price'] = number_format((float) $sx, 2, '.', '');
                                $cards['sx_value_signed'] = (float) $sx - $lastSx;
                                $cards['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                                $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                                $cards['sx_percent_signed'] = $sx_percent;
                                $cards['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                                $cards['sx_icon'] = $sx_icon;
                                $cards['sale_qty'] = CardSales::where('card_id', $cards->id)->sum('quantity');
                                Log::info('Cache == 2 ID ' . $current_card_id . ' SX ' . $sx . ' LASTSX ' . $lastSx);
                                $flag = 1;
                            }
                            if ($flag == 1) {
                                $value[] = $cards;
                                Cache::put($name, $value);
                                Log::info('Cache == 2 added');
                            }
                        }
                    } else if ($checkSalesCount >= 2) {
                        $cards = Card::where('id', $current_card_id)->with('details')->first();
                        if (!empty($cards)) {
                            $sx_data = CardSales::getSxAndOldestSx($cards->id, $day['to'], $day['from'], $day['daysForSx']);
                            $sx = $sx_data['sx'];
                            $lastSx = $sx_data['oldestSx'];
                            $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                            $cards['price'] = number_format((float) $sx, 2, '.', '');
                            $cards['sx_value_signed'] = (float) $sx - $lastSx;
                            $cards['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                            $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                            $cards['sx_percent_signed'] = $sx_percent;
                            $cards['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                            $cards['sx_icon'] = $sx_icon;
                            $cards['sale_qty'] = CardSales::where('card_id', $cards->id)->sum('quantity');
                            $value[] = $cards;
                            Cache::put($name, $value);
                            Log::info('Cache also added >= 2 ID ' . $current_card_id . ' SX ' . $sx . ' LASTSX ' . $lastSx);
                        }
                    }
                }
            }

            $value = Cache::get('trenders_all_cards');
            if ($value != null && !empty($value) && count($value) > 0) {
                foreach ($value as $key => $val) {
                    if ($current_card_id == $val['id']) {
                        $checkSalesCount = CardSales::where('card_id', $current_card_id)->count();
                        if ($checkSalesCount >= 2) {
                            $sx_data = CardSales::getSxAndOldestSx($current_card_id);
                            $sx = $sx_data['sx'];
                            $lastSx = $sx_data['oldestSx'];
                            $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';

                            $value[$key]['price'] = number_format((float) $sx, 2, '.', '');
                            $value[$key]['sx_value_signed'] = (float) $sx - $lastSx;
                            $value[$key]['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                            $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                            $value[$key]['sx_percent_signed'] = $sx_percent;
                            $value[$key]['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                            $value[$key]['sx_icon'] = $sx_icon;
                            $value[$key]['sale_qty'] = CardSales::where('card_id', $current_card_id)->sum('quantity');
                            Log::info('T Cache ID ' . $current_card_id . ' SX ' . $sx . ' lastSX ' . $lastSx);
                            break;
                        }
                    }
                }
                Cache::put('trenders_all_cards', $value);
            }
            Log::info('+++++++++++++');
            \DB::commit();
            return response()->json(['status' => 200, 'message' => 'Sales added successfully.'], 200);
        } catch (\Exception $e) {
            \DB::rollback();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getSalesList(Request $request) {
        try {
            $data = CardSales::where('card_id', $request->input('card_id'))->get();
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getSalesEdit($sale_id) {
        try {
            $data = CardSales::where('id', $sale_id)->first();
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function editSalesData(Request $request) {
        \DB::beginTransaction();
        try {
            $data = $request->all();
            $cardId = $data['card_id'];
            $requestedDate = Carbon::create($data['timestamp'])->format('Y-m-d');
            $existedCardSale = CardSales::where('id', $data['id'])->first();
            $existingSaleDate = Carbon::create($existedCardSale->timestamp)->format('Y-m-d');
            $cardSXExistingDetails = CardsSx::where('card_id', $cardId)->orderBy('date', 'DESC')->first();
            $latestSaleDate = $cardSXExistingDetails->date;
            $cardSXExisting = $cardSXExistingDetails->sx;
            $cardSport = Card::where('id', $existedCardSale->card_id)->pluck('sport');

            $runCode = 0;
            if ($existingSaleDate != $requestedDate || $existedCardSale->cost != $data['cost']) {
                $runCode = 1;
            } else if ($existingSaleDate == $requestedDate && $existedCardSale->cost == $data['cost'] && $existedCardSale->quantity != $data['quantity']) {
                $runCode = 2;
            }

            CardSales::where('id', $data['id'])->update([
                'timestamp' => Carbon::create($data['timestamp'])->format('Y-m-d H:i:s'),
                'quantity' => $data['quantity'],
                'cost' => $data['cost'],
                'source' => $data['source'],
                'type' => $data['type'],
            ]);
            Log::info('SALE EDIT - CARD ' . $cardId . 'DATE ' . $data['timestamp'] . ' ++++++++++++');
            if ($runCode == 2) {
                $updatedCardsQty = CardSales::where('card_id', $cardId)->where('timestamp', 'like', '%' . $requestedDate . '%')->sum('quantity');
                CardsSx::where('card_id', $cardId)->where('date', $requestedDate)->update(['quantity' => $updatedCardsQty]);
                $updatedTotalCardQty = CardSales::where('timestamp', 'like', '%' . $requestedDate . '%')->sum('quantity');
                CardsTotalSx::where('date', $requestedDate)->update(['quantity' => $updatedTotalCardQty]);

                if ($requestedDate != $existingSaleDate) {
                    if (CardSales::where('card_id', $cardId)->where('timestamp', 'like', '%' . $existingSaleDate . '%')->exists()) {
                        $updatedExistingCardQty = CardSales::where('card_id', $cardId)->where('timestamp', 'like', '%' . $existingSaleDate . '%')->sum('quantity');
                        CardsSx::where('card_id', $cardId)->where('date', $existingSaleDate)->update(['quantity' => $updatedExistingCardQty]);
                    } else {
                        CardsSx::where('card_id', $cardId)->where('date', $existingSaleDate)->forceDelete();
                    }
                    if (CardSales::where('timestamp', 'like', '%' . $existingSaleDate . '%')->exists()) {
                        $updatedExistingTotalCardQty = CardSales::where('timestamp', 'like', '%' . $existingSaleDate . '%')->sum('quantity');
                        CardsTotalSx::where('date', $existingSaleDate)->update(['quantity' => $updatedExistingTotalCardQty]);
                    } else {
                        CardsTotalSx::where('date', $existingSaleDate)->forceDelete();
                    }
                    Log::info('runCode 2 CardsSx CardsTotalSx F DELETE');
                }
            } else if ($runCode == 1) {
                $updatedCardsSx = CardSales::where('card_id', $cardId)->where('timestamp', 'like', '%' . $requestedDate . '%')->get();
                CardsSx::where('card_id', $cardId)->where('date', $requestedDate)->update(['sx' => $updatedCardsSx->avg('cost'), 'quantity' => $updatedCardsSx->sum('quantity')]);
                $updatedSx = CardSales::where('timestamp', 'like', '%' . $requestedDate . '%')->get();
                CardsTotalSx::where('date', $requestedDate)->update(['amount' => $updatedSx->avg('cost'), 'quantity' => $updatedSx->sum('quantity')]);

                if ($requestedDate != $existingSaleDate) {
                    if (CardSales::where('card_id', $cardId)->where('timestamp', 'like', '%' . $existingSaleDate . '%')->exists()) {
                        $updatedExistingSx = CardSales::where('card_id', $cardId)->where('timestamp', 'like', '%' . $existingSaleDate . '%')->get();
                        CardsSx::where('card_id', $cardId)->where('date', $existingSaleDate)->update(['sx' => $updatedExistingSx->avg('cost'), 'quantity' => $updatedExistingSx->sum('quantity')]);
                    } else {
                        CardsSx::where('card_id', $cardId)->where('date', $existingSaleDate)->forceDelete();
                    }
                    if (CardSales::where('timestamp', 'like', '%' . $existingSaleDate . '%')->exists()) {
                        $updatedExistingTotalSx = CardSales::where('timestamp', 'like', '%' . $existingSaleDate . '%')->get();
                        CardsTotalSx::where('date', $existingSaleDate)->update(['amount' => $updatedExistingTotalSx->avg('cost'), 'quantity' => $updatedExistingTotalSx->sum('quantity')]);
                    } else {
                        CardsTotalSx::where('date', $existingSaleDate)->forceDelete();
                    }
                }
                Log::info('runCode 1 CardsSx CardsTotalSx F DELETE');

                if ($requestedDate >= $latestSaleDate) {
                    $old_total_sx_value = AppSettings::select('total_sx_value')->first();
                    $changed_total_sx_value = ($old_total_sx_value->total_sx_value - $cardSXExisting) + $updatedCardsSx->avg('cost');
                    AppSettings::first()->update(["total_sx_value" => $changed_total_sx_value]);
                    Log::info('AppSettings total_sx_value ' . $changed_total_sx_value);
                }
                $current_card_id = $cardId;
                $days = config('constant.days');
                if ($requestedDate == $existingSaleDate) {
                    foreach ($days as $daykey => $day) {
                        $name = 'trenders_' . $daykey . '_' . strtolower($cardSport[0]);
                        if (($requestedDate >= $day['to']) && ($requestedDate <= $day['from'])) {
                            $value = Cache::get($name);
                            if ($value != null && !empty($value) && count($value) > 0) {
                                $flag = 0;
                                foreach ($value as $key => $val) {
                                    if (isset($val['id']) && $cardId == $val['id']) {
                                        $sx_data = CardSales::getSxAndOldestSx($cardId, $day['to'], $day['from'], $day['daysForSx']);
                                        $sx = $sx_data['sx'];
                                        $lastSx = $sx_data['oldestSx'];
                                        $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';

                                        $value[$key]['price'] = number_format((float) $sx, 2, '.', '');
                                        $value[$key]['sx_value_signed'] = (float) $sx - $lastSx;
                                        $value[$key]['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                                        $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                                        $value[$key]['sx_percent_signed'] = $sx_percent;
                                        $value[$key]['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                                        $value[$key]['sx_icon'] = $sx_icon;
                                        $value[$key]['sale_qty'] = CardSales::where('card_id', $cardId)->sum('quantity');
                                        $flag = 1;
                                        Log::info('Cache == dates ID ' . $cardId . ' SX ' . $sx . ' LASTSX ' . $lastSx);
                                        break;
                                    }
                                }
                                if ($flag == 1) {
                                    Cache::put($name, $value);
                                }
                            }
                        }
                    }
                } else {
                    foreach ($days as $daykey => $day) {
                        $name = 'trenders_' . $daykey . '_' . strtolower($cardSport[0]);
                        if (($existingSaleDate >= $day['to']) && ($existingSaleDate <= $day['from'])) {
                            $value = Cache::get($name);
                            if ($value != null && !empty($value) && count($value) > 0) {
                                $flag = 0;
                                foreach ($value as $key => $val) {
                                    if (isset($val['id']) && $cardId == $val['id']) {
                                        $checkSalesCount = CardSales::where('card_id', $current_card_id)->whereBetween('timestamp', [$day['to'], $day['from']])->count();
                                        if ($checkSalesCount >= 2) {
                                            $sx_data = CardSales::getSxAndOldestSx($current_card_id, $day['to'], $day['from'], $day['daysForSx']);
                                            $sx = $sx_data['sx'];
                                            $lastSx = $sx_data['oldestSx'];
                                            $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';

                                            $value[$key]['price'] = number_format((float) $sx, 2, '.', '');
                                            $value[$key]['sx_value_signed'] = (float) $sx - $lastSx;
                                            $value[$key]['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                                            $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                                            $value[$key]['sx_percent_signed'] = $sx_percent;
                                            $value[$key]['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                                            $value[$key]['sx_icon'] = $sx_icon;
                                            $value[$key]['sale_qty'] = CardSales::where('card_id', $current_card_id)->sum('quantity');
                                            $flag = 1;
                                            $cardKey = $key;
                                            Log::info('Cache >= 2 ID ' . $cardId . ' SX ' . $sx . ' LASTSX ' . $lastSx);
                                            break;
                                        } else {
                                            unset($value[$key]);
                                            Log::info('CACHE unset >= 2');
                                            $flag = 1;
                                            break;
                                        }
                                    }
                                }
                                if ($flag == 1) {
                                    Cache::put($name, $value);
                                }
                            }
                        }
                        if (($requestedDate >= $day['to']) && ($requestedDate <= $day['from'])) {
                            $value = Cache::get($name);
                            $cards = Card::whereHas('sales', function($q) use($day, $cardSport, $requestedDate) {
                                        $q->whereBetween('timestamp', [$day['to'], $day['from']]);
                                    }, '>=', 2)->where('id', $cardId)->where('sport', strtolower($cardSport[0]))->where('active', 1)->with('details')->first();

                            if (!empty($cards)) {
                                $sx_data = CardSales::getSxAndOldestSx($cards->id, $day['to'], $day['from'], $day['daysForSx']);
                                $sx = $sx_data['sx'];
                                $lastSx = $sx_data['oldestSx'];
                                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                                $cards['price'] = number_format((float) $sx, 2, '.', '');
                                $cards['sx_value_signed'] = (float) $sx - $lastSx;
                                $cards['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                                $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                                $cards['sx_percent_signed'] = $sx_percent;
                                $cards['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                                $cards['sx_icon'] = $sx_icon;
                                $cards['sale_qty'] = CardSales::where('card_id', $cards->id)->sum('quantity');
                                if (isset($cardKey) && $flag = 1) {
                                    $value[$cardKey] = $cards;
                                } else {
                                    $value[] = $cards;
                                }
                                Cache::put($name, $value);
                                Log::info('Cache dayToFrom ID ' . $cards->id . ' SX ' . $sx . ' LASTSX ' . $lastSx);
                            }
                        }
                    }
                }

                $value = Cache::get('trenders_all_cards');
                if ($value != null && !empty($value) && count($value) > 0) {
                    foreach ($value as $key => $val) {
                        if (isset($val['id']) && $cardId == $val['id']) {
                            $checkSalesCount = CardSales::where('card_id', $current_card_id)->count();
                            if ($checkSalesCount >= 2) {
                                $sx_data = CardSales::getSxAndOldestSx($cardId);
                                $sx = $sx_data['sx'];
                                $lastSx = $sx_data['oldestSx'];
                                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                                $value[$key]['price'] = number_format((float) $sx, 2, '.', '');
                                $value[$key]['sx_value_signed'] = (float) $sx - $lastSx;
                                $value[$key]['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                                $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                                $value[$key]['sx_percent_signed'] = $sx_percent;
                                $value[$key]['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                                $value[$key]['sx_icon'] = $sx_icon;
                                Log::info('T Cache ID ' . $cardId . ' SX ' . $sx . ' lastSX ' . $lastSx);
                                break;
                            }
                        }
                    }
                    Cache::put('trenders_all_cards', $value);
                }
            }
            Log::info('+++++++++++++');
            \DB::commit();
            return response()->json(['status' => 200, 'message' => 'Sales edited successfully.'], 200);
        } catch (\Exception $e) {
            \DB::rollback();
            return response()->json($e->getMessage(), 500);
        }
    }

//    private function __uploadSalesSx($cardId, $timestamp, $cost) {
//        if (CardsSx::where('card_id', $cardId)
//                        ->where('date', Carbon::create($timestamp)->format('Y-m-d'))
//                        ->exists()) {
//            $existedSales = CardSales::where("card_id", $cardId)
//                    ->where('timestamp', 'like', '%' . Carbon::create($timestamp)->format('Y-m-d') . '%')
//                    ->avg('cost');
//
//            CardsSx::where("card_id", $cardId)
//                    ->where('date', Carbon::create($timestamp)->format('Y-m-d'))
//                    ->update(["sx" => $existedSales]);
//        } else {
//            CardsSx::Create([
//                "card_id" => $cardId,
//                "date" => Carbon::create($timestamp)->format('Y-m-d'),
//                "sx" => $cost,
//            ]);
//        }
//    }

    public function getStoxtickerData() {
        try {
            $data = ['total' => 0, 'sale' => 0, 'avg_sale' => 0, 'change' => 0, 'change_arrow' => 'up', 'last_updated' => ''];
//            $data['total'] = Card::count();
            $data['total'] = Card::where('active', 1)->count();
            $total = AppSettings::first();
            $data['sale'] = $total['total_sx_value'];
            $data['last_updated'] = 'N/A';
            $last_updated = CardSales::orderBy('timestamp', 'DESC')->first();
            if (!empty($last_updated)) {
                $data['last_updated'] = Carbon::create($last_updated->timestamp)->format('F d Y \- h:i:s A');
            }
            $salesDate = CardsTotalSx::groupBy(DB::raw('DATE(date)'))->orderBy('date', 'DESC')->take(2)->get();
            $count = $salesDate->count();
            if ($count >= 2) {
                $sx_data['sx'] = $salesDate[0]->amount;
                $sx_data['lastSx'] = $salesDate[1]->amount;
            } elseif ($count == 1) {
                $sx_data['sx'] = $salesDate[0]->amount;
                $sx_data['lastSx'] = 0;
            } else {
                $sx_data['sx'] = 0;
                $sx_data['lastSx'] = 0;
            }

            $sx = $sx_data['sx'];
            $lastSx = $sx_data['lastSx'];
            $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
            $diff = abs($sx - $lastSx);
            $percent_diff = ($lastSx > 0 ? (($diff / $lastSx) * 100) : 0 );
            $data['change'] = number_format(abs($percent_diff), 2, '.', '');
            $data['change_arrow'] = $sx_icon;
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getDashboardGraphData($days = 2, $card_id = 0) {
        try {
            if ($days == 2) {
                $grpFormat = 'H:i';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d 00:00:00');
            } elseif ($days == 7) {
                $grpFormat = 'Y-m-d';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-8 days'));
            } elseif ($days == 30) {
                $grpFormat = 'Y-m-d';
                $lblFormat = 'H:i';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-30 days'));
            } elseif ($days == 90) {
                $grpFormat = 'Y-m';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-90 days'));
            } elseif ($days == 180) {
                $grpFormat = 'Y-m';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-180 days'));
            } elseif ($days == 365) {
                $grpFormat = 'Y-m';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-365 days'));
            } elseif ($days == 1825) {
                $grpFormat = 'Y';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-1825 days'));
            }

            $data = ['values' => [], 'labels' => []];

            if ($grpFormat == 'H:i') {
                $cvs = CardSales::where('card_id', $card_id)->whereBetween('timestamp', [$to, $from])->orderBy('timestamp', 'DESC')->get()->groupBy(function ($cs) use ($grpFormat) {
                            return Carbon::parse($cs->timestamp)->floorMinutes(5)->format($grpFormat);
                        })->map(function ($cs, $timestamp) use ($grpFormat, $days) {
                    return [
                    'cost' => round((clone $cs)->avg('cost'), 2),
                    'timestamp' => $timestamp,
                    'quantity' => $cs->map(function ($qty) {
                    return (int) $qty->quantity;
                    })->sum()
                    ];
                });
                $data['values'] = $cvs->pluck('cost')->toArray();
                $data['labels'] = $cvs->pluck('timestamp')->toArray();
                $data['qty'] = $cvs->pluck('quantity')->toArray();
            } else {
                $data = $this->__getSxAllData($days, $to, $from, $card_id);
            }
            if ($days == 2) {
                $labels = [];
                $values = [];
                $qty = [];
                $sx = 0;
                $ind = null;
                $startTime = new \DateTime($to);
                $endTime = new \DateTime($from);
                $timeStep = 5;
                $timeArray = array();
                $previousSx = 0;
                $flag = 0;
                while ($startTime <= $endTime) {
                    $hi = $startTime->format('H:i');
                    if (count($data['labels']) > 0) {
                        $ind = array_search($hi, $data['labels']);
                        if (is_numeric($ind)) {
                            $values[] = $data['values'][$ind];
                            $qty[] = $data['qty'][$ind];
                            $previousSx = $data['values'][$ind];
                            $flag = 1;
                        } else {
                            if ($previousSx == 0 && $flag == 0) {
                                $salesDate = CardSales::where('card_id', $card_id)->where('timestamp', '<', $to)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                                if ($salesDate !== null) {
                                    $previousSx = CardSales::where('card_id', $card_id)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                                    $values[] = number_format($previousSx, 2, '.', '');
                                    $qty[] = 0;
                                }
                                $flag = 1;
                            } else {
                                $values[] = number_format($previousSx, 2, '.', '');
                                $qty[] = 0;
                            }
                        }
                    } else {
                        if ($previousSx == 0 && $flag == 0) {
                            $salesDate = CardSales::where('card_id', $card_id)->where('timestamp', '<', $to)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                            if ($salesDate !== null) {
                                $previousSx = CardSales::where('card_id', $card_id)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                                $values[] = number_format($previousSx, 2, '.', '');
                                $qty[] = 0;
                            }
                            $flag = 1;
                        } else {
                            $values[] = number_format($previousSx, 2, '.', '');
                            $qty[] = 0;
                        }
                    }
                    $timeArray[] = $hi;
//                    $startTime->add(new \DateInterval('PT' . $timeStep . 'M'));
                }
                $data['values'] = $values;
                $data['qty'] = $qty;
                $data['labels'] = $timeArray;
            } else {
                $data['values'] = array_reverse($data['values']);
                $data['labels'] = array_reverse($data['labels']);
                $data['qty'] = array_reverse($data['qty']);
            }
            if ($days == 90) {
                $data['last_timestamp'] = '';
                $last_timestamp = CardSales::where('card_id', $card_id)->orderBy('timestamp', 'DESC')->first();
                if (!empty($last_timestamp)) {
                    $data['last_timestamp'] = Carbon::create($last_timestamp->timestamp)->format('F d Y \- h:i:s A');
                }
            }
            $sx_data = CardSales::getGraphSxWithCardId($days, $data);
            $sx = $sx_data['sx'];
            $lastSx = $sx_data['lastSx'];
            if (!empty($sx_data)) {
                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                $data['doller_diff'] = str_replace('-', '', number_format((float) ($sx - $lastSx), 2, '.', ''));
                $perc_diff = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                $data['perc_diff'] = str_replace('-', '', number_format($perc_diff, 2, '.', ''));
                $data['sx_icon'] = $sx_icon;
            } else {
                $data['doller_diff'] = 0;
                $data['perc_diff'] = 0;
                $data['sx_icon'] = '';
            }
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    private function __getSxAllData($days, $to, $from, $card_id) {
        $to = Carbon::create($to)->format('Y-m-d');
        $from = Carbon::create($from)->format('Y-m-d');
        $cvs = CardsSx::where('card_id', $card_id)->whereBetween('date', [$to, $from])->orderBy('date', 'DESC')->get();
        $data['values'] = $cvs->pluck('sx')->toArray();
        $data['labels'] = $cvs->pluck('date')->toArray();
        $data['qty'] = $cvs->pluck('quantity')->toArray();
        if ($days > 2) {
            $data = $this->__groupGraphDataPerDay($days, $data, $card_id);
        }
        return $data;
    }

    public function getStoxtickerAllData($days = 2) {
        try {
            if ($days == 2) {
                $grpFormat = 'H:i';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-1 day'));
            } elseif ($days == 7) {
                $grpFormat = 'Y-m-d';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-8 days'));
            } elseif ($days == 30) {
                $grpFormat = 'Y-m-d';
                $lblFormat = 'H:i';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-30 days'));
            } elseif ($days == 90) {
                $grpFormat = 'Y-m';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-90 days'));
            } elseif ($days == 180) {
                $grpFormat = 'Y-m';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-180 days'));
            } elseif ($days == 365) {
                $grpFormat = 'Y-m';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-365 days'));
            } elseif ($days == 1825) {
                $grpFormat = 'Y';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-1825 days'));
            }

            $data = ['values' => [], 'labels' => []];
            // $cvs = CardSales::groupBy('timestamp')->orderBy('timestamp', 'DESC')->limit($days);
            $cvs = CardSales::whereBetween('timestamp', [$to, $from])->orderBy('timestamp', 'DESC')->get()->groupBy(function ($cs) use ($grpFormat) {
                        return Carbon::parse($cs->timestamp)->format($grpFormat);
                    })->map(function ($cs, $timestamp) use ($grpFormat, $days) {
                return [
                'cost' => round((clone $cs)->avg('cost'), 2),
                'timestamp' => Carbon::createFromFormat($grpFormat, $timestamp)->format($days == 2 ? 'H:i' : $grpFormat),
                'quantity' => $cs->map(function ($qty) {
                return (int) $qty->quantity;
                })->sum()
                ];
            });
            $data['values'] = $cvs->pluck('cost')->toArray();
            $data['labels'] = $cvs->pluck('timestamp')->toArray();
            $data['qty'] = $cvs->pluck('quantity')->toArray();
            // dd($data);

            if ($days > 2) {
                $data = $this->__groupGraphData($days, $data);
            }
            if ($days == 2) {
                $labels = [];
                $values = [];
                $qty = [];
                for ($i = 0; $i <= 23; $i++) {
                    $labels[] = ($i < 10) ? '0' . $i . ':00' : $i . ':00';
                    $ind = array_search($labels[$i], $data['labels']);
                    $values[] = is_integer($ind) && array_key_exists($ind, $data['values']) ? $data['values'][$ind] : 0;
                    $qty[] = is_integer($ind) && array_key_exists($ind, $data['qty']) ? $data['qty'][$ind] : 0;
                    // (count($data['qty']) > 0 ) ? $data['qty'][0] : 0;
                }
                $data['labels'] = $labels;
                $data['values'] = $values;
                $data['qty'] = $qty;
            } else {
                $data['values'] = array_reverse($data['values']);
                $data['labels'] = array_reverse($data['labels']);
                $data['qty'] = array_reverse($data['qty']);
            }
//            $sales_diff = CardSales::orderBy('timestamp', 'DESC')->take(2)->get();
            $last_timestamp = CardSales::orderBy('timestamp', 'DESC')->first();
//            $sx = $sales_diff = CardSales::orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
//            $sx = CardSales::orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
//            $sx_count = count($sx);
//            $sx = $sales_diff = ($sx_count > 0) ? array_sum($sx->toArray()) / $sx_count : 0;

            $sx_data = CardSales::getSlabstoxSxGraph($days, $data);
            $sx = $sx_data['sx'];
            $lastSx = $sx_data['oldestSx'];

            if (!empty($sx_data) && !empty($last_timestamp)) {
//                $lastSx = CardSales::orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
//                $count = count($lastSx);
//                $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;
                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                $data['doller_diff'] = str_replace('-', '', number_format((float) ($sx - $lastSx), 2, '.', ''));

                $pert_diff = ($lastSx > 0 ? ((($sx - $lastSx) / $lastSx) * 100) : 0);
                $data['perc_diff'] = number_format(abs($pert_diff), 2, '.', '');
                $data['last_timestamp'] = Carbon::create($last_timestamp->timestamp)->format('F d Y \- h:i:s A');
                $data['change_arrow'] = $sx_icon;
            } else {
                $data['doller_diff'] = 0;
                $data['perc_diff'] = 0;
                $data['last_timestamp'] = '';
                $data['change_arrow'] = '';
            }
            $data['total_sales'] = number_format(CardSales::leftJoin('cards', 'cards.id', '=', 'card_sales.card_id')->where('cards.deleted_at', null)->orderBy('timestamp', 'DESC')->select('card_sales.card_id', 'card_sales.cost')->get()->groupBy('card_id')->map(function ($cs) {
                        return ['avg' => $cs->splice(0, 3)->avg('cost')];
                    })->sum('avg'), 2, '.', '');
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getCardGraphData($card_id, $days = 2) {
        try {
            $cids = explode('|', (string) $card_id);
            $temData = [];

            if ($days == 2) {
                $grpFormat = 'H:i';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d 00:00:00');
            } elseif ($days == 7) {
                $grpFormat = 'Y-m-d';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-8 days'));
            } elseif ($days == 30) {
                $grpFormat = 'Y-m-d';
                $lblFormat = 'H:i';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-30 days'));
            } elseif ($days == 90) {
                $grpFormat = 'Y-m';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-90 days'));
            } elseif ($days == 180) {
                $grpFormat = 'Y-m';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-180 days'));
            } elseif ($days == 365) {
                $grpFormat = 'Y-m';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-365 days'));
            } elseif ($days == 1825) {
                $grpFormat = 'Y';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-1825 days'));
            }

            foreach ($cids as $ind => $cid) {

                if ($grpFormat == 'H:i') {

                    $cvs = CardSales::where('card_id', $cid)->whereBetween('timestamp', [$to, $from])->orderBy('timestamp', 'DESC')->get()->groupBy(function ($cs) use ($grpFormat) {
                                return Carbon::parse($cs->timestamp)->format($grpFormat);
                            })->map(function ($cs, $timestamp) use ($grpFormat, $days) {
                        return [
                        'cost' => round((clone $cs)->avg('cost'), 2),
                        'timestamp' => $timestamp,
                        'quantity' => $cs->map(function ($qty) {
                        return (int) $qty->quantity;
                        })->sum()
                        ];
                    });
                } else {
                    $cvs = CardSales::where('card_id', $cid)->whereBetween('timestamp', [$to, $from])->orderBy('timestamp', 'DESC')->get()->groupBy(function ($cs) use ($grpFormat) {
                                return Carbon::parse($cs->timestamp)->format('Y-m-d');
                            })->map(function ($cs, $timestamp) use ($grpFormat, $days) {
                        return [
                        'cost' => round((clone $cs)->avg('cost'), 2),
                        'timestamp' => $timestamp,
                        'quantity' => $cs->map(function ($qty) {
                        return (int) $qty->quantity;
                        })->sum()
                        ];
                    });
                }


                $views = Card::where('id', $cid)->pluck('views');
                $view = ($views[0] == null) ? $view = 1 : $view = $views[0] + 1;
                (Card::where('id', $cid)->first())->update(['views' => $view]);

                $data['values'] = $cvs->pluck('cost')->toArray();
                $data['labels'] = $cvs->pluck('timestamp')->toArray();
                $data['qty'] = $cvs->pluck('quantity')->toArray();

                if ($days > 2) {
                    $data = $this->__groupGraphDataPerDay($days, $data, $cid);
                }
                if ($days == 2) {
                    $labels = [];
                    $values = [];
                    $qty = [];
                    $sx = 0;
                    $ind = null;

                    $startTime = new \DateTime($to);
                    $endTime = new \DateTime($from);
                    $timeStep = 5;
                    $timeArray = array();
                    $previousSx = 0;
                    $flag = 0;
                    while ($startTime <= $endTime) {
                        $hi = $startTime->format('H:i');
                        if (count($data['labels']) > 0) {
                            $ind = array_search($hi, $data['labels']);
                            if (is_numeric($ind)) {
                                $values[] = $data['values'][$ind];
                                $qty[] = $data['qty'][$ind];
                                $previousSx = $data['values'][$ind];
                                $flag = 1;
                            } else {
                                if ($previousSx == 0 && $flag == 0) {
                                    $salesDate = CardSales::where('card_id', $cid)->where('timestamp', '<', $to)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                                    if ($salesDate !== null) {
                                        $previousSx = CardSales::where('card_id', $cid)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                                        $values[] = number_format($previousSx, 2, '.', '');
                                        $qty[] = 0;
                                    }
                                    $flag = 1;
                                } else {
                                    $values[] = number_format($previousSx, 2, '.', '');
                                    $qty[] = 0;
                                }
                            }
                        } else {
                            if ($previousSx == 0 && $flag == 0) {
                                $salesDate = CardSales::where('card_id', $cid)->where('timestamp', '<', $to)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                                if ($salesDate !== null) {
                                    $previousSx = CardSales::where('card_id', $cid)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                                    $values[] = number_format($previousSx, 2, '.', '');
                                    $qty[] = 0;
                                }
                                $flag = 1;
                            } else {
                                $values[] = number_format($previousSx, 2, '.', '');
                                $qty[] = 0;
                            }
                        }
                        $timeArray[] = $hi;
                        $startTime->add(new \DateInterval('PT' . $timeStep . 'M'));
                    }
                    $data['values'] = $values;
                    $data['qty'] = $qty;
                    $data['labels'] = $timeArray;
                } else {
                    $data['values'] = array_reverse($data['values']);
                    $data['labels'] = array_reverse($data['labels']);
                    $data['qty'] = array_reverse($data['qty']);
                }
                array_push($temData, $data);
                // $temData[$ind] = $data;
            }

            $finalData['values1'] = $temData[0]['values'];
            $finalData['lable1'] = $temData[0]['labels'];
            $finalData['qty1'] = $temData[0]['qty'];
            $finalData['values2'] = $temData[1]['values'];
            $finalData['labels2'] = $temData[1]['labels'];
            $finalData['qty2'] = $temData[1]['qty'];

            if ($days == 90) {
                $finalData['rank1'] = $this->getCardRank($cids[0]);
                $finalData['rank2'] = $this->getCardRank($cids[1]);
            }

            if ($days == 90) {
                $sx_data00 = CardSales::getSx($cids[0]);
                $finalData['slabstoxvalue1'] = number_format((float) $sx_data00['sx'], 2, '.', '');
            }

            $sx_data0 = CardSales::getGraphSxWithCardId($days, $temData[0]);
            $finalData['sx1'] = number_format((float) $sx_data0['sx'], 2, '.', '');
            if ($days == 90) {
                $sx_data11 = CardSales::getSx($cids[1]);
                $finalData['slabstoxvalue2'] = number_format((float) $sx_data11['sx'], 2, '.', '');
            }
            $sx_data1 = CardSales::getGraphSxWithCardId($days, $temData[1]);
            $finalData['sx2'] = number_format((float) $sx_data1['sx'], 2, '.', '');
            if ($days == 90) {
                $finalData['last_sale1'] = CardSales::where('card_id', $cids[0])->orderBy('timestamp', 'DESC')->first();
                $finalData['last_sale2'] = CardSales::where('card_id', $cids[1])->orderBy('timestamp', 'DESC')->first();
                $finalData['high_sale1'] = CardSales::where('card_id', $cids[0])->orderBy('cost', 'DESC')->first();
                $finalData['high_sale2'] = CardSales::where('card_id', $cids[1])->orderBy('cost', 'DESC')->first();
                $finalData['low_sale1'] = CardSales::where('card_id', $cids[0])->orderBy('cost', 'ASC')->first();
                $finalData['low_sale2'] = CardSales::where('card_id', $cids[1])->orderBy('cost', 'ASC')->first();
            }
            return response()->json(['status' => 200, 'data' => $finalData], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getSingleCardGraphData($card_id, $days = 2) {
        
//        dd(EbayItems::whereIn('id', [50828,58957,58959])->get()->toArray());
        
        
        
//        $data = CardsSx::whereIn('card_id', [4,5,1,7])->orderBy('date', 'DESC')->get()->groupBy('card_id')->map(function ($item, $key) {
//            if(isset($item[1])) {
//                return [
//                    'cardId' => $item[0]->card_id,
//                    'sxDifference' => $item[0]->sx - $item[1]->sx,
//                    '%SxDifference' => (($item[0]->sx - $item[1]->sx)/$item[1]->sx)*100,
//                    ];
//            } else {
//                return [
//                    'cardId' => $item[0]->card_id,
//                    'sxDifference' => '-99999999999999999999',
//                    '%SxDifference' => '-9999999999999999999',
//                    'remark' => 'rank N/A'
//                    ];
//            }
//        });
//        
//        $myNewArray = array_values($data->toArray());
//        $keys = array_column($myNewArray, 'sxDifference');
//        array_multisort($keys, SORT_DESC, $myNewArray);
//        $rankBasedOnSxDifference = $myNewArray;
//        dump($rankBasedOnSxDifference);
//        $keys = array_column($myNewArray, '%SxDifference');
//        array_multisort($keys, SORT_DESC, $myNewArray);
//        $rankBasedOnSxDifferencePercentage = $myNewArray;
//        dd($rankBasedOnSxDifferencePercentage);
//        dd($myNewArray);
        
        
        
        try {
            if ($days == 2) {
                $grpFormat = 'H:i';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d 00:00:00');
            } elseif ($days == 7) {
                $grpFormat = 'Y-m-d';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-8 days'));
            } elseif ($days == 30) {
                $grpFormat = 'Y-m-d';
                $lblFormat = 'H:i';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-30 days'));
            } elseif ($days == 90) {
                $grpFormat = 'Y-m';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-90 days'));
            } elseif ($days == 180) {
                $grpFormat = 'Y-m';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-180 days'));
            } elseif ($days == 365) {
                $grpFormat = 'Y-m';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-365 days'));
            } elseif ($days == 1825) {
                $grpFormat = 'Y';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-1825 days'));
            }
            $data = ['values' => [], 'labels' => []];
            if ($grpFormat == 'H:i') {
                $cvs = CardSales::where('card_id', $card_id)->whereBetween('timestamp', [$to, $from])->orderBy('timestamp', 'DESC')->get()->groupBy(function ($cs) use ($grpFormat) {
                            return Carbon::parse($cs->timestamp)->floorMinutes(5)->format($grpFormat);
                        })->map(function ($cs, $timestamp) use ($grpFormat, $days) {
                    return [
                    'cost' => round((clone $cs)->avg('cost'), 2),
                    'timestamp' => $timestamp,
                    'quantity' => $cs->map(function ($qty) {
                    return (int) $qty->quantity;
                    })->sum()
                    ];
                });
                $data['values'] = $cvs->pluck('cost')->toArray();
                $data['labels'] = $cvs->pluck('timestamp')->toArray();
                $data['qty'] = $cvs->pluck('quantity')->toArray();
            } else {
                $data = $this->__getSxAllData($days, $to, $from, $card_id);
            }

            if ($days == 2) {
                $labels = [];
                $values = [];
                $qty = [];
                $sx = 0;
                $ind = null;
                $startTime = new \DateTime($to);
                $endTime = new \DateTime($from);
                $timeStep = 5;
                $timeArray = array();
                $previousSx = 0;
                $flag = 0;
                while ($startTime <= $endTime) {
                    $hi = $startTime->format('H:i');
                    $timstamp_format = $startTime->format('M/d/Y H:i');
                    if (count($data['labels']) > 0) {
                        $ind = array_search($hi, $data['labels']);
                        if (is_numeric($ind)) {
                            $values[] = $data['values'][$ind];
                            $qty[] = $data['qty'][$ind];
                            $previousSx = $data['values'][$ind];
                            $flag = 1;
                        } else {
                            if ($previousSx == 0 && $flag == 0) {
                                $salesDate = CardSales::where('card_id', $card_id)->where('card_id', $card_id)->where('timestamp', '<', $to)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                                if ($salesDate !== null) {
                                    $previousSx = CardSales::where('card_id', $card_id)->where('card_id', $card_id)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                                    $values[] = number_format($previousSx, 2, '.', '');
                                    $qty[] = 0;
                                }
                                $flag = 1;
                            } else {
                                $values[] = number_format($previousSx, 2, '.', '');
                                $qty[] = 0;
                            }
                        }
                    } else {
                        if ($previousSx == 0 && $flag == 0) {
                            $salesDate = CardSales::where('card_id', $card_id)->where('card_id', $card_id)->where('timestamp', '<', $to)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                            if ($salesDate !== null) {
                                $previousSx = CardSales::where('card_id', $card_id)->where('card_id', $card_id)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                                $values[] = number_format($previousSx, 2, '.', '');
                                $qty[] = 0;
                            }
                            $flag = 1;
                        } else {
                            $values[] = number_format($previousSx, 2, '.', '');
                            $qty[] = 0;
                        }
                    }
                    $timeArray[] = $hi;
                    $startTime->add(new \DateInterval('PT' . $timeStep . 'M'));
                }
                $data['values'] = $values;
                $data['qty'] = $qty;
                $data['labels'] = $timeArray;
            } else {
                $data['values'] = array_reverse($data['values']);
                $data['labels'] = array_reverse($data['labels']);
                $data['qty'] = array_reverse($data['qty']);
            }

            $finalData['values'] = $data['values'];
            $finalData['labels'] = $data['labels'];
            $finalData['qty'] = $data['qty'];
//            }

            $sx_data = CardSales::getGraphSxWithCardId($days, $data);
            $sx = $sx_data['sx'];
            $lastSx = $sx_data['lastSx'];

            $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
            $finalData['dollar_diff'] = str_replace('-', '', number_format($sx - $lastSx, 2, '.', ''));
            $pert_diff = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
            $finalData['pert_diff'] = str_replace('-', '', number_format($pert_diff, 2, '.', ''));
            $finalData['sx_icon'] = $sx_icon;
            $finalData['sx_value'] = number_format($sx, 2, '.', '');
            if ($days == 90) {
                $finalData['rank'] = $this->getCardRank($card_id);
                $finalData['price_rank'] = rand(1,99);
                $finalData['percentage_rank'] = rand(1,99);
                $sx_data = CardSales::getSx($card_id);
                $finalData['slabstoxValue'] = (isset($sx_data['sx']) ? number_format($sx_data['sx'], 2, '.', '') : 0);
                $lastSaleData = CardSales::where('card_id', $card_id)->orderBy('timestamp', 'DESC')->first();
                $finalData['lastSalePrice'] = (!empty($lastSaleData) ? $lastSaleData->cost : 0);
                $finalData['lastSaleDate'] = (!empty($lastSaleData) ? $lastSaleData['timestamp'] : 0);
                $finalData['highestSale'] = CardSales::where('card_id', $card_id)->orderBy('cost', 'DESC')->first();
                $finalData['lowestSale'] = CardSales::where('card_id', $card_id)->orderBy('cost', 'ASC')->first();
            }
            $name = 'card_rank_based_on_sx_difference';
            $trender = Cache::get($name);
            $finalData['card_rank_based_on_sx_difference'] = $trender[$card_id];
            
            $name = 'card_rank_based_on_sx_difference_percentage';
            $trender = Cache::get($name);
            $finalData['card_rank_based_on_sx_difference_percentage'] = $trender[$card_id];
            
            return response()->json(['status' => 200, 'data' => $finalData], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getCardAllGraph($card_id) {
        try {
            $days = [
                0 => ['from' => date('Y-m-d H:i:s'), 'to' => date('Y-m-d 00:00:00')],
                1 => ['from' => date('Y-m-d H:i:s'), 'to' => date('Y-m-d H:i:s', strtotime('-7 days'))],
                2 => ['from' => date('Y-m-d H:i:s'), 'to' => date('Y-m-d H:i:s', strtotime('-30 days'))],
                3 => ['from' => date('Y-m-d H:i:s'), 'to' => date('Y-m-d H:i:s', strtotime('-90 days'))],
                4 => ['from' => date('Y-m-d H:i:s'), 'to' => date('Y-m-d H:i:s', strtotime('-180 days'))],
                5 => ['from' => date('Y-m-d H:i:s'), 'to' => date('Y-m-d H:i:s', strtotime('-365 days'))],
                6 => ['from' => date('Y-m-d H:i:s'), 'to' => date('Y-m-d H:i:s', strtotime('-1825 days'))]];

            $data['labels'] = ['1D', '1W', '1M', '3M', '6M', '1Y', '5Y'];
            foreach ($days as $day) {
                $today_date = date('Y-m-d H:i:s');
                $data['values'][] = CardSales::where('card_id', $card_id)->whereBetween('timestamp', [$day['to'], $day['from']])->orderBy('timestamp', 'DESC')->sum('quantity');
            }
            $data['card_history'] = CardSales::where('card_id', $card_id)->orderBy('timestamp', 'DESC')->get();
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function lbl_dt($a, $b) {
        $a = Carbon::parse($a);
        $b = Carbon::parse($b);
        if ($a->greaterThan($b)) {
            return -1;
        } else if ($a->lessThan($b)) {
            return 1;
        } else
            0;
    }

    public function __groupGraphData($days, $data) {
        $months = null;
        $years = null;
        $cmp = $days;
        $cmpSfx = 'days';
        if ($days > 30 && $days <= 365) {
            $months = (int) ($days / 30);
            $cmp = $months;
            $cmpSfx = 'months';
        }
        if ($days > 365) {
            $years = (int) ($days / 365);
            $cmp = $years;
            $cmpSfx = 'years';
        }

        $grouped = [];
        $grouped_qty = [];
        $max = 0;
        if ($months != null) {
            $max = $months;
        }
        if ($years != null) {
            $max = $years;
        }

        if (isset($data['labels']) && isset($data['labels'][0])) {
            $last_date = Carbon::parse($data['labels'][0]);
        }

        $cmpFormat = 'Y-m-d';
        $last_date = Carbon::now();
        if ($cmpSfx == 'days') {
            $start_date = $last_date->copy()->subDays($cmp - 1);
        } else if ($cmpSfx == 'months') {
            $cmpFormat = 'Y-m';
            $start_date = $last_date->copy()->subMonths($cmp - 1);
        } else if ($cmpSfx == 'years') {
            $cmpFormat = 'Y';
            $start_date = $last_date->copy()->subYears($cmp - 1);
        }
        // if ((count($data['labels']) < (int) $cmp) || $cmpSfx == 'years' || $last_date > Carbon::now()) {

        if (isset($data['labels']) && isset($data['labels'][0])) {
            $period = \Carbon\CarbonPeriod::create($start_date, '1 ' . $cmpSfx, $last_date);
            $map_val = [];
            $map_qty = [];
            foreach ($period as $dt) {
                $dt = trim($dt->format($cmpFormat));
                $map_val[$dt] = 0;
                $map_qty[$dt] = 0;
                $ind = array_search($dt, $data['labels']);
                if (gettype($ind) == "integer") {
                    $map_val[$dt] = $data['values'][$ind];
                    $map_qty[$dt] = $data['qty'][$ind];
                }
            }
            uksort($map_val, [$this, "lbl_dt"]);
            uksort($map_qty, [$this, "lbl_dt"]);

            $data['labels'] = Collect(array_keys($map_val))->map(function ($lbl) use ($cmpFormat) {
                        return Carbon::createFromFormat($cmpFormat, explode(' ', $lbl)[0])->format('M/d/Y');
                    })->toArray();
            $data['values'] = array_values($map_val);
            $data['qty'] = array_values($map_qty);
        }
        // }

        return $data;
    }

    public function addRequestSlab(Request $request) {
        try {
            $user_id = auth()->user()->id;
            $validator = Validator::make($request->all(), [
                        'player' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json($validator, 500);
            }
            $filename = null;
            if ($request->hasFile('image')) {
                $filename = 'stoxRequests/' . substr(md5(mt_rand()), 0, 7) . $request->image->getClientOriginalName();
                Storage::disk('public')->put($filename, file_get_contents($request->image->getRealPath()));
            }

            RequestSlab::create([
                'user_id' => $user_id,
                'player' => $request->input('player'),
                'sport' => $request->input('sport'),
                'year' => $request->input('year'),
                'brand' => $request->input('brand'),
                'card' => $request->input('card'),
                'rc' => $request->input('rc'),
                'variation' => $request->input('variation'),
                'grade' => $request->input('grade'),
                'image' => $filename,
                'status' => 0
            ]);
            return response()->json(['status' => 200, 'data' => ['message' => 'Submitted Successfully.']], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function addRequestListing(Request $request) {
        try {
            $user_id = auth()->user()->id;
            $link = $request->input('link');

            $validator = Validator::make($request->all(), [
                        'link' => 'required|url',
                        'card_id' => 'required|exists:cards,id'
            ]);
            if ($validator->fails()) {
                // dd($validator->errors());
                return response()->json($validator->errors(), 500);
            }
            $check_request_listing = RequestListing::where('link', $request->input('link'))->where('card_id', $request->input('card_id'))->count();
            if ($check_request_listing == 0) {
                $requestListing = RequestListing::create([
                            'user_id' => $user_id,
                            'link' => $link,
                            'card_id' => $request->input('card_id')
                ]);

                if ($requestListing) {

                    $script_link = '/home/' . env('SCRAP_USER') . '/ebay_short/ebayFetch/bin/python3 /home/' . env('SCRAP_USER') . '/ebay_short/core.py """' . $link . '"""';
                    $scrap_response = shell_exec($script_link . " 2>&1");
                    $response = json_decode($scrap_response);
                    if (!empty($response->timeLeft)) {
                        date_default_timezone_set("America/Los_Angeles");
                        $auction_end_str = $response->timeLeft / 1000;
                        $auction_end = date('Y-m-d H:i:s', $auction_end_str);
                    }
                    EbayShortItem::create([
                        'request_listing_id' => $requestListing->id,
                        'title' => $response->name,
                        'ebay_id' => $response->ebay_id,
                        'price' => $response->price,
                        'image' => $response->image,
                        'time_left' => isset($auction_end) ? $auction_end : "",
                        'specifics' => json_encode($response->specifics),
                    ]);
                }
                return response()->json(['status' => 200, 'data' => ['message' => 'Listing request has been submitted successfully.']], 200);
            } else {
                return response()->json(['status' => 200, 'data' => ['message' => 'This listing is already present in our system. Try another one.']], 200);
            }
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getRequestSlabListForAdmin(Request $request) {
//        $page = $request->input('page', 1);
//        $take = $request->input('take', 30);
//        $skip = $take * $page;
//        $skip = $skip - $take;
        try {
            $items = RequestSlab::with(['user'])->where('status', 0)->orderBy('updated_at', 'desc')->get();
//            $items = $items->skip($skip)->take($take);
            return response()->json(['status' => 200, 'data' => $items], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getRequestListingListForAdmin(Request $request) {
//        $page = $request->input('page', 1);
//        $take = $request->input('take', 30);
//        $skip = $take * $page;
//        $skip = $skip - $take;
        try {
//            $items = RequestListing::where('approved', 0)
//                    ->with(['user', 'card', 'ebayShortItem'])
//                    ->select('id', 'approved', 'card_id', 'link', 'new_card_id', 'user_id')
//                    ->orderBy('updated_at', 'desc')
//                    ->get();
            $items = RequestListing::with(['user', 'card', 'ebayShortItem'])
                            ->select('id', 'approved', 'card_id', 'link', 'new_card_id', 'user_id', 'comparison')
                            ->orderBy('created_at', 'desc')
                            ->take(100)->get();

//            $items = $items->skip($skip)->take($take);
            return response()->json(['status' => 200, 'data' => $items], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function markRequestedListingForAdmin(Request $request) {
        $validator = Validator::make($request->all(), [
                    'rid' => 'required|exists:request_listing,id',
                    'sts' => 'required|numeric',
        ]);
        if ($validator->fails()) {
            return response()->json($validator, 500);
        }
        try {
            $req = RequestListing::whereId($request->get('rid'))->first();
            if ($request->get('newSlabId') != null) {
                $card_id = $request->get('newSlabId');
                if (Card::whereId($card_id)->count() != 1) {
                    return response()->json(['message' => 'Invalid Card Id. Try again.'], 500);
                }
                $req->update(['approved' => $request->get('sts'), 'new_card_id' => $request->get('newSlabId')]);
            } else {
                $card_id = $req->card_id;
                $req->update(['approved' => $request->get('sts')]);
            }
            if ($request->get('sts') == -1) {
                return response()->json(['status' => 200, 'data' => 'Request successfully rejected!'], 200);
            }

            // Scrap Data
//            $script_link = '/home/ubuntu/ebay/ebayFetch/bin/python3 /home/ubuntu/ebay/core.py """' . $req->link . '"""';
            $script_link = '/home/' . env('SCRAP_USER') . '/ebay/ebayFetch/bin/python3 /home/' . env('SCRAP_USER') . '/ebay/core.py """' . $req->link . '"""';
            $scrap_response = shell_exec($script_link . " 2>&1");
            $response = json_decode($scrap_response);
//            $data = (array) json_decode($scrap_response);
//
//            $cat = array(
//                'Football' => '1',
//                'Baseball' => '2',
//                'Basketball' => '3',
//                'Soccer' => '4',
//                'Pokemon' => '10',
//            );
//            if (isset($req->specifics->Sport) && !empty($req->specifics->Sport)) {
//                $cat_id = $cat[$req->specifics->Sport];
//            } else {
//                $cat_id = 1;
//            }
//
//            if (!empty($data['price'])) {
//                $selling_status = EbayItemSellingStatus::create([
//                            'itemId' => $data['ebay_id'],
//                            'currentPrice' => $data['price'],
//                            'convertedCurrentPrice' => $data['price'],
//                            'sellingState' => $data['price'],
//                            'timeLeft' => isset($data['timeLeft']) ? $data['timeLeft'] : null,
//                ]);
//            }
//            if (array_key_exists('seller', $data) && !empty($data['seller'])) {
//                $data['seller'] = (array) $data['seller'];
//                $seller_info = EbayItemSellerInfo::create([
//                            'itemId' => $data['ebay_id'],
//                            'sellerUserName' => isset($data['seller']['name']) ? $data['seller']['name'] : null,
//                            'positiveFeedbackPercent' => isset($data['seller']['feedback']) ? $data['seller']['feedback'] : null,
//                            'seller_contact_link' => isset($data['seller']['contact']) ? $data['seller']['contact'] : null,
//                            'seller_store_link' => isset($data['seller']['store']) ? $data['seller']['store'] : null
//                ]);
//            }
//            if (array_key_exists('specifics', $data) && !empty($data['specifics'])) {
//                foreach ($data['specifics'] as $key => $speci) {
//                    if (isset($speci['Value'])) {
//                        if ($speci['Value'] != "N/A") {
//                            EbayItemSpecific::create([
//                                'itemId' => $data['ebay_id'],
//                                'name' => isset($speci['Name']) ? $speci['Name'] : null,
//                                'value' => is_array($speci['Value']) ? implode(',', $speci['Value']) : $speci['Value']
//                            ]);
//                        }
//                    } else {
//                        EbayItemSpecific::create([
//                            'itemId' => $data['ebay_id'],
//                            'name' => $key,
//                            'value' => is_array($speci) ? implode(',', $speci) : $speci
//                        ]);
//                    }
//                }
//            }
//            if (array_key_exists('ebay_id', $data)) {
////                        $listing_info = EbayItemListingInfo::create([
////                            'itemId' => $data['ebay_id'],
////                            'buyItNowAvailable' => isset($row[7]) ? $row[7] : null,
////                            'listingType' => isset($row[2]) ? $row[2]: null,
////                            'startTime' => isset($row[5]) ? Carbon::create($row[5])->format('Y-m-d h:i:s') : null,
////                            'endTime' => isset($row[6]) ? Carbon::create($row[6])->format('Y-m-d h:i:s') : null,
////                        ]);
//
//                EbayItems::create([
//                    'card_id' => $card_id,
////                            'excel_uploads_id' => $eu_ids->id,
//                    'itemId' => $data['ebay_id'],
//                    'title' => $data['name'],
//                    'category_id' => $cat_id,
//                    'globalId' => isset($data['details']['Site']) ? 'EBAY-' . $data['details']['Site'] : null,
//                    'galleryURL' => isset($data['image']) ? $data['image'] : null,
//                    'viewItemURL' => isset($data['details']['ViewItemURLForNaturalSearch']) ? $data['details']['ViewItemURLForNaturalSearch'] : null,
//                    'autoPay' => isset($data['details']['AutoPay']) ? $data['details']['AutoPay'] : null,
//                    'postalCode' => isset($data['details']['PostalCode']) ? $data['details']['PostalCode'] : null,
//                    'location' => isset($data['location']) ? $data['location'] : null,
//                    'country' => isset($data['details']['Country']) ? $data['details']['Country'] : null,
//                    'returnsAccepted' => isset($data['returns']) == 'ReturnsNotAccepted' ? false : true,
//                    'condition_id' => isset($data['details']['ConditionID']) ? $data['details']['ConditionID'] : 1,
//                    'pictureURLLarge' => isset($data['image']) ? $data['image'] : null,
//                    'pictureURLSuperSize' => isset($data['image']) ? $data['image'] : null,
//                    'listing_ending_at' => isset($data['timeLeft']) ? $data['timeLeft'] : null,
//                    'is_random_bin' => array_key_exists('random_bin', $data) ? (bool) $data['random_bin'] : 0,
//                    'seller_info_id' => isset($seller_info) ? $seller_info->id : null,
//                    'selling_status_id' => isset($selling_status) ? $selling_status->id : null,
////                            'listing_info_id' => isset($listing_info) ? $listing_info->id : null,
//                ]);
//            }



            $response->card_id = $card_id;
            $response->item_link = $req->link;
            return response()->json(['status' => 200, 'data' => $response], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getSingleRequestedSlab(Request $request, $card_id) {
        $req = RequestSlab::whereId($card_id)->first();
        return response()->json(['status' => 200, 'data' => $req], 200);
    }

    public function requestedSlabReject(Request $request) {
        $req = RequestSlab::whereId($request->input('id'))->first();
        $req->update(['status' => -1]);
        return response()->json(['status' => 200, 'message' => 'Slab rejected successfully.'], 200);
    }

//    public function uploadSlabForExcelImport_sukhi(Request $request) {
//        dump($request->all());
//        try {
//            if ($request->has('file1')) {
//                $sports = ucwords($request->for);
//                $filename = date("mdYHis"). '.'.$request->file1->extension();
//                if (Storage::disk('public')->put($filename, file_get_contents($request->file1->getRealPath()))) {
//                    $zip = new ZipArchive;
//                    $res = $zip->open(public_path("storage/" . $filename));
//                    if ($res === TRUE) {
//                        $zip->extractTo(public_path('storage/'.$sports));
//                        $zip->close();
//                        $folder = $request->file1->getClientOriginalName();
//                        $folder = explode(".", $folder);
//                        $dir = $sports . '/' . $folder[0];
//                        StoreZipImages::dispatch($filename, $dir)->delay(now()->addMinutes(1));
//                        ExcelUploads::create([
//                            'file_name' => $filename,
//                            'status' => 1,
//                            'file_type' => 2,
//                        ]);
//                        Storage::disk('public')->delete($filename);
//                        return response()->json(['message' => 'Images uploaded successfully.'], 200);
//                    } else {
//                        return response()->json(['message' => 'Error while extracting the files.'], 500);
//                    }
//                }
//                return response()->json(['message' => 'Error while saving the files.'], 500);
//            }
////            dd('else');
//            if ($request->has('file') && !is_array($request->file)) {
//                $filename = $request->file('file')->getClientOriginalName();
//                $path = $request->file('file')->store('temp');
//                if ($request->has('card_id')) {
//                    Excel::import(new ListingsImport($filename), storage_path('app') . '/' . $path);
////                    ExcelImports::dispatch(['file' => $path, 'type' => 'listings', 'filename' => $filename]);
//                    return response()->json(['message' => 'Listings imported successfully.'], 200);
//                } else {
//                    Excel::import(new CardsImport($filename), storage_path('app') . '/' . $path);
////                    ExcelImports::dispatch(['file' => $path, 'type' => 'slabs', 'filename' => $filename]);
//                    return response()->json(['message' => 'Slabs imported successfully.'], 200);
//                }
//            } elseif ($request->has('file') && is_array($request->file)) {
//                foreach ($request->file as $csv) {
//                    $filename = $csv->getClientOriginalName();
//                    $path = $csv->store('temp');
//                    if ($request->has('card_id')) {
//                        Excel::import(new ListingsImport($filename), storage_path('app') . '/' . $path);
////                        ExcelImports::dispatch(['file' => $path, 'type' => 'listings', 'filename' => $filename]);
//                    } else {
//                        Excel::import(new CardsImport($filename), storage_path('app') . '/' . $path);
////                        ExcelImports::dispatch(['file' => $path, 'type' => 'slabs', 'filename' => $filename]);
//                    }
//                }
//                if ($request->has('card_id')) {
//                    return response()->json(['message' => 'Listings imported successfully.'], 200);
//                } else {
//                    return response()->json(['message' => 'Slabs imported successfully.'], 200);
//                }
//            } else {
//                return response()->json(['message' => 'Images uploaded successfully.'], 200);
//            }
//        } catch (\Exception $e) {
//            \Log::error($e);
//            return response()->json(['message' => $e->getMessage()], 500);
//        }
//    }

    private function setSalesSxOnExcelUpload() {
        $salesData = CardSales::groupBy(DB::raw('DATE(timestamp)'))->orderBy('timestamp', 'DESC')->pluck('timestamp');
        foreach ($salesData as $key => $data) {
            $cardsByTimestamp = CardSales::where('timestamp', 'like', '%' . Carbon::create($data)->format('Y-m-d') . '%')->get();
            foreach ($cardsByTimestamp as $value) {
                $sale = CardSales::where("card_id", $value->card_id)->where('timestamp', 'like', '%' . Carbon::create($value->timestamp)->format('Y-m-d') . '%')->get();

                if (CardsSx::where("card_id", $value->card_id)
                                ->where('date', 'like', '%' . Carbon::create($value->timestamp)->format('Y-m-d') . '%')
                                ->exists()) {
                    CardsSx::where("card_id", $value->card_id)
                            ->where('date', 'like', '%' . Carbon::create($value->timestamp)->format('Y-m-d') . '%')
                            ->update(["sx" => $sale->avg('cost'), "quantity" => $sale->sum('quantity')]);
                } else {
                    CardsSx::create([
                        "card_id" => $value->card_id,
                        "date" => Carbon::create($value->timestamp)->format('Y-m-d'),
                        "sx" => $sale->avg('cost'),
                        "quantity" => $sale->sum('quantity'),
                    ]);
                }

                $saleTotal = CardSales::where('timestamp', 'like', '%' . Carbon::create($value->timestamp)->format('Y-m-d') . '%')->get();
                if (CardsTotalSx::where('date', Carbon::create($value->timestamp)->format('Y-m-d'))->exists()) {
                    CardsTotalSx::where('date', Carbon::create($value->timestamp)->format('Y-m-d'))
                            ->update(["amount" => $saleTotal->avg('cost'), "quantity" => $saleTotal->sum('quantity')]);
                } else {
                    CardsTotalSx::create([
                        "date" => Carbon::create($value->timestamp)->format('Y-m-d'),
                        "quantity" => $saleTotal->sum('quantity'),
                        "amount" => $saleTotal->avg('cost'),
                    ]);
                }

                $days = config('constant.days');
                $sports = config('constant.sports');
                foreach ($days as $daykey => $day) {
                    foreach ($sports as $sport) {
                        $name = 'trenders_' . $daykey . '_' . $sport;
                        $value = Cache::get($name);
                        if ($value != null && !empty($value)) {
                            $flag = 0;
                            foreach ($value as $key => $val) {

                                if ($value->card_id == $val['id']) {
                                    $sx_data = CardSales::getSxAndOldestSx($value->card_id, $day['to'], $day['from'], $day['daysForSx']);
                                    $sx = $sx_data['sx'];
                                    $lastSx = $sx_data['oldestSx'];
                                    $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';

                                    $value[$key]['price'] = number_format((float) $sx, 2, '.', '');
                                    $value[$key]['sx_value_signed'] = (float) $sx - $lastSx;
                                    $value[$key]['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                                    $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                                    $value[$key]['sx_percent_signed'] = $sx_percent;
                                    $value[$key]['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                                    $value[$key]['sx_icon'] = $sx_icon;
                                    $flag = 1;
                                    break;
                                }
                            }
                            if ($flag == 1) {
                                Cache::put($name, $value);
                            }
                        }
                    }
                }

                $value = Cache::get('trenders_all_cards');
                if ($value != null && !empty($value)) {
                    foreach ($value as $key => $val) {

                        if ($value->card_id == $val['id']) {
                            $checkSalesCount = CardSales::where('card_id', $current_card_id)->count();
                            if ($checkSalesCount >= 2) {
                                $sx_data = CardSales::getSxAndOldestSx($value->card_id, $day['to'], $day['from'], $day['daysForSx']);
                                $sx = $sx_data['sx'];
                                $lastSx = $sx_data['oldestSx'];
                                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';

                                $value[$key]['price'] = number_format((float) $sx, 2, '.', '');
                                $value[$key]['sx_value_signed'] = (float) $sx - $lastSx;
                                $value[$key]['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                                $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                                $value[$key]['sx_percent_signed'] = $sx_percent;
                                $value[$key]['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                                $value[$key]['sx_icon'] = $sx_icon;
                                break;
                            }
                        }
                    }
                    Cache::put('trenders_all_cards', $value);
                }
            }
        }
        //refresh trender cache
//        JobForTrender::dispatch();
    }

    public function uploadSlabForExcelImport(Request $request) {
        try {
            if ($request->has('file1')) {
                $sports = ucwords($request->for);
                $filename = $request->file('file1')->getClientOriginalName(); //need original name for excel upload table
                if (Storage::disk('public')->put($filename, file_get_contents($request->file1->getRealPath()))) {
                    $zip = new ZipArchive;
                    $res = $zip->open(public_path("storage/" . $filename));
                    if ($res === TRUE) {
                        $zip->extractTo(public_path('storage/' . $sports));
                        $zip->close();
                        $folder = explode(".", $filename);
                        $zipFolder = $folder[0];
                        $dir = $sports . '/' . $zipFolder;
                        StoreZipImages::dispatch($filename, $dir); //why add minutes
                        Storage::disk('public')->delete($filename);
                    } else {
                        return response()->json(['message' => 'There has been an error while extracting the files. Please try again.'], 500);
                    }
                } else {
                    return response()->json(['message' => 'There has been an error while saving the files. Please try again.'], 500);
                }
            }
            if ($request->has('file') && !is_array($request->file)) {
                $filename = $request->file('file')->getClientOriginalName();
                $path = $request->file('file')->store('temp');
                if ($request->has('card_id')) {
                    Excel::import(new ListingsImport($filename), storage_path('app') . '/' . $path);
//                    $this->setSalesSxOnExcelUpload(); //Upload sx avg to cards_sx table
                    return response()->json(['message' => 'Listings imported successfully.'], 200);
                } else {
                    Excel::import(new CardsImport($filename), storage_path('app') . '/' . $path);
                    return response()->json(['message' => 'Slabs imported successfully.'], 200);
                }
            } elseif ($request->has('file') && is_array($request->file)) {
                foreach ($request->file as $csv) {
                    $filename = $csv->getClientOriginalName();
                    $path = $csv->store('temp');
                    if ($request->has('card_id')) {
                        Excel::import(new ListingsImport($filename), storage_path('app') . '/' . $path);
//                        $this->setSalesSxOnExcelUpload(); //Upload sx avg to cards_sx table
                    } else {
                        Excel::import(new CardsImport($filename), storage_path('app') . '/' . $path);
                    }
                }
                if ($request->has('card_id')) {
                    return response()->json(['message' => 'Listings imported successfully.'], 200);
                } else {
                    return response()->json(['message' => 'Slabs imported successfully.'], 200);
                }
            } else {
                return response()->json(['message' => 'Files imported successfully.'], 200);
            }
        } catch (\Exception $e) {
            \Log::error($e);
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function inactiveSlab(Request $request) {
        try {
            if (is_array($request->id)) {
                foreach ($request->id as $id) {
                    (Card::whereId($id)->first())->update(['active' => 0]);
                }
                return response()->json(['message' => 'Cards inactive successfully'], 200);
            } else {
                (Card::whereId($request->id)->first())->update(['active' => 0]);
                return response()->json(['message' => 'Card inactive successfully'], 200);
            }
        } catch (Exception $e) {
            \Log::error($e);
            return response()->json($e->getMessage(), 500);
        }
    }

    public function csvUploads(Request $request) {
//        $page = $request->input('page', 1);
//        $take = $request->input('take', 30);
//        $skip = $take * $page;
//        $skip = $skip - $take;
        try {
//            $data = ExcelUploads::skip($skip)->take($take)->get();
            $data = ExcelUploads::orderBy('created_at', 'DESC')->get();
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function deleteUploads(Request $request, $excel_id) {
        try {
            ExcelUploads::whereId($excel_id)->delete();
            if ($carddetails = Card::where('excel_uploads_id', $excel_id)->first()) {
                ($carddetails = Card::where('excel_uploads_id', $excel_id))->delete();
            }
            if ($ebayitems = EbayItems::where('excel_uploads_id', $excel_id)->first()) {
                ($ebayitems = EbayItems::where('excel_uploads_id', $excel_id))->delete();
            }
            if ($cardsales = CardSales::where('excel_uploads_id', $excel_id)->first()) {
                ($cardsales = CardSales::where('excel_uploads_id', $excel_id))->delete();
            }
            return response()->json(['status' => 200], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function __groupGraphDataPerDay($days, $data, $card_ids = 0, $boardGraph = 0) {
        $months = null;
        $years = null;
        $cmp = $days;
        $cmpSfx = 'days';
        if ($days > 30 && $days <= 365) {
            $months = (int) ($days / 30);
            $cmp = $months;
            $cmpSfx = 'months';
        }
        if ($days > 365) {
            $years = (int) ($days / 365);
            $cmp = $years;
            $cmpSfx = 'years';
        }

        $grouped = [];
        $grouped_qty = [];
        $max = 0;
        if ($months != null) {
            $max = $months;
        }
        if ($years != null) {
            $max = $years;
        }
        $cmpFormat = 'Y-m-d';
        if ($cmpSfx == 'months') {
            $cmpFormat = 'Y-m';
        } else if ($cmpSfx == 'years') {
            $cmpFormat = 'Y';
        }

        if ($days == 7) {
            $start_date = date('Y-m-d', strtotime('-8 days'));
        } elseif ($days == 30) {
            $start_date = date('Y-m-d', strtotime('-30 days'));
        } elseif ($days == 90) {
            $start_date = date('Y-m-d', strtotime('-90 days'));
        } elseif ($days == 180) {
            $start_date = date('Y-m-d', strtotime('-180 days'));
        } elseif ($days == 365) {
            $start_date = date('Y-m-d', strtotime('-365 days'));
        } elseif ($days == 1825) {
            $start_date = date('Y-m-d', strtotime('-1825 days'));
        }
        $last_date = date('Y-m-d');
        $period = \Carbon\CarbonPeriod::create($start_date, '1 day', $last_date);
        $data['start_date'] = new DateTime($start_date);
        $data['start_date'] = $data['start_date']->format('M/d/Y');
        $map_val = [];
        $map_qty = [];
        $previousValue = 0;
        $flag = 0;
        $flag1 = 0;
        foreach ($period as $dt) {
            $ts = $dt->timestamp * 1000;
            $dt = trim($dt->format('Y-m-d'));
            $ind = array_search($dt, $data['labels']);
            if ($boardGraph == 1) {
                if (gettype($ind) == "integer") {
                    $map_val[$dt] = [$ts, number_format($data['values'][$ind], 2, '.', '')];
                    $map_qty[$dt] = $data['qty'][$ind];
                    $previousValue = number_format($data['values'][$ind], 2, '.', '');
                    $flag = 1;
                } else {
                    if ($flag == 0) {
                        if (is_array($card_ids)) {
                            $salesDate = CardSales::whereIn('card_id', $card_ids)->where('timestamp', '<', $dt)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                        } elseif ($card_ids != 0) {
                            $salesDate = CardsSx::where('card_id', $card_ids)->where('date', '<=', $dt)->orderBy('date', 'DESC')->first();
                        } else {
                            $salesDate = CardsTotalSx::where('date', '<=', $dt)->orderBy('date', 'DESC')->first();
                        }
                        if ($salesDate !== null) {
                            if (is_array($card_ids)) {
                                $sx = CardSales::whereIn('card_id', $card_ids)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->get();
                                $map_val[$dt] = [$ts, number_format($sx->avg('cost'), 2, '.', '')];
                                $map_qty[$dt] = number_format($sx->sum('quantity'), 2, '.', '');
                            } elseif ($card_ids != 0) {
                                $map_val[$dt] = [$ts, number_format($salesDate->sx, 2, '.', '')];
                                $map_qty[$dt] = $salesDate->quantity;
                            } else {
                                $map_val[$dt] = [$ts, number_format($salesDate->amount, 2, '.', '')];
                                $map_qty[$dt] = $salesDate->quantity;
                            }
                            $flag = 1;
                            $previousValue = $map_val[$dt][1];
                        } else {
                            $map_val[$dt] = [$ts, $previousValue];
                            $map_qty[$dt] = 0;
                            $flag = 1;
                        }
                    } elseif ($previousValue != 0 || $flag == 1) {
                        $map_val[$dt] = [$ts, $previousValue];
                        $map_qty[$dt] = 0;
                    }
                }
            } else {
                if (gettype($ind) == "integer") {
                    $map_val[$dt] = [$ts, number_format($data['values'][$ind], 2, '.', '')];
                    $map_qty[$dt] = $data['qty'][$ind];
                    $previousValue = number_format($data['values'][$ind], 2, '.', '');
                    $flag = 1;
                } else {
                    if ($previousValue != 0) {
                        $map_val[$dt] = [$ts, $previousValue];
                        $map_qty[$dt] = 0;
                        $flag = 1;
                    } elseif ($previousValue == 0 && $flag1 == 0) {
                        if (is_array($card_ids)) {
                            $salesDate = CardSales::whereIn('card_id', $card_ids)->where('timestamp', '<', $dt)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                        } elseif ($card_ids != 0) {
                            $salesDate = CardsSx::where('card_id', $card_ids)->where('date', '<=', $dt)->orderBy('date', 'DESC')->first();
                        } else {
                            $salesDate = CardsTotalSx::where('date', '<=', $dt)->orderBy('date', 'DESC')->first();
                        }
                        if ($salesDate !== null) {
                            if (is_array($card_ids)) {
                                $sx = CardSales::whereIn('card_id', $card_ids)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->get();
                                $map_val[$dt] = [$ts, number_format($sx->avg('cost'), 2, '.', '')];
                                $map_qty[$dt] = number_format($sx->sum('quantity'), 2, '.', '');
                            } elseif ($card_ids != 0) {
                                $map_val[$dt] = [$ts, number_format($salesDate->sx, 2, '.', '')];
                                $map_qty[$dt] = $salesDate->quantity;
                            } else {
                                $map_val[$dt] = [$ts, number_format($salesDate->amount, 2, '.', '')];
                                $map_qty[$dt] = $salesDate->quantity;
                            }
                            $flag = 1;
                            $previousValue = $map_val[$dt][1];
                        } else {
                            $flag1 = 1;
                        }
                    }
                }
            }
        }
        uksort($map_val, [$this, "lbl_dt"]);
        uksort($map_qty, [$this, "lbl_dt"]);
        $data['labels'] = Collect(array_keys($map_val))->map(function ($lbl) use ($cmpFormat) {
                    return Carbon::createFromFormat('Y-m-d', explode(' ', $lbl)[0])->format('M/d/Y');
                })->toArray();
        $data['values'] = array_values($map_val);
        $data['qty'] = array_values($map_qty);

        return $data;
    }

    //add Request Listing for all if not exists
    public function addRequestListingForAll() {
        try {
            Log::info('Short listing request START');
            $allListing = RequestListing::where('approved', 0)->where('listing_sync', 0)->orderBy('updated_at', 'desc')->get();

            foreach ($allListing as $list) {
                \DB::beginTransaction();
                Log::info($list->id);
                RequestListing::where('id', $list->id)->update(['listing_sync' => 1]);
                // if(!EbayShortItem::where("request_listing_id",$list->id)->exists()){
                $script_link = '/home/' . env('SCRAP_USER') . '/ebay_short/ebayFetch/bin/python3 /home/' . env('SCRAP_USER') . '/ebay_short/core.py """' . $list->link . '"""';
                $scrap_response = shell_exec($script_link . " 2>&1");
                $response = json_decode($scrap_response);
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
                // }
                \DB::commit();
            }
        } catch (\Exception $e) {
            \DB::rollback();
            \Log::info($e->getMessage());
        }
    }

    protected function matchAndCountDbMatching($fullName, $title) {
        $count = 0;
        $fullName = str_replace("/", " ", $fullName);
        $names = explode(' ', $fullName);
        foreach ($names as $name) {
            if (Str::contains(strToLower($title), strToLower($name))) {
                $count = $count + 1;
                break;
            }
        }

        return $count;
    }

    protected function compareByDB($listing) {
        $total = 5;
        $percentage = 0;
        $count = 0;
        $title = strToLower($listing->ebayShortItem->title);

        if (!empty($title)) {
            if (!empty($listing->cardForMatch->player)) {
                $count = $count + $this->matchAndCountDbMatching($listing->cardForMatch->player, $title);
            }

            if (!empty($listing->cardForMatch->year) && Str::contains(strToLower($title), $listing->cardForMatch->year)) {
                $count = $count + 1;
            }

            if (isset($listing->cardForMatch->brand) && !empty($listing->cardForMatch->brand)) {
                $count = $count + $this->matchAndCountDbMatching($listing->cardForMatch->brand, $title);
            }

            if (isset($listing->cardForMatch->variation) && !empty($listing->cardForMatch->variation)) {
                //Ignore Base variation
                if ($listing->cardForMatch->variation == 'Base') {
                    $count = $count + 1;
                } else {
                    $count = $count + $this->matchAndCountDbMatching($listing->cardForMatch->variation, $title);
                }
            }
//need to compare by grade
            if (isset($listing->cardForMatch->grade) && !empty($listing->cardForMatch->grade)) {
                $names = explode(' ', $listing->cardForMatch->grade);
                if (isset($names[0]) && !empty($names[0]) && Str::contains($title, strToLower($names[0]))) {
                    $count = $count + 1;
                } else if (isset($names[1]) && !empty($names[1]) && Str::contains($title, ' ' . strToLower($names[1]))) {
                    $count = $count + 1;
                }

                // $count = $count + $this->matchAndCountDbMatching($listing->cardForMatch->grade, $title);
            }

            $percentage = ($count * 100) / $total;
        }

        return $percentage;
    }

    protected function compareByImageText($scrap_response, $listing) {
        $total = 4;
        $count = 0;
        $scrap_response = strToLower($scrap_response);

        if (!empty($scrap_response)) {
            if (!empty($listing->cardForMatch->player)) {
                $count = $count + $this->matchAndCountDbMatching($listing->cardForMatch->player, $scrap_response);
            }
            if (!empty($listing->cardForMatch->year)) {
                $count = $count + $this->matchAndCountDbMatching($listing->cardForMatch->year, $scrap_response);
            }

            if (isset($listing->cardForMatch->brand) && !empty($listing->cardForMatch->brand)) {
                $count = $count + $this->matchAndCountDbMatching($listing->cardForMatch->brand, $scrap_response);
            }

            if (isset($listing->cardForMatch->grade) && !empty($listing->cardForMatch->grade)) {
                $names = explode(' ', $listing->cardForMatch->grade);
                if (isset($name[0]) && !empty($name[0]) && Str::contains($title, $name[0])) {
                    $count = $count + 1;
                } else if (isset($name[1]) && !empty($name[1]) && Str::contains($title, ' ' . $name[1])) {
                    $count = $count + 1;
                }
                // $count = $count + $this->matchAndCountDbMatching($listing->cardForMatch->grade, $scrap_response);
            }
        }

        $percentage = ($count * 100) / $total;

        return $percentage;
    }

    public function compareImages() {
        Log::info('compareImages START');
        $allListing = RequestListing::where('approved', 0)->with("cardForMatch", "ebayShortItem")->take(5)->latest()->get();
        // $allListing = RequestListing::whereId('74746')->with("cardForMatch", "ebayShortItem")->get();
        $total = 5;
        
        //         $allListing = RequestListing::get();
        //         foreach ($allListing as $listing) {
            //             RequestListing::where('id',$listing->id)->update(["approved"=>0, 'comparison'=>NULL]);
            //         }
            // dump('done');
        foreach ($allListing as $listing) {
            $count = 0;
            $weightedPercentageDb = 0; 
            $weightedPercentageImage = 0;
            $imagePercentage = 0;
            $avgPercentage = 0;

            if (!empty($listing->cardForMatch) && !empty($listing->ebayShortItem)) {
                
                $dbPercentage = $this->compareByDB($listing);

                if(!empty($listing->ebayShortItem->image)){
                    if ($dbPercentage >= 80) {
                        $url1 = str_replace('300', '600', $listing->ebayShortItem->image);
                        $contents = file_get_contents($url1);
                        $name = substr($url1, strrpos($url1, '/') + 1);
    
                        if (!Storage::put($name, $contents)) {
                            $url1 = str_replace('300', '500', $listing->ebayShortItem->image);
                            $contents = file_get_contents($url1);
                            $name = substr($url1, strrpos($url1, '/') + 1);
    
                            if (!Storage::put($name, $contents)) {
                                $contents = file_get_contents($listing->ebayShortItem->image);
                                $name = substr($url1, strrpos($url1, '/') + 1);
                            }
                        }
    
                        $imageUrl = storage_path('app/' . $name);
                        $scrap_response = shell_exec("/home/" . env('SCRAP_USER') . "/ebay_image_compare/ebayFetch/bin/python3 /home/" . env('SCRAP_USER') . "/ebay_image_compare/textextract.py $imageUrl 2>&1");
                        $scrap_response = substr($scrap_response, strrpos($scrap_response, '['));
    
                        $imagePercentage = $this->compareByImageText($scrap_response, $listing);

                        $weightedPercentageImage = ($imagePercentage * 30) / 100;
                        $weightedPercentageDb = ($dbPercentage * 70) / 100;
                        $avgPercentage = ($weightedPercentageDb + $weightedPercentageImage);
                    } else {
                        RequestListing::where('id', $listing->id)->update(["approved" => -1]);
                    }
                } else {
                    $avgPercentage = $dbPercentage;
                }
                
                if ($avgPercentage >= 70) {
                    //Approved
                    // dump($avgPercentage.'Approved');
                    RequestListing::where('id', $listing->id)->update(["approved" => 1, 'comparison' => $avgPercentage]);
                } else if ($avgPercentage < 70 && $avgPercentage >= 30) {
                    //manual
                    // dump($avgPercentage.'manual');
                    RequestListing::where('id', $listing->id)->update(["approved" => 2, 'comparison' => $avgPercentage]);
                } else {
                    //reject
                    //dump($avgPercentage.'reject');
                    RequestListing::where('id', $listing->id)->update(["approved" => -1, 'comparison' => $avgPercentage]);
                }
            }
        }
        Log::info('compareImages END');
//        dd('Done');
        // dd($allListing->toArray());
    }

}
