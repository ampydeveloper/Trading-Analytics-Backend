<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Card;
use App\Models\CardDetails;
use App\Models\CardValues;
use App\Models\CardSales;
use App\Models\Board;
use App\Models\BoardFollow;
use App\Models\Ebay\EbayItems;
use Carbon\Carbon;
use Validator;
use Illuminate\Support\Facades\Storage;

class StoxtickerController extends Controller {

    public function slabSearch(Request $request) {
        try {
            $data = Card::where('player', 'like', '%' . $request->input('keyword') . '%')->distinct('player')->where('active', 1)->with('details')->get()->take(12);
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function createBoard(Request $request) {
        try {
            Board::create([
                'name' => $request->input('name'),
                'user_id' => auth()->user()->id,
                'cards' => json_encode($request->input('cards')),
            ]);
            return response()->json(['status' => 200, 'message' => 'Board Created'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function followBoard(Request $request) {
        try {
            if (empty(BoardFollow::where('board_id', '=', $request->input('board'))->where('user_id', '=', auth()->user()->id)->first())) {
                BoardFollow::create([
                    'board_id' => $request->input('board'),
                    'user_id' => auth()->user()->id,
                ]);
                $board_create = true;
            } else {
                BoardFollow::where('board_id', '=', $request->input('board'))->where('user_id', '=', auth()->user()->id)->delete();
                $board_create = false;
            }
            return response()->json(['status' => 200, 'message' => 'Board followed.', 'board_create' => $board_create], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function deleteBoard(Request $request) {
        try {
            Board::where('id', '=', $request->input('board'))->delete();
            return response()->json(['status' => 200, 'message' => 'Board deleted.'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function searchBoard(Request $request) {
        try {
            $boards = Board::where('name', 'like', '%' . $request->input('keyword') . '%')->get()->take(4);
            if(!empty($request->input('sport'))){
                foreach ($boards as $key => $board) {
                    $all_cards = json_decode($board->cards);
                    $card_details = Card::whereIn('id', $all_cards)->whereIn('sport', $request->input('sport'))->count();
                    if(empty($card_details) && $card_details == 0){
                        $boards->forget($key);
                    }
                }
            }
            $days = 2;
            if($request->has('days')){ $days = $request->get('days'); }
            foreach ($boards as $key => $board) {
                $all_cards = json_decode($board->cards);
                $boards[$key]['sales_graph'] = $this->__cardData($all_cards, $days);

                $sx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
                $lastSx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
                $count = count($lastSx);
                $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;
                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                if ($sx != 0) {
                    $boards[$key]['pert_diff'] = number_format((float) $lastSx / $sx * 100, 2, '.', '');
                } else {
                    $boards[$key]['pert_diff'] = 0;
                }
                $boards[$key]['sx_value'] = number_format((float) $sx, 2, '.', '');
                $boards[$key]['sx_icon'] = $sx_icon;
                $total_card_value = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
                $boards[$key]['total_card_value'] = number_format((float) $total_card_value, 2, '.', '');
            }

            return response()->json(['status' => 200, 'data' => $boards], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function allBoards($days) {
        try {
            $b_ids = BoardFollow::where('user_id', auth()->user()->id)->pluck('board_id');
            $all_boards = Board::where('user_id', auth()->user()->id)->get();
            $board_follow = Board::whereIn('id', $b_ids)->get();
            $boards = $all_boards->merge($board_follow);
            foreach ($boards as $key => $board) {
                $all_cards = json_decode($board->cards);
                $boards[$key]['board_details'] = Card::whereIn('id', $all_cards)->with('details')->get();
                $boards[$key]['sale_details'] = CardSales::whereIn('card_id', $all_cards)->get();
//                $boards[$key]['sales_graph'] = $this->__cardData($all_cards, $days);
                $total_card_value = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
                if (!empty($total_card_value)) {
                    $boards[$key]['total_card_value'] = $total_card_value;
                } else {
                    $boards[$key]['total_card_value'] = 0;
                }

                $sx = CardSales::where('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
                $lastSx = CardSales::where('card_id', $all_cards)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
                $count = count($lastSx);
                $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;
                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                $boards[$key]['sx_value'] = number_format((float) $sx, 2, '.', '');
                if ($sx != 0) {
                    $boards[$key]['pert_diff'] = number_format((float) $lastSx / $sx * 100, 2, '.', '');
                } else {
                    $boards[$key]['pert_diff'] = 0;
                }
                $boards[$key]['sx_icon'] = $sx_icon;
            }
            return response()->json(['status' => 200, 'data' => $boards], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function singleBoards($days, $board) {
        try {
            $board = Board::where('id', $board)->first();
            $all_cards = json_decode($board->cards);
            $boards['board_details'] = Card::whereIn('id', $all_cards)->with('details')->get();
            $boards['sale_details'] = CardSales::whereIn('card_id', $all_cards)->get();
            $boards['sales_graph'] = $this->__cardData($all_cards, $days);

            $total_card_value = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
                if (!empty($total_card_value)) {
                    $boards['total_card_value'] = $total_card_value;
                } else {
                    $boards['total_card_value'] = 0;
                }

                $sx = CardSales::where('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
                $lastSx = CardSales::where('card_id', $all_cards)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
                $count = count($lastSx);
                $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;
                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                $boards['sx_value'] = number_format((float) $sx, 2, '.', '');
                if ($sx != 0) {
                    $boards['pert_diff'] = number_format((float) $lastSx / $sx * 100, 2, '.', '');
                } else {
                    $boards['pert_diff'] = 0;
                }
                $boards['sx_icon'] = $sx_icon;
            
            return response()->json(['status' => 200, 'data' => $boards], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function boardDetails($board, $days = 2) {
        try {
            $board = Board::where('id', $board)->first();
            $follow = BoardFollow::where('board_id', '=', $board->id)->where('user_id', '=', auth()->user()->id)->first();
            $all_cards = json_decode($board->cards);
            $total_card_value = 0;
            foreach ($all_cards as $key => $card) {
                $each_cards[$key]['card_data'] = Card::where('id', (int) $card)->with('details')->first();
                $sx = CardSales::where('card_id', $card)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
                $total_card_value = $total_card_value + $sx;
                $lastSx = CardSales::where('card_id', $card)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
                $count = count($lastSx);
                $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;
                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                $each_cards[$key]['card_data']['sx_value'] = number_format((float) $sx, 2, '.', '');
                $each_cards[$key]['card_data']['sx_icon'] = $sx_icon;
            }
            $finalData['sales_graph'] = $this->__cardData($all_cards, $days);

            $sx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
            $lastSx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->skip(3)->limit(3)->pluck('cost');
            $count = count($lastSx);
            $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;
            $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
            if ($sx != 0) {
                $finalData['pert_diff'] = number_format((float) $lastSx / $sx * 100, 2, '.', '');
            } else {
                $finalData['pert_diff'] = 0;
            }
            $finalData['sx_value'] = number_format((float) $sx, 2, '.', '');
            $finalData['sx_icon'] = $sx_icon;
            $finalData['total_card_value'] = number_format((float) $total_card_value, 2, '.', '');

            return response()->json(['status' => 200, 'board' => $board, 'cards' => $each_cards, 'card_data' => $finalData, 'follow' => $follow], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function __cardData($card_ids, $days) {
//        $card_id = 12;
//        $days = 2;
        $cvs = CardSales::whereIn('card_id', $card_ids)->groupBy('timestamp')->orderBy('timestamp', 'DESC')->limit($days);
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
            $data['labels'] = array_reverse($data['labels']);
            $data['qty'] = array_reverse($data['qty']);
        }
        $finalData['values'] = $data['values'];
        $finalData['labels'] = $data['labels'];
        $finalData['qty'] = $data['qty'];
        $last_timestamp = CardSales::whereIn('card_id', $card_ids)->select('timestamp')->orderBy('timestamp', 'DESC')->first();
        if (!empty($last_timestamp)) {
            $finalData['last_timestamp'] = Carbon::create($last_timestamp->timestamp)->format('F d Y \- h:i:s A');
        } else {
            $finalData['last_timestamp'] = 'N/A';
        }
//        $finalData['rank'] = $this->getCardRank($card_id);
        return $finalData;
    }

    public function lbl_dt($a, $b)
    {
        $a = Carbon::parse($a);
        $b = Carbon::parse($b);
        if ($a->greaterThan($b)) {
            return -1;
        } else if ($a->lessThan($b)) {
            return 1;
        } else 0;
    }

    public function __groupGraphData($days, $data)
    {
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

        $last_date = Carbon::parse($data['labels'][0]);
        if ((count($data['labels']) < (int) $cmp) || $cmpSfx == 'years' || $last_date > Carbon::now()) {
            // $last_date = Carbon::parse($data['labels'][0]);
            $last_date = Carbon::now();
            if ($cmpSfx == 'days') {
                $start_date = $last_date->copy()->subDays($cmp);
            } else if ($cmpSfx == 'months') {
                $start_date = $last_date->copy()->subMonths($cmp);
            } else if ($cmpSfx == 'years') {
                $start_date = $last_date->copy()->subYears($cmp);
            }
            $lblSfx = explode(' ', $data['labels'][0]);
            if(count($lblSfx) > 1){ $lblSfx = $lblSfx[1]; }else{ $lblSfx = ''; }
            $period = \Carbon\CarbonPeriod::create($start_date, '1 ' . $cmpSfx, $last_date);
            $map_val = [];
            $map_qty = [];
            foreach ($period as $dt) {
                $dt = $dt->format('Y-m-d') . ' ' . $lblSfx;
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

            $data['labels'] = array_keys($map_val);
            $data['values'] = array_values($map_val);
            $data['qty'] = array_values($map_qty);
        }

        $grouped = [];
        $max = 0;
        if ($months != null) {
            $max = $months;
            foreach ($data['labels'] as $ind => $dt) {
                $dt = explode('-', $dt);
                if (count($dt) > 1) {
                    $dt = sprintf('%s-%s', $dt[0], $dt[1]);
                } else {
                    $dt = $dt[0];
                }
                if (!in_array($dt, array_keys($grouped))) {
                    $grouped[$dt] = 0;
                }
                $grouped[$dt] += round($data['values'][$ind]);
            }
        }
        if ($years != null) {
            $max = $years;
            foreach ($data['labels'] as $ind => $dt) {
                $dt = explode('-', $dt);
                if (count($dt) > 1) {
                    $dt = sprintf('%s-%s', $dt[0], $dt[1]);
                } else {
                    $dt = $dt[0];
                }
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

    public function getSoldListings(Request $request) {
        try {
            $items['basketball'] = EbayItems::whereHas('card', function($q) use($request) {
                        $q->where('sport', 'basketball');
                    })->where('sold_price', '>', 0)->with(['sellingStatus', 'card', 'listingInfo'])->orderBy('updated_at', 'desc')->take(6)->get();
            $items['football'] = EbayItems::whereHas('card', function($q) use($request) {
                        $q->where('sport', 'football');
                    })->where('sold_price', '>', 0)->with(['sellingStatus', 'card', 'listingInfo'])->orderBy('updated_at', 'desc')->take(6)->get();
            $items['baseball'] = EbayItems::whereHas('card', function($q) use($request) {
                        $q->where('sport', 'baseball');
                    })->where('sold_price', '>', 0)->with(['sellingStatus', 'card', 'listingInfo'])->orderBy('updated_at', 'desc')->take(6)->get();
            $items['soccer'] = EbayItems::whereHas('card', function($q) use($request) {
                        $q->where('sport', 'soccer');
                    })->where('sold_price', '>', 0)->with(['sellingStatus', 'card', 'listingInfo'])->orderBy('updated_at', 'desc')->take(6)->get();
            $items['pokemon'] = EbayItems::whereHas('card', function($q) use($request) {
                        $q->where('sport', 'pokemon');
                    })->where('sold_price', '>', 0)->with(['sellingStatus', 'card', 'listingInfo'])->orderBy('updated_at', 'desc')->take(6)->get();
//            $board = Board::where('id', $board)->first();
//            $all_cards = json_decode($board->cards);
//            foreach ($all_cards as $card) {
//                $each_cards[] = Card::where('id', (int) $card)->with('details')->first();
//            }
//            $finalData = $this->__cardData();
            return response()->json(['status' => 200, 'data' => $items], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

}
