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
use App\Models\Ebay\EbayItems;
use App\Models\RequestSlab;
use Carbon\Carbon;
use Excel;
use Validator;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

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
//                $response = EbayService::getSingleItemDetails($itemId);
                $script_link = '/home/ubuntu/ebay/ebayFetch/bin/python3 /home/ubuntu/ebay/core.py """' . $itemId . '"""';
                $scrap_response = shell_exec($script_link . " 2>&1");
                $response = json_decode($scrap_response);
                return response()->json(['status' => 200, 'data' => $response], 200);
//                if (isset($response['data'])) {
//                    return response()->json(['status' => 200, 'data' => $response['data']], 200);
//                } else {
//                    return response()->json('No record found.', 500);
//                }
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
                            $searchTerm = strtolower($request->get('search'));
                            foreach (['player', 'year', 'brand', 'card', 'rc', 'variation', 'grade'] as $fl) {
                                $q->orWhere($fl, 'like', '%' . $searchTerm . '%');
                            }
                        }
                    })->get();
            $cards = $cards->skip($skip)->take($take);
//            die('sd');
            $data = [];
            foreach ($cards as $card) {
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
                ];
            }
            $sportsList = Card::select('sport')->distinct()->pluck('sport');
            return response()->json(['status' => 200, 'data' => $data, 'next' => ($page + 1), 'sportsList' => $sportsList], 200);
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
            $rank = 0;
            CardDetails::orderBy('currentPrice', 'DESC')->get('card_id')->map(function($item, $key) use($id, &$rank) {
                if ($item['card_id'] == $id) {
                    $rank = $key;
                }
            });
            $cards = Card::where('id', $id)->with('details')->firstOrFail()->toArray();
            $cardValues = CardValues::where('card_id', $id)->orderBy('date', 'DESC')->limit(7);

            $cardValues = $cardValues->get()->toArray();
            $latestTwo = array_splice($cardValues, 0, 2);

            $sx = 0;
            $sx_icon = 'up';
            $updated = '';
            foreach ($latestTwo as $cv) {
                if ($sx == 0) {
                    $cards['price_graph_updated'] = Carbon::create($cv['updated_at'])->format('F d Y \- h:i:s A');
                    $sx = $cv['avg_value'];
                } else {
                    $sx = $sx - $cv['avg_value'];
                }
            }
            if ($sx < 0) {
                $sx = abs($sx);
                $sx_icon = 'down';
            }
            $cards['sx'] = $sx;
            $cards['sx_icon'] = $sx_icon;
            $cards['rank'] = $rank;
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
        $skip = $take * $page;
        $skip = $skip - $take;
        try {
            $cards = Card::where(function ($q) use ($request, $search) {
                        if ($request->has('sport') && $request->input('sport') != null) {
                            $q->where('sport', $request->input('sport'));
                        }
                        if ($search != null) {
                            $q->where('title', 'like', '%' . $search . '%');
                        }
                    })->where('active', 1)->with('details');
            if ($top_trend) {
                $cards = $cards->groupBy('sport');
            }
            $cards = $cards->skip($skip)->take($take)->get();
            $cards = $cards->map(function($card, $key) {
                $data = $card;
                $sx = CardSales::where('card_id', $card->id)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
                $lastSx = CardSales::where('card_id', $card->id)->orderBy('timestamp', 'DESC')->skip(3)->limit(3)->pluck('cost');
                $count = count($lastSx);
                $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;
                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                $data['sx_value'] = number_format((float) $sx, 2, '.', '');
                $data['sx_icon'] = $sx_icon;
                $data['price'] = 0;
                if (isset($card->details->currentPrice)) {
                    $data['price'] = $card->details->currentPrice;
                }
                return $data;
            });
            if ($top_trend) {
                $cards = $cards->sortBy('price');
            }
            if ($request->input('orderby') == 'priceup') {
                foreach ($cards as $key => $card) {
                    if ($card->sx_icon == 'down') {
                        $cards->forget($key);
                    }
                }
                $cards = $cards->sortBy('sx_value');
            } elseif ($request->input('orderby') == 'pricedown') {
                foreach ($cards as $key => $card) {
                    if ($card->sx_icon == 'up') {
                        $cards->forget($key);
                    }
                }
                $cards = $cards->sortBy('sx_value');
            } elseif ($request->input('orderby') == 'percentup') {
                
            } elseif ($request->input('orderby') == 'percentdown') {
                
            }


            return response()->json(['status' => 200, 'data' => $cards, 'next' => ($page + 1)], 200);
        } catch (\Exception $e) {
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
                $sx = CardSales::where('card_id', $card->id)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
                $lastSx = CardSales::where('card_id', $card->id)->orderBy('timestamp', 'DESC')->skip(3)->limit(3)->pluck('cost');
                $count = count($lastSx);
                $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;
                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                $data['sx_value'] = number_format((float) $sx, 2, '.', '');
                $data['sx_icon'] = $sx_icon;
                $data['price'] = 0;
                if (isset($card->details)) {
                    $data['price'] = $card->details->currentPrice;
                }
                return $data;
            });

            return response()->json(['status' => 200, 'data' => $cards], 200);
        } catch (\Exception $e) {
            dump($e);
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getSmartKeyword(Request $request) {
        try {
            $data = Card::where('player', 'like', '%' . $request->input('keyword') . '%')->distinct('player')->where('active', 1)->get()->take(10);
            $list = [];
            foreach ($data as $key => $value) {
                $name = explode(' ', $value['player']);
                $list[] = [
                    'id' => $value['id'],
                    'player' => $name[0],
                    'title' => $value['title']
                ];
            }
            return response()->json(['status' => 200, 'data' => $list, 'keyword' => $request->input('keyword')], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getSmartKeywordWithData(Request $request) {
        try {
            $data = Card::with(['details'])->where(function($q) use ($request) {
                        // $search = explode(' ', $request->input('search'));
                        $search = $request->input('search');
                        $q->orWhere('title', 'like', '%' . $search . '%');
                        // foreach ($search as $key => $keyword) {
                        // }
                    })->where('active', 1)->get()->take(10)->map(function($item, $key) {
                $temp = $item;
                $temp['rank'] = 0;
                return $temp;
            });
            return response()->json(['status' => 200, 'data' => $data, 'keyword' => $request->input('search')], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage() . ' ' . $e->getLine(), 500);
        }
    }

    public function getPopularPickCards(Request $request) {
        try {
            $data = Card::with(['details'])->orderBy('views', 'desc')->orderBy('created_at', 'desc')->get()->take($request->input('take', 10));
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage() . ' ' . $e->getLine(), 500);
        }
    }

    public function getCardRank($id) {
        $rank = 0;
        CardDetails::orderBy('currentPrice', 'DESC')->get('card_id')->map(function($item, $key) use($id, &$rank) {
            if ($item['card_id'] == $id) {
                $rank = $key;
            }
        });
        return $rank;
    }

    public function getSmartKeywordOnlyName(Request $request) {
        try {
            $data = Card::select('player')->where('player', 'like', '%' . $request->input('keyword') . '%')->where('sport', $request->input('sport'))->where('active', 1)->distinct()->get()->take(10);
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
                Card::where('id', $request->input('id'))->update(array('is_featured' => $is_featured));
                if ($is_featured) {
                    Card::where('sport', $cardSport)->where('id', '<>', $request->input('id'))->update(array('is_featured' => 0));
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
                //echo $cardSport.' : '.$status;
                /** update is featured start * */
                Card::where('id', $request->input('id'))->update(array('active' => $status));
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
                Card::where('id', $request->input('id'))->update(array('is_sx' => $is_sx));

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
                $filename = $request->image->getClientOriginalName();
                Storage::disk('public')->put($filename, file_get_contents($request->image->getRealPath()));
            }
            Card::create([
                'row_id' => (int) $data->row_id + 1,
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
                'image' => $request->hasFile('image') ? $filename : null,
            ]);
            return response()->json(['status' => 200, 'message' => 'Card Created'], 200);
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


//             Card::where('id', $request->input('id'))->update(array('is_featured'=>$is_featured));

            Card::where('id', $request->input('id'))->update([
                'sport' => $request->input('sport'),
                'player' => $request->input('player'),
                'year' => $request->input('year'),
                'brand' => $request->input('brand'),
                'card' => $request->input('card'),
                'rc' => $request->input('rc'),
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
                'is_featured' => ((bool) $request->input('is_featured')),
            ]);
//            $data = Card::where('id', $request->input('card_id'))->update($updated_array);
            return response()->json(['status' => 200, 'message' => 'Card Updated'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function createSales(Request $request) {
        try {
//            $data = Card::where('sport', $request->input('sport'))->orderBy('updated_at', 'desc')->first();

            CardSales::create([
                'card_id' => $request->input('card_id'),
                'timestamp' => Carbon::create($request->input('timestamp'))->format('Y-m-d h:i:s'),
                'quantity' => $request->input('quantity'),
                'cost' => $request->input('cost'),
                'source' => $request->input('source'),
                'type' => $request->input('type'),
            ]);
            return response()->json(['status' => 200, 'message' => 'Sales data added.'], 200);
        } catch (\Exception $e) {
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
        try {
//            $data = Card::where('sport', $request->input('sport'))->orderBy('updated_at', 'desc')->first();

            CardSales::where('id', $request->input('id'))->update([
//                'card_id'=> $request->input('card_id'),
                'timestamp' => Carbon::create($request->input('timestamp'))->format('Y-m-d h:i:s'),
                'quantity' => $request->input('quantity'),
                'cost' => $request->input('cost'),
                'source' => $request->input('source'),
                'type' => $request->input('type'),
            ]);
            return response()->json(['status' => 200, 'message' => 'Sales data edited.'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getStoxtickerData() {
        try {
            $data = ['total' => 0, 'sale' => 0, 'avg_sale' => 0, 'change' => 0, 'change_arrow' => 'up', 'last_updated' => ''];
            $data['total'] = Card::count();
            $cs_cost = CardSales::sum('cost');
            $data['sale'] =  number_format((float) $cs_cost, 2, '.', '');
            $last_updated = CardSales::orderBy('timestamp', 'DESC')->first();
            if (!empty($last_updated)) {
                $data['last_updated'] = Carbon::create($last_updated->timestamp)->format('F d Y \- h:i:s A');
            }

//            if (count($updateDates) > 1) {
            // Diff sum in latest and latest-1 
//                $data['change'] = $data['sale'] - CardValues::where('date', $updateDates[1])->sum('avg_value');
            $data['change'] = 0;
            if ($data['change'] < 0) {
                $data['change_arrow'] = 'down';
            }
            $data['change'] = abs($data['change']);
//            }
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getDashboardGraphData($days = 2, $card_id = 0) {
        try {
//            dd('in');
            $data = ['values' => [], 'labels' => []];
            $cvs = CardSales::where('card_id', $card_id)->groupBy('timestamp')->orderBy('timestamp', 'DESC')->limit($days);
            $data['values'] = $cvs->pluck('cost')->toArray();
            $data['labels'] = $cvs->pluck('timestamp')->toArray();
//            dd($data['labels']);
            $data['qty'] = $cvs->pluck('quantity')->toArray();

//            $cvs = CardValues::select('date', 'avg_value')->groupBy('date')->orderBy('date', 'DESC')->limit($days);
//            $data['values'] = $cvs->pluck('avg_value')->toArray();
//            $data['labels'] = $cvs->pluck('date')->toArray();

            $data = $this->__groupGraphData($days, $data);
            $data_qty = $this->__groupGraphData($days, ['labels' => $data['values'], 'values' => $data['qty']]);
            if ($days == 2) {
                $labels = [];
                $values = [];
                $qty = [];
                for ($i = 0; $i <= 23; $i++) {
                    $labels[] = ($i < 10) ? '0' . $i . ':00' : $i . ':00';
                    $values[] = (count($data['values']) > 0 ) ? $data['values'][0] : 0;
                    $qty[] = (count($data['qty']) > 0 ) ? $data['qty'][0] : 0;
                }
                $data['labels'] = $labels;
                $data['values'] = $values;
                $data['qty'] = $qty;
            } else {
                $data['values'] = array_reverse($data['values']);
                foreach ($data['labels'] as $key => $date) {
                    $tempDate[$key] = date('M/d/y', strtotime($date));
                }
                $data['labels'] = array_reverse($tempDate);
                $data['qty'] = array_reverse($data['qty']);
            }
            $sales_diff = CardSales::where('card_id', $card_id)->orderBy('timestamp', 'DESC')->take(2)->get();
            if (isset($sales_diff[1])) {
                $data['doller_diff'] = $sales_diff[1]->cost - $sales_diff[0]->cost;
                $data['perc_diff'] = $sales_diff[1]->cost / $sales_diff[0]->cost * 100;
                $data['last_timestamp'] = Carbon::create($sales_diff[1]->timestamp)->format('F d Y \- h:i:s A');
            } else {
                $data['doller_diff'] = 0;
                $data['perc_diff'] = 0;
                $data['last_timestamp'] = '';
            }

            $data['total_sales'] = CardSales::where('card_id', $card_id)->sum('cost');
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getStoxtickerAllData($days = 2) {
        try {
            $data = ['values' => [], 'labels' => []];
            $cvs = CardSales::groupBy('timestamp')->orderBy('timestamp', 'DESC')->limit($days);
            $data['values'] = $cvs->pluck('cost')->toArray();
            $data['labels'] = $cvs->pluck('timestamp')->toArray();
            $data['qty'] = $cvs->pluck('quantity')->toArray();

            $data = $this->__groupGraphData($days, $data);
            $data_qty = $this->__groupGraphData($days, ['labels' => $data['values'], 'values' => $data['qty']]);
            if ($days == 2) {
                $labels = [];
                $values = [];
                $qty = [];
                for ($i = 0; $i <= 23; $i++) {
                    $labels[] = ($i < 10) ? '0' . $i . ':00' : $i . ':00';
                    $values[] = (count($data['values']) > 0 ) ? $data['values'][0] : 0;
                    $qty[] = (count($data['qty']) > 0 ) ? $data['qty'][0] : 0;
                }
                $data['labels'] = $labels;
                $data['values'] = $values;
                $data['qty'] = $qty;
            } else {
                $data['values'] = array_reverse($data['values']);
                foreach ($data['labels'] as $key => $date) {
                    $tempDate[$key] = date('M/d/y', strtotime($date));
                }
                $data['labels'] = array_reverse($tempDate);
                $data['qty'] = array_reverse($data['qty']);
            }
            $sales_diff = CardSales::orderBy('timestamp', 'DESC')->take(2)->get();
            if (isset($sales_diff[1])) {
                $doller_diff = $sales_diff[1]->cost - $sales_diff[0]->cost;
                $data['doller_diff'] = str_replace('-','',$doller_diff);
                $perc_diff = $sales_diff[1]->cost / $sales_diff[0]->cost * 100;
                $data['perc_diff'] = number_format((float) $perc_diff, 2, '.', '');
                $data['last_timestamp'] = Carbon::create($sales_diff[1]->timestamp)->format('F d Y \- h:i:s A');
            } else {
                $data['doller_diff'] = 0;
                $data['perc_diff'] = 0;
                $data['last_timestamp'] = '';
            }
            $data['change_arrow'] = 'up';
            if ($data['doller_diff'] < 0) {
                $data['change_arrow'] = 'down';
            }
            $total_sales = CardSales::sum('cost');
            $data['total_sales'] = number_format((float) $total_sales, 2, '.', '');
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getCardGraphData($card_id, $days = 2) {
        try {
            $cids = explode('|', (string) $card_id);
            foreach ($cids as $ind => $cid) {
                $cvs = CardSales::where('card_id', $cid)->groupBy('timestamp')->orderBy('timestamp', 'DESC')->limit($days);
                $views = Card::where('id', $cid)->pluck('views');
                $view = ($views[0] == null) ? $view = 1 : $view = $views[0] + 1;
                Card::where('id', $cid)->update(['views' => $view]);
                $data['values'] = $cvs->pluck('cost')->toArray();
                $data['labels'] = $cvs->pluck('timestamp')->toArray();
                $data['qty'] = $cvs->pluck('quantity')->toArray();

                $data = $this->__groupGraphData($days, $data);
                $data_qty = $this->__groupGraphData($days, ['labels' => $data['values'], 'values' => $data['qty']]);
                if ($days == 2) {
                    $labels = [];
                    $values = [];
                    $qty = [];
                    for ($i = 0; $i <= 23; $i++) {
                        $labels[] = ($i < 10) ? '0' . $i . ':00' : $i . ':00';
                        $values[] = (count($data['values']) > 0 ) ? $data['values'][0] : 0;
                        $qty[] = (count($data['qty']) > 0 ) ? $data['qty'][0] : 0;
                    }
                    $data['labels'] = $labels;
                    $data['values'] = $values;
                    $data['qty'] = $qty;
                } else {
                    $data['values'] = array_reverse($data['values']);
                    $tempDate = [];
                    foreach ($data['labels'] as $key => $date) {
                        $tempDate[$key] = date('M/d/y', strtotime($date));
                    }
                    $data['labels'] = array_reverse($tempDate);
                    $data['qty'] = array_reverse($data['qty']);
                }
                $temData[$ind] = $data;
            }
            $finalData['values1'] = $temData[0]['values'];
            $finalData['lable1'] = $temData[0]['labels'];
            $finalData['qty1'] = $temData[0]['qty'];
            $finalData['values2'] = $temData[1]['values'];
            $finalData['labels2'] = $temData[1]['labels'];
            $finalData['qty2'] = $temData[1]['qty'];
            $finalData['rank1'] = $this->getCardRank($cids[0]);
            $finalData['rank2'] = $this->getCardRank($cids[1]);
            $finalData['last_sale1'] = CardSales::where('card_id', $cids[0])->orderBy('timestamp', 'DESC')->first();
            $finalData['last_sale2'] = CardSales::where('card_id', $cids[1])->orderBy('timestamp', 'DESC')->first();
            $finalData['high_sale1'] = CardSales::where('card_id', $cids[0])->orderBy('cost', 'DESC')->first();
            $finalData['high_sale2'] = CardSales::where('card_id', $cids[1])->orderBy('cost', 'DESC')->first();
            $finalData['low_sale1'] = CardSales::where('card_id', $cids[0])->orderBy('cost', 'ASC')->first();
            $finalData['low_sale2'] = CardSales::where('card_id', $cids[1])->orderBy('cost', 'ASC')->first();

            return response()->json(['status' => 200, 'data' => $finalData], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getSingleCardGraphData($card_id, $days = 2) {
        try {
            $cvs = CardSales::where('card_id', $card_id)->groupBy('timestamp')->orderBy('timestamp', 'DESC')->limit($days);
            $data['values'] = $cvs->pluck('cost')->toArray();
            $data['labels'] = $cvs->pluck('timestamp')->toArray();
            $data['qty'] = $cvs->pluck('quantity')->toArray();

            $data = $this->__groupGraphData($days, $data);
            $data_qty = $this->__groupGraphData($days, ['labels' => $data['values'], 'values' => $data['qty']]);
            if ($days == 2) {
                $labels = [];
                $values = [];
                $qty = [];
                for ($i = 0; $i <= 23; $i++) {
                    $labels[] = ($i < 10) ? '0' . $i . ':00' : $i . ':00';
                    $values[] = (count($data['values']) > 0 ) ? $data['values'][0] : 0;
                    $qty[] = (count($data['qty']) > 0 ) ? $data['qty'][0] : 0;
                }
                $data['labels'] = $labels;
                $data['values'] = $values;
                $data['qty'] = $qty;
            } else {
                $data['values'] = array_reverse($data['values']);
                foreach ($data['labels'] as $key => $date) {
                    $tempDate[$key] = date('M/d/y', strtotime($date));
                }
                $data['labels'] = array_reverse($tempDate);
                $data['qty'] = array_reverse($data['qty']);
            }

            $finalData['values'] = $data['values'];
            $finalData['labels'] = $data['labels'];
            $finalData['qty'] = $data['qty'];
            $finalData['rank'] = $this->getCardRank($card_id);

            $sx = $finalData['slabstoxValue'] = CardSales::where('card_id', $card_id)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
            $lastSaleData = CardSales::where('card_id', $card_id)->latest()->first();
            $finalData['lastSalePrice'] = $lastSaleData->cost;
            $finalData['lastSaleDate'] = $lastSaleData['timestamp'];
            $finalData['highestSale'] = CardSales::where('card_id', $card_id)->max('cost');
            $finalData['lowestSale'] = CardSales::where('card_id', $card_id)->min('cost');

//            $sx = CardSales::where('card_id', $card->id)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
            $lastSx = CardSales::where('card_id', $card_id)->orderBy('timestamp', 'DESC')->skip(3)->limit(3)->pluck('cost');
            $lastSx = count($lastSx);
//                $lastSx = ($count > 0) ? array_sum($lastSx->toArray())/$count : 0;
            $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
            $finalData['dollar_diff'] = number_format($sx - $lastSx, 2, '.', '');
            $finalData['pert_diff'] = number_format($lastSx / $sx * 100, 2, '.', '');
            $finalData['sx_icon'] = $sx_icon;

            return response()->json(['status' => 200, 'data' => $finalData], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getCardAllGraph($card_id) {
        try {
            $days = [ 
                0=>['from'=> date('Y-m-d H:i:s'),'to'=>date('Y-m-d H:i:s',strtotime('-1 day'))],
                1=>['from'=> date('Y-m-d H:i:s',strtotime('-1 day')),'to'=>date('Y-m-d H:i:s',strtotime('-7 days'))],
                2=>['from'=> date('Y-m-d H:i:s',strtotime('-1 day')),'to'=>date('Y-m-d H:i:s',strtotime('-30 day'))],
                3=>['from'=> date('Y-m-d H:i:s',strtotime('-1 day')),'to'=>date('Y-m-d H:i:s',strtotime('-90 day'))],
                4=>['from'=> date('Y-m-d H:i:s',strtotime('-1 day')),'to'=>date('Y-m-d H:i:s',strtotime('-180 day'))],
                5=>['from'=> date('Y-m-d H:i:s',strtotime('-1 day')),'to'=>date('Y-m-d H:i:s',strtotime('-365 day'))],
        6=>['from'=> date('Y-m-d H:i:s',strtotime('-1 day')),'to'=>date('Y-m-d H:i:s',strtotime('-1825 day'))]];
            $data['labels'] = ['1D', '1W', '1M', '3M', '6M', '1Y', '5Y'];
            foreach ($days as $day) {
                $today_date = date('Y-m-d H:i:s');
                $data['values'][] = CardSales::where('card_id', $card_id)->whereBetween('timestamp', [$day['to'], $day['from']])->orderBy('timestamp', 'DESC')->sum('quantity');
//                $data['values'][] = array_sum($cvs->toArray());
            }
//            $data['dfssf'] = $days;
            $data['card_history'] = CardSales::where('card_id', $card_id)->get();
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function __groupGraphData($days, $data) {
        $months = null;
        $years = null;
        if ($days > 30 && $days <= 365) {
            $months = (int) ($days / 30);
        }
        if ($days > 365) {
            $years = (int) ($days / 365);
        }

        $grouped = [];
        $max = 0;
        if ($months != null) {
            $max = $months;
            foreach ($data['labels'] as $ind => $dt) {
                $dt = explode('-', $dt);
                $dt = sprintf('%s-%s', $dt[0], $dt[1]);
                if (!in_array($dt, array_keys($grouped))) {
                    $grouped[$dt] = 0;
                }
                $grouped[$dt] += round($data['values'][$ind]);
            }
        }
        if ($years != null) {
            $max = $years;
            foreach ($data['labels'] as $ind => $dt) {
                $dt = explode('-', $dt)[0];
                if (!in_array($dt, array_keys($grouped))) {
                    $grouped[$dt] = 0;
                }
                $grouped[$dt] += round($data['values'][$ind]);
            }
        }

        if ($grouped != []) {
            $data['values'] = array_values($grouped);
            $data['labels'] = array_keys($grouped);
            $data['values'] = array_splice($data['values'], 0, $max);
            $data['labels'] = array_splice($data['labels'], 0, $max);
        }
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
            ]);
            return response()->json(['status' => 200, 'data' => ['message' => 'Request added']], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getRequestSlabListForAdmin(Request $request) {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {
            $items = RequestSlab::with(['user'])->orderBy('updated_at', 'desc')->get();
            $items = $items->skip($skip)->take($take);
            return response()->json(['status' => 200, 'data' => $items, 'next' => ($page + 1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function uploadSlabForExcelImport(Request $request) {
//        dd($request->all());
        try {
            if ($request->input('imageType') != 0) {
                $filename = $request->file->getClientOriginalName();
                if (Storage::disk('public')->put($filename, file_get_contents($request->file->getRealPath()))) {
                    $zip = new ZipArchive;
                    $res = $zip->open(public_path("storage/" . $filename));
                    if ($res === TRUE) {
                        if ($request->input('for') == 'baseball') {
                            $zip->extractTo(public_path("storage/baseball"));
                            $zip->close();
                        } else if ($request->input('for') == 'basketball') {
                            $zip->extractTo(public_path("storage/basketball"));
                            $zip->close();
                        } else if ($request->input('for') == 'football') {
                            $zip->extractTo(public_path("storage/football"));
                            $zip->close();
                        } else if ($request->input('for') == 'soccer') {
                            $zip->extractTo(public_path("storage/soccer"));
                            $zip->close();
                        }
                        return response()->json(['message' => $filename], 200);
                    } else {
                        return response()->json(['message' => 'Error while extracting the files'], 500);
                    }
                }
                return response()->json(['message' => 'File uploaded unsuccessfully'], 500);
            } else {

                if ($request->has('card_id')) {
                    Excel::import(new ListingsImport, request()->file('file'));
                } else {
                    Excel::import(new CardsImport, request()->file('file'));
                }
                return response()->json(['message' => 'Card imported successfully'], 200);
            }
        } catch (\Exception $e) {
            \Log::error($e);
            return response()->json($e->getMessage(), 500);
        }
    }

    public function inactiveSlab(Request $request) {


        try {
            if (is_array($request->id)) {
                foreach ($request->id as $id) {
                    Card::whereId($id)->update(['active' => 0]);
                }
                return response()->json(['message' => 'Cards inactive successfully'], 200);
            } else {
                Card::whereId($request->id)->update(['active' => 0]);
                return response()->json(['message' => 'Card inactive successfully'], 200);
            }
        } catch (Exception $e) {
            \Log::error($e);
            return response()->json($e->getMessage(), 500);
        }
    }

}
