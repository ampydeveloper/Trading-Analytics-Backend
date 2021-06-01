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
use DB;
use DateTime;
use DatePeriod;
use DateInterval;

class StoxtickerController extends Controller {

    public function slabSearch(Request $request) {
        try {
            $search = $request->input('keyword', null);
            $data = Card::whereHas('sales')->where(function($q) use ($search) {
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

    

    public function allBoards($days) {
        try {
//            dd($days);
//            $user_id = 15;
            $user_id = auth()->user()->id;
            $all_boards = Board::where('user_id', $user_id)->get();
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

//                $total_card_value = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
//                if (!empty($total_card_value)) {
//                    $boards[$key]['total_card_value'] = $total_card_value;
//                } else {
//                    $boards[$key]['total_card_value'] = 0;
//                }
                $total_card_value = 0;
                foreach ($all_cards as $cardId) {
                    $sale = CardSales::getSx($cardId);
                    $total_card_value += $sale['sx'];
                }
//                $total_card_value = CardSales::whereIn('card_id', $all_cards)->sum('cost');
                $boards[$key]['total_card_value'] = number_format((float) $total_card_value, 2, '.', '');

//                $sx = CardSales::where('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
//                $sx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
//                $sx_count = count($sx);
//                $sx = ($sx_count > 0) ? array_sum($sx->toArray()) / $sx_count : 0;
//                $lastSx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
//                $count = count($lastSx);
//                $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;

                $sx = $boards[$key]['sales_graph']['sx'];
                $lastSx = $boards[$key]['sales_graph']['lastSx'];

                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                $boards[$key]['sx_value'] = number_format((float) $sx, 2, '.', '');
                if ($sx != 0) {
                    $pert_diff = ($lastSx > 0 ? ((($sx - $lastSx) / $lastSx) * 100) : 0);
                    $boards[$key]['pert_diff'] = number_format((float) $pert_diff, 2, '.', '');
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
//            $total_card_value = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
//            $sx_count = count($total_card_value);
//            $total_card_value = ($sx_count > 0) ? array_sum($total_card_value->toArray()) / $sx_count : 0;
//            if (!empty($total_card_value)) {
//                $boards['total_card_value'] = $total_card_value;
//            } else {
//                $boards['total_card_value'] = 0;
//            }
            $total_card_value = 0;
            foreach ($all_cards as $cardId) {
                $sale = CardSales::getSx($cardId);
                $total_card_value += $sale['sx'];
            }
//            $total_card_value = CardSales::whereIn('card_id', $all_cards)->sum('cost');
            $boards['total_card_value'] = number_format((float) $total_card_value, 2, '.', '');

//                $sx = CardSales::where('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
//            $sx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
//            $sx_count = count($sx);
//            $sx = ($sx_count > 0) ? array_sum($sx->toArray()) / $sx_count : 0;
//            $lastSx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
//            $count = count($lastSx);
//            $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;

            $sx = $boards['sales_graph']['sx'];
            $lastSx = $boards['sales_graph']['lastSx'];

            $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
            $boards['sx_value'] = number_format($sx, 2, '.', '');

            if ($sx != 0) {
                $pert_diff = ($lastSx > 0 ? ((($sx - $lastSx) / $lastSx) * 100) : 0);
                $boards['pert_diff'] = number_format($pert_diff, 2, '.', '');
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
//                $sx = CardSales::where('card_id', $card)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
//                $sx = CardSales::where('card_id', $card)->orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
//                $sx_count = count($sx);
//                $sx = ($sx_count > 0) ? array_sum($sx->toArray()) / $sx_count : 0;
////                $total_card_value = $total_card_value + $sx;
//                $lastSx = CardSales::where('card_id', $card)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
//                $count = count($lastSx);
//                $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;

                $sx_data = CardSales::getSxAndLastSx($card);
                $sx = $sx_data['sx'];
                $total_card_value += $sx;
                $lastSx = $sx_data['lastSx'];

                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                $each_cards[$key]['card_data']['price'] = number_format($sx, 2, '.', '');
                $each_cards[$key]['card_data']['sx_value'] = number_format(abs($sx - $lastSx), 2, '.', '');
                $each_cards[$key]['card_data']['sx_icon'] = $sx_icon;
            }
            $finalData['sales_graph'] = $this->__cardData($all_cards, $days);

//            $sx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
//            $sx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
//            $sx_count = count($sx);
//            $sx = ($sx_count > 0) ? array_sum($sx->toArray()) / $sx_count : 0;
//            $lastSx = CardSales::whereIn('card_id', $all_cards)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
//            $count = count($lastSx);
//            $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;

            $sx = $finalData['sales_graph']['sx'];
            $lastSx = $finalData['sales_graph']['lastSx'];

            $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
            if ($sx != 0) {
                $pert_diff = ($lastSx > 0 ? ((($sx - $lastSx) / $lastSx) * 100) : 0);
                $finalData['pert_diff'] = number_format((float) $pert_diff, 2, '.', '');
            } else {
                $finalData['pert_diff'] = 0;
            }
            $finalData['sx_value'] = number_format((float) $sx, 2, '.', '');
            $finalData['sx_icon'] = $sx_icon;

//            $total_card_value = 0;
//                foreach ($all_cards as $cardId) {
//                    $sale = CardSales::getSx($cardId);
//                    $total_card_value += $sale['sx'];
//                }
//            $total_card_value = CardSales::whereIn('card_id', $all_cards)->sum('cost');
            $finalData['total_card_value'] = number_format((float) $total_card_value, 2, '.', '');

            return response()->json(['status' => 200, 'board' => $board, 'cards' => $each_cards, 'card_data' => $finalData, 'follow' => $follow], 200);
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

    public function getStoxtickerAllData($days = 2) {
        try {
//            dd(date('Y-m-d H:i:s'));
            if ($days == 2) {
                $grpFormat = 'H:i';
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d 00:00:00');
            } elseif ($days == 7) {
                $grpFormat = 'Y-m-d';
//                $from = date('Y-m-d H:i:s', strtotime('-1 day'));
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-8 days'));
            } elseif ($days == 30) {
                $grpFormat = 'Y-m-d';
                $lblFormat = 'H:i';
//                $from = date('Y-m-d H:i:s', strtotime('-1 day'));
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-30 days'));
            } elseif ($days == 90) {
                $grpFormat = 'Y-m';
//                $from = date('Y-m-d H:i:s', strtotime('-1 day'));
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-90 days'));
            } elseif ($days == 180) {
                $grpFormat = 'Y-m';
//                $from = date('Y-m-d H:i:s', strtotime('-1 day'));
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-180 days'));
            } elseif ($days == 365) {
                $grpFormat = 'Y-m';
//                $from = date('Y-m-d H:i:s', strtotime('-1 day'));
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-365 days'));
            } elseif ($days == 1825) {
                $grpFormat = 'Y';
//                $from = date('Y-m-d H:i:s', strtotime('-1 day'));
                $from = date('Y-m-d H:i:s');
                $to = date('Y-m-d H:i:s', strtotime('-1825 days'));
            }
//            
            $cvs = CardSales::whereBetween('timestamp', [$to, $from])->orderBy('timestamp', 'DESC')->get();
//            dd($cvs);
            $data = ['values' => [], 'labels' => []];

            if ($grpFormat == 'H:i') {
                $cvs = CardSales::whereBetween('timestamp', [$to, $from])->orderBy('timestamp', 'DESC')->get()->groupBy(function ($cs) use ($grpFormat) {
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
                $cvs = CardSales::whereBetween('timestamp', [$to, $from])->orderBy('timestamp', 'DESC')->get()->groupBy(function ($cs) use ($grpFormat) {
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

            $data['values'] = $cvs->pluck('cost')->toArray();
            $data['labels'] = $cvs->pluck('timestamp')->toArray();
            $data['qty'] = $cvs->pluck('quantity')->toArray();


            if ($days > 2) {
                $data = $this->__groupGraphDataPerDay($days, $data);
            }
            if ($days == 2) {
                $labels = [];
                $values = [];
                $qty = [];
                $sx = 0;
                $ind = null;

                $startTime = new \DateTime($to);
                $endTime = new \DateTime($from);
                $timeStep = 1;
                $timeArray = array();
                $previousSx = 0;
                $flag = 0;
                while ($startTime <= $endTime) {
                    $hi = $startTime->format('H:i');
//                    $date_format = $startTime->format('M/d/Y H:i');
//                    $timstamp_format = $startTime->gettimestamp() * 1000;
                    if (count($data['labels']) > 0) {
                        $ind = array_search($hi, $data['labels']);
                        if (is_numeric($ind)) {
                            $values[] = $data['values'][$ind];
//                            $values[] = array($timstamp_format, $data['values'][$ind]);
                            $qty[] = $data['qty'][$ind];
                            $previousSx = $data['values'][$ind];
                            $flag = 1;
                        } else {
                            if ($previousSx == 0 && $flag == 0) {
                                $salesDate = CardSales::where('timestamp', '<', $to)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                                if ($salesDate !== null) {
                                    $previousSx = CardSales::where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                                    $values[] = number_format($previousSx, 2, '.', '');
//                                    $values[] = array($timstamp_format, number_format($previousSx, 2, '.', ''));
                                    $qty[] = 0;
                                }
                                $flag = 1;
                            } else {
                                $values[] = number_format($previousSx, 2, '.', '');
//                                $values[] = array($timstamp_format, number_format($previousSx, 2, '.', ''));
                                $qty[] = 0;
                            }
                        }
                    } else {
                        if ($previousSx == 0 && $flag == 0) {
                            $salesDate = CardSales::where('timestamp', '<', $to)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                            if ($salesDate !== null) {
                                $previousSx = CardSales::where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                                $values[] = number_format($previousSx, 2, '.', '');
//                                $values[] = array($timstamp_format, number_format($previousSx, 2, '.', ''));
                                $qty[] = 0;
                            }
                            $flag = 1;
                        } else {
                            $values[] = number_format($previousSx, 2, '.', '');
//                            $values[] = array($timstamp_format, number_format($previousSx, 2, '.', ''));
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
            if ($days == 90) {
                $data['last_timestamp'] = '';
                $last_timestamp = CardSales::orderBy('timestamp', 'DESC')->first();
                if (!empty($last_timestamp)) {
                    $data['last_timestamp'] = Carbon::create($last_timestamp->timestamp)->format('F d Y \- h:i:s A');
                }
            }
//            $sx_data = CardSales::getGraphSx($from, $to);
            $sx_data = CardSales::getGraphSx($days, $data);
            $sx = $sx_data['sx'];
            $lastSx = $sx_data['lastSx'];
            if (!empty($sx_data)) {
                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                $data['doller_diff'] = str_replace('-', '', number_format((float) ($sx - $lastSx), 2, '.', ''));
                $data['perc_diff'] = (($lastSx > 0) ? str_replace('-', '', number_format((($sx - $lastSx) / $lastSx) * 100, 2, '.', '')) : 0);
                $data['change_arrow'] = $sx_icon;
            } else {
                $data['doller_diff'] = 0;
                $data['perc_diff'] = 0;
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
                    })->where('sold_price', '>', 0)->with(['card', 'listingInfo'])->orderBy('updated_at', 'desc')->take(6)->get();
            $items['football'] = EbayItems::whereHas('card', function($q) use($request) {
                        $q->where('sport', 'football');
                    })->where('sold_price', '>', 0)->with(['card', 'listingInfo'])->orderBy('updated_at', 'desc')->take(6)->get();
            $items['baseball'] = EbayItems::whereHas('card', function($q) use($request) {
                        $q->where('sport', 'baseball');
                    })->where('sold_price', '>', 0)->with(['card', 'listingInfo'])->orderBy('updated_at', 'desc')->take(6)->get();
            $items['soccer'] = EbayItems::whereHas('card', function($q) use($request) {
                        $q->where('sport', 'soccer');
                    })->where('sold_price', '>', 0)->with(['card', 'listingInfo'])->orderBy('updated_at', 'desc')->take(6)->get();
            $items['pokemon'] = EbayItems::whereHas('card', function($q) use($request) {
                        $q->where('sport', 'pokemon');
                    })->where('sold_price', '>', 0)->with(['card', 'listingInfo'])->orderBy('updated_at', 'desc')->take(6)->get();
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
    
    public function searchBoard(Request $request) {
        try {
//            dump($request->all());
            $page = $request->input('page', 1);
            $take = 10;
            $takeout = $take * $page;
//        $skip = $skip - $take;
            $boards_query = Board::where('name', 'like', '%' . $request->input('keyword') . '%');
            $boards = $boards_query->take($takeout)->get();
//            $boards = $boards_query->get();
            $boards_count = $boards_query->count();
//            dump($boards->toArray());
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
//            $days = 7;
//            $days = 30;
//            $days = 90;
//            $days = 180;
//            $days = 365;
//            $days = 1825;
            
            foreach ($boards as $key => $board) {
                $all_cards = json_decode($board->cards);
//                dump($all_cards);
                foreach ($all_cards as $card) {
                    $individual_sales_graph[] = $this->__cardData($card, $days);
                }
//                dump($individual_sales_graph);
                
                $sales_graph['labels'] = $individual_sales_graph[0]['labels'];
                $sales_graph['sx'] = $individual_sales_graph[0]['sx'];
//                dump($sales_graph['sx']);
                $sales_graph['lastSx'] = $individual_sales_graph[0]['lastSx'];
                foreach($individual_sales_graph as $graphKey => $graph) {
                    if ($graphKey == 0) {
                        foreach ($individual_sales_graph[0]['values'] as $valueKey => $value) {
                            $sales_graph['values'][$valueKey] = $value;
                            $sales_graph['qty'][$valueKey] = $individual_sales_graph[0]['qty'][$valueKey];
                            for ($i = 1; $i < count($individual_sales_graph); $i++) {
                                $sales_graph['qty'][$valueKey] += $individual_sales_graph[$i]['qty'][$valueKey];
                                if (is_array($value)) {
                                    $sales_graph['values'][$valueKey][1] += $individual_sales_graph[$i]['values'][$valueKey][1];
                                } else {
                                    $sales_graph['values'][$valueKey] += $individual_sales_graph[$i]['qty'][$valueKey];
                                }
                            }
                        }
                    } else {
                        $sales_graph['sx'] += $individual_sales_graph[$graphKey]['sx'];
                        $sales_graph['lastSx'] += $individual_sales_graph[$graphKey]['lastSx'];
                    }
                }

//                dd($sales_graph);
                $boards[$key]['sales_graph'] = $sales_graph;
//                dd($boards);
                $total_card_value = 0;
                foreach ($all_cards as $cardId) {
                    $sale = CardSales::getSx($cardId);
                    $total_card_value += $sale['sx'];
                }

                $sx = $boards[$key]['sales_graph']['sx'];
                $lastSx = $boards[$key]['sales_graph']['lastSx'];

                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                if ($sx != 0) {
                    $pert_diff = ($lastSx > 0 ? ((($sx - $lastSx) / $lastSx) * 100) : 0);
                    $boards[$key]['pert_diff'] = number_format((float) $pert_diff, 2, '.', '');
                } else {
                    $boards[$key]['pert_diff'] = 0;
                }
                $boards[$key]['sx_value'] = number_format((float) $sx, 2, '.', '');
                $boards[$key]['sx_icon'] = $sx_icon;
                $boards[$key]['total_card_value'] = number_format((float) $total_card_value, 2, '.', '');
            }

            return response()->json(['status' => 200, 'data' => $boards, 'boards_count' => $boards_count, 'page' => ((int) $page + 1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
    
    
    public function __cardData($card_ids, $days) {
        //        $card_id = 12;
        //        $days = 2;
//        dump($card_ids);
//        dump($days);
        if ($days == 2) {
            $grpFormat = 'H:i';
            $from = date('Y-m-d H:i:s');
            $to = date('Y-m-d 00:00:00');
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

        if ($grpFormat == 'H:i') {
            if(is_array($card_ids)) {
                $cvs = CardSales::whereIn('card_id', $card_ids)->whereBetween('timestamp', [$to, $from])->orderBy('timestamp', 'DESC')->get()->groupBy(function ($cs) use ($grpFormat) {
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
                $cvs = CardSales::where('card_id', $card_ids)->whereBetween('timestamp', [$to, $from])->orderBy('timestamp', 'DESC')->get()->groupBy(function ($cs) use ($grpFormat) {
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
            }
            
        } else {
            if(is_array($card_ids)) {
                $cvs = CardSales::whereIn('card_id', $card_ids)->whereBetween('timestamp', [$to, $from])->orderBy('timestamp', 'DESC')->get()->groupBy(function ($cs) use ($grpFormat) {
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
            } else {
                $cvs = CardSales::where('card_id', $card_ids)->whereBetween('timestamp', [$to, $from])->orderBy('timestamp', 'DESC')->get()->groupBy(function ($cs) use ($grpFormat) {
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
        }
        
//        dump($cvs->toArray());




//        dump($cvs);

        $data['values'] = $cvs->pluck('cost')->toArray();
        $data['labels'] = $cvs->pluck('timestamp')->toArray();
        $data['qty'] = $cvs->pluck('quantity')->toArray();

        if ($days > 2) {
//            $data = $this->__groupGraphData($days, $data);
            $data = $this->__groupGraphDataPerDay($days, $data, $card_ids);
//            dd($data);
        }
        // $data_qty = $this->__groupGraphData($days, ['labels' => $data['values'], 'values' => $data['qty']]);
        if ($days == 2) {

            $labels = [];
            $values = [];
            $qty = [];
            $sx = 0;
            $ind = null;

            $startTime = new \DateTime($to);
            $endTime = new \DateTime($from);
            $timeStep = 1;
            $timeArray = array();
            $previousSx = 0;
            $flag = 0;
            while ($startTime <= $endTime) {
                $hi = $startTime->format('H:i');
//                $date_format = $startTime->format('M/d/Y H:i');
//                $timstamp_format = $startTime->gettimestamp() * 1000;
                if (count($data['labels']) > 0) {
                    $ind = array_search($hi, $data['labels']);
                    if (is_numeric($ind)) {
                        $values[] = $data['values'][$ind];
//                        $values[] = array($timstamp_format, $data['values'][$ind]);
                        $qty[] = $data['qty'][$ind];
                        $previousSx = $data['values'][$ind];
                        $flag = 1;
                    } else {
                        if ($previousSx == 0 && $flag == 0) {
                            if(is_array($card_ids)) {
                                $salesDate = CardSales::whereIn('card_id', $card_ids)->where('timestamp', '<', $to)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                            } else {
                                $salesDate = CardSales::where('card_id', $card_ids)->where('timestamp', '<', $to)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                            }
                            if ($salesDate !== null) {
                                if(is_array($card_ids)) { 
                                    $previousSx = CardSales::whereIn('card_id', $card_ids)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                                } else {
                                    $previousSx = CardSales::where('card_id', $card_ids)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                                }
                                $values[] = number_format($previousSx, 2, '.', '');
//                                $values[] = array($timstamp_format, number_format($previousSx, 2, '.', ''));
                                $qty[] = 0;
                            }
                            $flag = 1;
                        } else {
                            $values[] = number_format($previousSx, 2, '.', '');
//                            $values[] = array($timstamp_format, number_format($previousSx, 2, '.', ''));
                            $qty[] = 0;
                        }
                    }
                } else {
                    if ($previousSx == 0 && $flag == 0) {
                        if(is_array($card_ids)) {
                            $salesDate = CardSales::whereIn('card_id', $card_ids)->where('timestamp', '<', $to)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                        } else {
                            $salesDate = CardSales::where('card_id', $card_ids)->where('timestamp', '<', $to)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                        }
                        if ($salesDate !== null) {
                            if(is_array($card_ids)) {
                                $previousSx = CardSales::whereIn('card_id', $card_ids)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                            } else {
                                $previousSx = CardSales::where('card_id', $card_ids)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                            }
            
                            $values[] = number_format($previousSx, 2, '.', '');
//                            $values[] = array($timstamp_format, number_format($previousSx, 2, '.', ''));
                            $qty[] = 0;
                        }
                        $flag = 1;
                    } else {
                        $values[] = number_format($previousSx, 2, '.', '');
//                        $values[] = array($timstamp_format, number_format($previousSx, 2, '.', ''));
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
//            $data['values'] = array_reverse($data['values']);
//            $data['labels'] = array_reverse($data['labels']);
//            $data['qty'] = array_reverse($data['qty']);

            $data['values'] = array_reverse($data['values']);
            $data['labels'] = array_reverse($data['labels']);
            $data['qty'] = array_reverse($data['qty']);
        }
//        dump($data);
        $finalData['values'] = $data['values'];
        $finalData['labels'] = $data['labels'];
        $finalData['qty'] = $data['qty'];
        if(is_array($card_ids)) {
            $last_timestamp = CardSales::whereIn('card_id', $card_ids)->select('timestamp')->orderBy('timestamp', 'DESC')->first();
        } else {
            $last_timestamp = CardSales::where('card_id', $card_ids)->select('timestamp')->orderBy('timestamp', 'DESC')->first();
        }
//        dd($last_timestamp);
        $finalData['last_timestamp'] = 'N/A';
        if (!empty($last_timestamp)) {
            $finalData['last_timestamp'] = Carbon::create($last_timestamp->timestamp)->format('F d Y \- h:i:s A');
        }
        if(is_array($card_ids)) {
            $sx_data = CardSales::getGraphSxWithIds($days, $data, $card_ids);
        } else {
            $sx_data = CardSales::getGraphSxWithCardId($days, $data, $card_ids);
        }
        
        $finalData['sx'] = $sx_data['sx'];
        $finalData['lastSx'] = $sx_data['lastSx'];

//        dd($finalData);
        return $finalData;
    }
    
    public function __groupGraphDataPerDay($days, $data, $card_ids = 0) {
//        dump($days);
//        dump($data);
//        dd($card_ids);
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
        $last_date = Carbon::now();
        if ($cmpSfx == 'days') {
            $start_date = $last_date->copy()->subDays($cmp);
        } else if ($cmpSfx == 'months') {
            $cmpFormat = 'Y-m';
            $start_date = $last_date->copy()->subMonths($cmp);
        } else if ($cmpSfx == 'years') {
            $cmpFormat = 'Y';
            $start_date = $last_date->copy()->subYears($cmp);
        }

//        dump($cmpFormat);
//        dump($start_date);
//        dd($last_date);
        // if ((count($data['labels']) < (int) $cmp) || $cmpSfx == 'years' || $last_date > Carbon::now()) {
        // if (isset($data['labels']) && isset($data['labels'][0])) {
        $period = \Carbon\CarbonPeriod::create($start_date, '1 day', $last_date);
        $data['start_date'] = trim($start_date->format('M/d/Y'));
        $map_val = [];
        $map_qty = [];
        $previousValue = 0;
        $flag = 0;
        foreach ($period as $dt) {
            $ts = $dt->timestamp * 1000;
            $dt = trim($dt->format('Y-m-d'));
            $ind = array_search($dt, $data['labels']);
            if (gettype($ind) == "integer") {
                $map_val[$dt] = [$ts, $data['values'][$ind]];
                $map_qty[$dt] = $data['qty'][$ind];
                $previousValue = $data['values'][$ind];
                $flag = 1;
            } else {
                if ($previousValue != 0 && (($cmpFormat == 'Y-m' && $days != 90) || $cmpFormat == 'Y')) {
                    $map_val[$dt] = [$ts, $previousValue];
                    $map_qty[$dt] = 0;
                    $flag = 1;
                } elseif ($flag == 0 && ($cmpFormat == 'Y-m-d' || $days == 90)) {
                    if (is_array($card_ids)) {
                        $salesDate = CardSales::whereIn('card_id', $card_ids)->where('timestamp', '<', $dt)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                    } 
                    elseif ($card_ids != 0) {
                        $salesDate = CardSales::where('card_id', $card_ids)->where('timestamp', '<', $dt)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                    } 
                    else {
                        $salesDate = CardSales::where('timestamp', '<', $dt)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                    }
                    if ($salesDate !== null) {
                        if (is_array($card_ids)) {
                            $sx = CardSales::whereIn('card_id', $card_ids)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                        } 
                        elseif($card_ids != 0) {
                            $sx = CardSales::whereIn('card_id', $card_ids)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                        }
                        else {
                            $sx = CardSales::where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                        }
                        $map_val[$dt] = [$ts, $sx];
                        $map_qty[$dt] = 0;
                        $flag = 1;
                        $previousValue = $sx;
                    } else {
                        $map_val[$dt] = [$ts, $previousValue];
                        $map_qty[$dt] = 0;
                        $flag = 1;
                    }
                } elseif (($previousValue != 0 || $flag == 1) && ($cmpFormat == 'Y-m-d' || $days == 90)) {
                    $map_val[$dt] = [$ts, $previousValue];
                    $map_qty[$dt] = 0;
                }
            }
        }


        uksort($map_val, [$this, "lbl_dt"]);
        uksort($map_qty, [$this, "lbl_dt"]);

        $data['labels'] = Collect(array_keys($map_val))->map(function ($lbl) use ($cmpFormat) {
                    return Carbon::createFromFormat('Y-m-d', explode(' ', $lbl)[0])->format('M/d/Y');
                })->toArray();
        // $data['labels'] = array_keys($map_val);
        $data['values'] = array_values($map_val);
        $data['qty'] = array_values($map_qty);
//        dump($data);
//        dump($GraphSX);
//        dd($GraphLastSX);
        // }
        // $period = \Carbon\CarbonPeriod::create($start_date, '1 '.$cmpSfx, $last_date);
        // $data['labels'] = [];
        // foreach ($period as $dt) {
        // array_push($data['labels'], $dt->format('M/d/Y'));
        // }
        // dd($period);
        // }

        return $data;
    }

}
