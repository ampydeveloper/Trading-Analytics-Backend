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
            $search = $request->input('keyword', null);
            $data = Card::where(function($q) use ($search) {
                        $search = explode(' ', $search);
                        foreach ($search as $key => $keyword) {
                            $q->Where('title', 'like', '%' . $keyword . '%');
                        }
                    })->distinct('player')->where('active', 1)->with('details')->get();

//            $data = Card::where('player', 'like', '%' . $request->input('keyword') . '%')->distinct('player')->where('active', 1)->with('details')->get();
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
            $page = $request->input('page', 1);
            $take = 4;
            $takeout = $take * $page;
//        $skip = $skip - $take;
            $boards = Board::where('name', 'like', '%' . $request->input('keyword') . '%')->take($takeout)->get();
            if (!empty($request->input('sport'))) {
                foreach ($boards as $key => $board) {
                    $all_cards = json_decode($board->cards);
                    $card_details = Card::whereIn('id', $all_cards)->whereIn('sport', $request->input('sport'))->count();
                    if (empty($card_details) && $card_details == 0) {
                        $boards->forget($key);
                    }
                }
            }
            $days = 2;
            if ($request->has('days')) {
                $days = $request->get('days');
            }
            foreach ($boards as $key => $board) {
                $all_cards = json_decode($board->cards);
                $boards[$key]['sales_graph'] = $this->__cardData($all_cards, $days);

//                $sx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
                $sx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
                $sx_count = count($sx);
                $sx = ($sx_count > 0) ? array_sum($sx->toArray()) / $sx_count : 0;
                $lastSx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
                $count = count($lastSx);
                $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;
                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                if ($sx != 0) {
                    $pert_diff = ($lastSx>0? ((($sx - $lastSx) / $lastSx) * 100) : 0);
                    $boards[$key]['pert_diff'] = number_format((float) $pert_diff, 2, '.', '');
                } else {
                    $boards[$key]['pert_diff'] = 0;
                }
                $boards[$key]['sx_value'] = number_format((float) $sx, 2, '.', '');
                $boards[$key]['sx_icon'] = $sx_icon;
//                $total_card_value = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
                $total_card_value = CardSales::whereIn('card_id', $all_cards)->sum('cost');
                $boards[$key]['total_card_value'] = number_format((float) $total_card_value, 2, '.', '');
            }

            return response()->json(['status' => 200, 'data' => $boards, 'page' => ((int) $page + 1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function allBoards($days) {
        try {
//            $user_id = 15;
            $user_id = auth()->user()->id;
            $all_boards = Board::where('user_id', $user_id)->get();
//            dd('ewrr');
            $b_ids = BoardFollow::where('user_id', $user_id)->pluck('board_id');
            if (!empty($b_ids)) {
                $board_follow = Board::whereIn('id', $b_ids)->get();
                $boards = $all_boards->merge($board_follow);
            }

            foreach ($boards as $key => $board) {
                $all_cards = json_decode($board->cards);
                $boards[$key]['board_details'] = Card::whereIn('id', $all_cards)->with('details')->get();
                $boards[$key]['sale_details'] = CardSales::whereIn('card_id', $all_cards)->get();
                $boards[$key]['sales_graph'] = $this->__cardData($all_cards, $days);
                $total_card_value = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
                if (!empty($total_card_value)) {
                    $boards[$key]['total_card_value'] = $total_card_value;
                } else {
                    $boards[$key]['total_card_value'] = 0;
                }

//                $sx = CardSales::where('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
                $sx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
                $sx_count = count($sx);
                $sx = ($sx_count > 0) ? array_sum($sx->toArray()) / $sx_count : 0;
                $lastSx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
                $count = count($lastSx);
                $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;
                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                $boards[$key]['sx_value'] = number_format((float) $sx, 2, '.', '');
                if ($sx != 0) {
                    $boards[$key]['pert_diff'] = number_format((float) (($sx - $lastSx) / $lastSx) * 100, 2, '.', '');
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

//            $total_card_value = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
            $total_card_value = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
            $sx_count = count($total_card_value);
            $total_card_value = ($sx_count > 0) ? array_sum($total_card_value->toArray()) / $sx_count : 0;
            if (!empty($total_card_value)) {
                $boards['total_card_value'] = $total_card_value;
            } else {
                $boards['total_card_value'] = 0;
            }

//                $sx = CardSales::where('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
            $sx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
            $sx_count = count($sx);
            $sx = ($sx_count > 0) ? array_sum($sx->toArray()) / $sx_count : 0;
            $lastSx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
            $count = count($lastSx);
            $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;
            $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
            $boards['sx_value'] = number_format((float) $sx, 2, '.', '');
            if ($sx != 0) {
                $boards['pert_diff'] = number_format((float) (($sx - $lastSx) / $lastSx) * 100, 2, '.', '');
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
//            $total_card_value = 0;
            foreach ($all_cards as $key => $card) {
                $each_cards[$key]['card_data'] = Card::where('id', (int) $card)->with('details')->first();
//                $sx = CardSales::where('card_id', $card)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
                $sx = CardSales::where('card_id', $card)->orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
                $sx_count = count($sx);
                $sx = ($sx_count > 0) ? array_sum($sx->toArray()) / $sx_count : 0;
//                $total_card_value = $total_card_value + $sx;
                $lastSx = CardSales::where('card_id', $card)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
                $count = count($lastSx);
                $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;
                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                $each_cards[$key]['card_data']['sx_value'] = number_format((float) $sx, 2, '.', '');
                $each_cards[$key]['card_data']['sx_icon'] = $sx_icon;
            }
            $finalData['sales_graph'] = $this->__cardData($all_cards, $days);

//            $sx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
            $sx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
            $sx_count = count($sx);
            $sx = ($sx_count > 0) ? array_sum($sx->toArray()) / $sx_count : 0;
            $lastSx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
            $count = count($lastSx);
            $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;
            $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
            if ($sx != 0) {
                $finalData['pert_diff'] = number_format((float) (($sx - $lastSx) / $lastSx) * 100, 2, '.', '');
            } else {
                $finalData['pert_diff'] = 0;
            }
            $finalData['sx_value'] = number_format((float) $sx, 2, '.', '');
            $finalData['sx_icon'] = $sx_icon;
            $total_card_value = CardSales::whereIn('card_id', $all_cards)->sum('cost');
            $finalData['total_card_value'] = number_format((float) $total_card_value, 2, '.', '');

            return response()->json(['status' => 200, 'board' => $board, 'cards' => $each_cards, 'card_data' => $finalData, 'follow' => $follow], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function __cardData($card_ids, $days) {
        //        $card_id = 12;
        //        $days = 2;
        if ($days == 2) {
            $grpFormat = 'H:i';
            $from = date('Y-m-d H:i:s');
            $to = date('Y-m-d H:i:s', strtotime('-1 day'));
        } elseif ($days == 7) {
            $grpFormat = 'Y-m-d';
            $from = date('Y-m-d H:i:s', strtotime('-1 day'));
            $to = date('Y-m-d H:i:s', strtotime('-8 days'));
        } elseif ($days == 30) {
            $grpFormat = 'Y-m-d';
            $lblFormat = 'H:i';
            $from = date('Y-m-d H:i:s', strtotime('-1 day'));
            $to = date('Y-m-d H:i:s', strtotime('-30 days'));
        } elseif ($days == 90) {
            $grpFormat = 'Y-m';
            $from = date('Y-m-d H:i:s', strtotime('-1 day'));
            $to = date('Y-m-d H:i:s', strtotime('-90 days'));
        } elseif ($days == 180) {
            $grpFormat = 'Y-m';
            $from = date('Y-m-d H:i:s', strtotime('-1 day'));
            $to = date('Y-m-d H:i:s', strtotime('-180 days'));
        } elseif ($days == 365) {
            $grpFormat = 'Y-m';
            $from = date('Y-m-d H:i:s', strtotime('-1 day'));
            $to = date('Y-m-d H:i:s', strtotime('-365 days'));
        } elseif ($days == 1825) {
            $grpFormat = 'Y';
            $from = date('Y-m-d H:i:s', strtotime('-1 day'));
            $to = date('Y-m-d H:i:s', strtotime('-1825 days'));
        }
        // $cvs = CardSales::whereIn('card_id', $card_ids)->groupBy('timestamp')->orderBy('timestamp', 'DESC')->limit($days);
        $cvs = CardSales::whereIn('card_id', $card_ids)->whereBetween('timestamp', [$to, $from])->orderBy('timestamp', 'DESC')->get()->groupBy(function ($cs) use ($grpFormat) {
                    return Carbon::parse($cs->timestamp)->format($grpFormat);
                })->map(function ($cs, $timestamp) use ($grpFormat, $days) {
            return [
            'cost' => round((clone $cs)->splice(0, 3)->avg('cost'), 2),
            'timestamp' => Carbon::createFromFormat($grpFormat, $timestamp)->format($days == 2 ? 'H:i' : $grpFormat),
            // ($days == 1825 ? 'Y' : 'Y-m-d 00:00:00')),
            'quantity' => $cs->map(function ($qty) {
            return (int) $qty->quantity;
            })->sum()
            ];
        });
        $data['values'] = $cvs->pluck('cost')->toArray();
        $data['labels'] = $cvs->pluck('timestamp')->toArray();
        $data['qty'] = $cvs->pluck('quantity')->toArray();

        if ($days > 2) {
            $data = $this->__groupGraphData($days, $data);
        }
        // $data_qty = $this->__groupGraphData($days, ['labels' => $data['values'], 'values' => $data['qty']]);
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

        if ((count($data['labels']) < (int) $cmp) || $cmpSfx == 'years' || $last_date > Carbon::now()) {
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
