<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ebay\EbayItems;
use Illuminate\Http\Request;
use App\Models\WatchList;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;
use App\Models\Card;
use App\Models\CardValues;
use App\Models\CardSales;

class WatchListController extends Controller {

    protected $user_id;

    public function getUserWatchListIds(Request $request) {
        if (!empty(auth()->user())) {
            try {
                $this->user_id = auth()->user()->id;
                $watchlist = WatchList::where('user_id', $this->user_id)->pluck('card_id');
                return response()->json(['status' => 200, 'data' => $watchlist], 200);
            } catch (\Exception $e) {
                return response()->json($e->getMessage(), 500);
            }
        } else {
            return response()->json(['status' => 200, 'data' => []], 200);
        }
    }

    public function addToWatchList(Request $request) {
        try {
            $this->user_id = auth()->user()->id;
            $validator = Validator::make($request->all(), [
                        'id' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json($validator, 500);
            }
            if (WatchList::where(['user_id' => $this->user_id, 'card_id' => $request->input('id')])->count() == 0) {
                WatchList::create(['user_id' => $this->user_id, 'card_id' => $request->input('id')]);
                return response()->json(['status' => 200, 'data' => ['message' => 'Added succefully']], 200);
            } else {
                return response()->json(['status' => 200, 'data' => ['message' => 'already added']], 200);
            }
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function removeToWatchList(Request $request) {
        try {
            $this->user_id = auth()->user()->id;
            $validator = Validator::make($request->all(), [
                        'id' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json($validator, 500);
            }
            if (WatchList::where(['user_id' => $this->user_id, 'card_id' => $request->input('id')])->count() == 1) {
                WatchList::where(['user_id' => $this->user_id, 'card_id' => $request->input('id')])->delete();
                return response()->json(['status' => 200, 'data' => ['message' => 'Remove succefully']], 200);
            } else {
                return response()->json(['status' => 200, 'data' => ['message' => 'Not is watch list']], 200);
            }
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getEbayList(Request $request) {
        if (!empty(auth()->user())) {
            try {
                $this->user_id = auth()->user()->id;
                $page = $request->input('page', 1);
                $take = $request->input('take', 30);
                $search = $request->input('search', null);
                $filterBy = $request->input('filterBy', null);
                $skip = $take * $page;
                $skip = $skip - $take;

                $ids = WatchList::where('user_id', $this->user_id)->pluck('card_id');
                $cards = Card::whereIn('id', $ids)->where(function ($q) use ($filterBy) {
                            if ($filterBy != 'price_low_to_high' && $filterBy != null) {
                                $q->where('sport', $filterBy);
                            }
                        })->with('details')->skip($skip)->take($take)->get();
//            
                $data = [];
                foreach ($cards as $key => $card) {
                    $sx_data = CardSales::getSxAndLastSx($card->id);
                    $sx = $sx_data['sx'];
                    $lastSx = $sx_data['lastSx'];

                    $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                    $sx = number_format((float) $sx, 2, '.', '');


//                $cardValues = CardValues::where('card_id', $card->id)->orderBy('date', 'DESC')->limit(2)->get('avg_value');
//                $sx = 0;
//                $sx_icon = 'up';
//                foreach ($cardValues as $i => $cv) {
//                        if ($sx == 0) {
//                            $sx = $cv->avg_value;
//                        } else {
//                            $sx = $sx - $cv->avg_value;
//                        }
//                    }
//                    if ($sx < 0) {
//                        $sx = abs($sx);
//                        $sx_icon = 'down';
//                    }
//                    $purchase_price = (isset($ptempcards[$card->id]) ? $ptempcards[$card->id]->purchase_price : 0);
//                    $purchase_quantity = (isset($ptempcards[$card->id]) ? $ptempcards[$card->id]->purchase_quantity : 1);
//                    $portfolio_id = (isset($ptempcards[$card->id]) ? $ptempcards[$card->id]->id : 0);
//                    $differ = number_format((float) ($sx - $purchase_price), 2, '.', '');
//                $sx_icon = (($differ < 0)?'down':'up');
                    $data[] = [
                        'id' => $card->id,
                        'title' => $card->title,
                        'cardImage' => $card->cardImage,
                        'grade' => $card->grade,
                        'is_sx' => $card->is_sx,
                        'sx_value' => str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', '')),
                        'sx_icon' => $sx_icon,
                        'price' => $sx,
//                        'purchase_price' => $purchase_price,
//                        'differ' => $differ,
//                        'portfolio_id' => $portfolio_id,
//                        'purchase_quantity' => $purchase_quantity
                    ];
                }

//                usort($data, function($a, $b) {
//                    return $a['purchase_price'] <=> $b['purchase_price'];
//                });
                return response()->json(['status' => 200, 'data' => $data, 'next' => ($page + 1)], 200);
            } catch (\Exception $e) {
                return response()->json($e->getMessage(), 500);
            }
        } else {
            return response()->json(['status' => 200, 'data' => []], 200);
        }
    }

}
