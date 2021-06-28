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

    private function __graphDataPerDay($days, $data, $to, $card_ids = 0, $boardGraph = 0) {
        $months = null;
        $years = null;
        $cmp = $days;
        $cmpSfx = 'days';
        $cmpFormat = 'Y-m-d';
        if ($days > 30 && $days <= 365) {
            $months = (int) ($days / 30);
            $cmp = $months;
            $cmpSfx = 'months';
            $cmpFormat = 'Y-m';
        }
        if ($days > 365) {
            $years = (int) ($days / 365);
            $cmp = $years;
            $cmpSfx = 'years';
            $cmpFormat = 'Y';
        }

        $start_date = $to;
        $last_date = date('Y-m-d', strtotime(date('Y-m-d') . ' + 1 day'));
//dump($start_date);
//dump($last_date);
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

//            dd($boardGraph);
            if ($boardGraph == 1) {
                if (gettype($ind) == "integer") {
                    $map_val[$dt] = [$ts, number_format($data['values'][$ind], 2, '.', '')];
                    $map_qty[$dt] = $data['qty'][$ind];
                    $previousValue = number_format($data['values'][$ind], 2, '.', '');
                    $flag = 1;
                } else {
                    if ($flag == 0) {
                        if (is_array($card_ids)) {
                            $salesDate = CardSales::whereIn('card_id', $card_ids)->where('timestamp', '<=', $dt)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                        } elseif ($card_ids != 0) {
                            $salesDate = CardsSx::where('card_id', $card_ids)->where('date', '<=', $dt)->orderBy('date', 'DESC')->first(DB::raw('DATE(date)'));
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
                            $previousValue = $map_val[$dt];
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
                            $salesDate = CardSales::whereIn('card_id', $card_ids)->where('timestamp', '<=', $dt)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                        } elseif ($card_ids != 0) {
                            $salesDate = CardsSx::where('card_id', $card_ids)->where('date', '<=', $dt)->orderBy('date', 'DESC')->first(DB::raw('DATE(date)'));
                        } else {
                            $salesDate = CardsTotalSx::where('date', '<=', $dt)->orderBy('date', 'DESC')->first();
                        }
//                        dump($salesDate);
                        if ($salesDate !== null) {
                            if (is_array($card_ids)) {
                                $sx = CardSales::whereIn('card_id', $card_ids)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->get();
                                $map_val[$dt] = [$ts, number_format($sx->avg('cost'), 2, '.', '')];
                                $map_qty[$dt] = $sx->sum('quantity');
                            } elseif ($card_ids != 0) {
                                $sx = CardsSx::where('card_id', $card_ids)->where('date', 'like', '%' . Carbon::create($salesDate['DATE(date)'])->format('Y-m-d') . '%')->value('sx');
                                $map_val[$dt] = [$ts, number_format($sx, 2, '.', '')];
                                $map_qty[$dt] = 0;
                            } else {
                                $sx = CardsTotalSx::where('date', Carbon::create($salesDate->date)->format('Y-m-d'))->first();
                                $map_val[$dt] = [$ts, $salesDate->amount];
                                $map_qty[$dt] = $salesDate->quantity;
                            }
                            $flag = 1;
                            $previousValue = $sx->quantity;
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

    private function __getSxAllData($days, $to, $from) {
        $to = Carbon::create($to)->format('Y-m-d');
        $from = Carbon::create($from)->format('Y-m-d');
        $cvs = CardsTotalSx::whereBetween('date', [$to, $from])->orderBy('date', 'DESC')->get();
        $data['values'] = $cvs->pluck('amount')->toArray();
        $data['labels'] = $cvs->pluck('date')->toArray();
        $data['qty'] = $cvs->pluck('quantity')->toArray();
        if ($days > 2) {
            $data = $this->__groupGraphDataPerDay($days, $data);
        }
        return $data;
    }
    private function __getSlabstoxSxAllData($days, $to, $from) {
        $to = Carbon::create($to)->format('Y-m-d');
        $from = Carbon::create($from)->format('Y-m-d');
        $cvs = CardsTotalSx::whereBetween('date', [$to, $from])->orderBy('date', 'DESC')->get();
        $data['values'] = $cvs->pluck('amount')->toArray();
        $data['labels'] = $cvs->pluck('date')->toArray();
        $data['qty'] = $cvs->pluck('quantity')->toArray();
        if ($days > 2) {
            $data = $this->__groupGraphDataPerDay($days, $data);
        }
        return $data;
    }

    public function getStoxtickerAllData($days = 2) {
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
                $interval = 5;
                $cvs = CardSales::whereBetween('timestamp', [$to, $from])->orderBy('timestamp', 'DESC')->get()->groupBy(function ($cs) use ($grpFormat) {
                            return Carbon::parse($cs->timestamp)->floorMinutes(5)->format($grpFormat);
                        })->map(function ($cs, $timestamp) {
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
                $data = $this->__getSlabstoxSxAllData($days, $to, $from);
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
                                $salesDate = CardSales::where('timestamp', '<', $to)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                                if ($salesDate !== null) {
                                    $previousSx = CardSales::where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
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
                            $salesDate = CardSales::where('timestamp', '<', $to)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                            if ($salesDate !== null) {
                                $previousSx = CardSales::where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
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
            if ($days == 90) {
                $data['last_timestamp'] = '';
                $last_timestamp = CardSales::orderBy('timestamp', 'DESC')->first();
                if (!empty($last_timestamp)) {
                    $data['last_timestamp'] = Carbon::create($last_timestamp->timestamp)->format('F d Y \- h:i:s A');
                }
            }
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
            $page = $request->input('page', 1);
            $take = 10;
            $takeout = $take * $page;
            $boards_query = Board::where('name', 'like', '%' . $request->input('keyword') . '%');
            $boards = $boards_query->take($takeout)->get();
            $boards_count = $boards_query->count();
            if ($boards_count > 0) {
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
                    if ($days == 180 || $days == 365 || $days == 1825) {
                        $boardGraph = 1;
                    } else {
                        $boardGraph = 0;
                    }
                    foreach ($all_cards as $card) {
                        $individual_sales_graph[] = $this->__cardData($card, $days, $boardGraph);
                    }
                    if ($boardGraph == 0) {
                        $sales_graph['labels'] = $individual_sales_graph[0]['labels'];
                        $sales_graph['sx'] = $individual_sales_graph[0]['sx'];
                        $sales_graph['lastSx'] = $individual_sales_graph[0]['lastSx'];
                        $individual_sales_graph_count = count($individual_sales_graph);
                        foreach ($individual_sales_graph as $graphKey => $graph) {
                            if ($graphKey == 0) {
                                foreach ($individual_sales_graph[0]['values'] as $valueKey => $value) {
                                    $count = count($individual_sales_graph[0]['values']);
                                    if ($valueKey < ($count - 1)) {
                                        $sales_graph['values'][$valueKey] = $value;
                                        $sales_graph['qty'][$valueKey] = $individual_sales_graph[0]['qty'][$valueKey];
                                        for ($i = 1; $i < $individual_sales_graph_count; $i++) {
                                            $sales_graph['qty'][$valueKey] += $individual_sales_graph[$i]['qty'][$valueKey];
                                            if (is_array($value)) {
                                                $sales_graph['values'][$valueKey][1] += $individual_sales_graph[$i]['values'][$valueKey][1];
                                            } else {
                                                $sales_graph['values'][$valueKey] += $individual_sales_graph[$i]['values'][$valueKey];
                                            }
                                        }
                                    }
                                }
                            } else {
                                $sales_graph['sx'] += $individual_sales_graph[$graphKey]['sx'];
                                $sales_graph['lastSx'] += $individual_sales_graph[$graphKey]['lastSx'];
                            }
                        }
                        $sales_graph['last_timestamp'] = $individual_sales_graph[0]['last_timestamp'];
                    } else {
                        $individual_sales_graph_count = count($individual_sales_graph);
                        $flag = 0;
                        foreach ($individual_sales_graph[0]['qty'] as $qtyKey => $qty) {
                            $sales_graph_value = $individual_sales_graph[0]['values'][$qtyKey];
                            $sales_graph_qty = $individual_sales_graph[0]['qty'][$qtyKey];
                            if ($qty != 0) {
                                $flag = 1;
                            }
                            for ($i = 1; $i < $individual_sales_graph_count; $i++) {
                                if ($individual_sales_graph[$i]['qty'][$qtyKey] != 0) {
                                    $flag = 1;
                                }
                                $sales_graph_value[1] += $individual_sales_graph[$i]['values'][$qtyKey][1];
                                $sales_graph_qty += $individual_sales_graph[$i]['qty'][$qtyKey];
                            }
                            if ($flag == 1) {
                                $sales_graph['labels'][$qtyKey] = $individual_sales_graph[0]['labels'][$qtyKey];
                                $sales_graph['values'][$qtyKey] = $sales_graph_value;
                                $sales_graph['qty'][$qtyKey] = $sales_graph_qty;
                            }
                        }
                        $sales_graph['labels'] = array_values($sales_graph['labels']);
                        $sales_graph['values'] = array_values($sales_graph['values']);
                        $sales_graph['qty'] = array_values($sales_graph['qty']);
                        $sales_graph['sx'] = $sales_graph['values'][count($sales_graph['values']) - 1][1];
                        $sales_graph['lastSx'] = $sales_graph['values'][0][1];
                        $sales_graph['last_timestamp'] = $individual_sales_graph[0]['last_timestamp'];
                    }
                    $boards[$key]['sales_graph'] = $sales_graph;
                    $total_card_value = 0;
                    foreach ($all_cards as $cardId) {
                        $sale = CardSales::getSx($cardId);
                        $total_card_value += $sale['sx'];
                    }
                    $sx = $boards[$key]['sales_graph']['sx'];
                    $lastSx = $boards[$key]['sales_graph']['lastSx'];
                    $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                    $boards[$key]['doller_diff'] = str_replace('-', '', number_format((float) ($sx - $lastSx), 2, '.', ''));
                    if ($sx != 0) {
                        $pert_diff = ($lastSx > 0 ? ((($sx - $lastSx) / $lastSx) * 100) : 0);
                        $boards[$key]['pert_diff'] = number_format((float) $pert_diff, 2, '.', '');
                    } else {
                        $boards[$key]['pert_diff'] = 0;
                    }
//                $boards[$key]['sx_value'] = number_format((float) $sx, 2, '.', '');
                    $boards[$key]['sx_icon'] = $sx_icon;
                    $boards[$key]['total_card_value'] = number_format((float) $total_card_value, 2, '.', '');
                }
            } else {
                return response()->json(['status' => 200, 'data' => [], 'boards_count' => 0, 'page' => 0], 200);
            }
            return response()->json(['status' => 200, 'data' => $boards, 'boards_count' => $boards_count, 'page' => ((int) $page + 1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function __cardData($card_ids, $days, $boardGraph = 0) {
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

        if ($grpFormat == 'H:i') {
            if (is_array($card_ids)) {
                $cvs = CardSales::whereIn('card_id', $card_ids)->whereBetween('timestamp', [$to, $from])->orderBy('timestamp', 'DESC')->get()->groupBy(function ($cs) use ($grpFormat) {
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
            } else {
                $cvs = CardSales::where('card_id', $card_ids)->whereBetween('timestamp', [$to, $from])->orderBy('timestamp', 'DESC')->get()->groupBy(function ($cs) use ($grpFormat) {
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
            }
            $data['values'] = $cvs->pluck('cost')->toArray();
            $data['labels'] = $cvs->pluck('timestamp')->toArray();
            $data['qty'] = $cvs->pluck('quantity')->toArray();
        } else {
            if (is_array($card_ids)) {
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
                $data['values'] = $cvs->pluck('cost')->toArray();
                $data['labels'] = $cvs->pluck('timestamp')->toArray();
                $data['qty'] = $cvs->pluck('quantity')->toArray();
            } else {
                
                $to = Carbon::create($to)->format('Y-m-d');
                $from = Carbon::create($from)->format('Y-m-d');
                $cvs = CardsSx::where('card_id', $card_ids)->whereBetween('date', [$to, $from])->orderBy('date', 'DESC')->get();
                $data['values'] = $cvs->pluck('sx')->toArray();
                $data['labels'] = $cvs->pluck('date')->toArray();
                $data['qty'] = $cvs->pluck('quantity')->toArray();
            }
        }
        
        if ($days > 2) {
            $data = $this->__groupGraphDataPerDay($days, $data, $card_ids, $boardGraph);
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
                            if (is_array($card_ids)) {
                                $salesDate = CardSales::whereIn('card_id', $card_ids)->where('timestamp', '<', $to)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                            } else {
                                $salesDate = CardsSx::where('card_id', $card_ids)->where('date', '<=', $to)->orderBy('date', 'DESC')->first();
                            }
                            if ($salesDate !== null) {
                                if (is_array($card_ids)) {
                                    $previousSx = CardSales::whereIn('card_id', $card_ids)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                                } else {
                                    $previousSx = $salesDate->sx;
                                }
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
                        if (is_array($card_ids)) {
                            $salesDate = CardSales::whereIn('card_id', $card_ids)->where('timestamp', '<', $to)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                        } else {
                            $salesDate = CardsSx::where('card_id', $card_ids)->where('date', '<=', $to)->orderBy('date', 'DESC')->first();
                        }
                        if ($salesDate !== null) {
                            if (is_array($card_ids)) {
                                $previousSx = CardSales::whereIn('card_id', $card_ids)->where('timestamp', 'like', '%' . $salesDate['DATE(timestamp)'] . '%')->avg('cost');
                            } else {
                                $previousSx = $salesDate->sx;
                            }
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
        if (is_array($card_ids)) {
            $last_timestamp = CardSales::whereIn('card_id', $card_ids)->select('timestamp')->orderBy('timestamp', 'DESC')->first();
        } else {
            $last_timestamp = CardsSx::where('card_id', $card_ids)->select('date')->orderBy('date', 'DESC')->first();
        }
        $finalData['last_timestamp'] = 'N/A';
        if (!empty($last_timestamp)) {
            $finalData['last_timestamp'] = Carbon::create($last_timestamp->timestamp)->format('F d Y \- h:i:s A');
        }
        if (is_array($card_ids)) {
            $sx_data = CardSales::getGraphSxWithIds($days, $data, $card_ids);
        } else {
            $sx_data = CardSales::getGraphSxWithCardId($days, $data);
        }
        $finalData['sx'] = $sx_data['sx'];
        $finalData['lastSx'] = $sx_data['lastSx'];
        return $finalData;
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
                    }
                    elseif ($previousValue == 0 && $flag1 == 0) {
                        if (is_array($card_ids)) {
                            $salesDate = CardSales::whereIn('card_id', $card_ids)->where('timestamp', '<', $dt)->orderBy('timestamp', 'DESC')->first(DB::raw('DATE(timestamp)'));
                        } elseif ($card_ids != 0) {
                            $salesDate = CardsSx::where('card_id', $card_ids)->where('date', '<=', $dt)->orderBy('date', 'DESC')->first();
                        } else {
                            $salesDate = CardsTotalSx::where('date', '<=', $dt)->orderBy('date', 'DESC')->first();
                        }
//                        dump($dt);
//                        dd($salesDate);
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

    public function boardDetails($board, $days = 2) {
        try {
            $board = Board::where('id', $board)->first();
            $follow = BoardFollow::where('board_id', '=', $board->id)->where('user_id', '=', auth()->user()->id)->first();
            $all_cards = json_decode($board->cards);
            $total_card_value = 0;
            if ($days == 180 || $days == 365 || $days == 1825) {
                $boardGraph = 1;
            } else {
                $boardGraph = 0;
            }
            foreach ($all_cards as $key => $card) {
                $each_cards[$key]['card_data'] = Card::where('id', (int) $card)->with('details')->first();
                $sx_data = CardSales::getSxAndLastSx($card);
                $sx = $sx_data['sx'];
                $total_card_value += $sx;
                $lastSx = $sx_data['lastSx'];

                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                $each_cards[$key]['card_data']['price'] = number_format($sx, 2, '.', '');
                $each_cards[$key]['card_data']['sx_value'] = number_format(abs($sx - $lastSx), 2, '.', '');
                $each_cards[$key]['card_data']['sx_icon'] = $sx_icon;
                $individual_sales_graph[] = $this->__cardData($card, $days, $boardGraph);
            }

            if ($boardGraph == 0) {
                $sales_graph['labels'] = $individual_sales_graph[0]['labels'];
                $sales_graph['sx'] = $individual_sales_graph[0]['sx'];
                $sales_graph['lastSx'] = $individual_sales_graph[0]['lastSx'];
                $individual_sales_graph_count = count($individual_sales_graph);
                foreach ($individual_sales_graph as $graphKey => $graph) {
                    if ($graphKey == 0) {
                        foreach ($individual_sales_graph[0]['values'] as $valueKey => $value) {
                            $count = count($individual_sales_graph[0]['values']);
                            if ($valueKey < ($count - 1)) {
                                $sales_graph['values'][$valueKey] = $value;
                                $sales_graph['qty'][$valueKey] = $individual_sales_graph[0]['qty'][$valueKey];
                                for ($i = 1; $i < $individual_sales_graph_count; $i++) {
                                    $sales_graph['qty'][$valueKey] += $individual_sales_graph[$i]['qty'][$valueKey];
                                    if (is_array($value)) {
                                        $sales_graph['values'][$valueKey][1] += $individual_sales_graph[$i]['values'][$valueKey][1];
                                    } else {
                                        $sales_graph['values'][$valueKey] += $individual_sales_graph[$i]['values'][$valueKey];
                                    }
                                }
                            }
                        }
                    } else {
                        $sales_graph['sx'] += $individual_sales_graph[$graphKey]['sx'];
                        $sales_graph['lastSx'] += $individual_sales_graph[$graphKey]['lastSx'];
                    }
                }
                $sales_graph['last_timestamp'] = $individual_sales_graph[0]['last_timestamp'];
            } else {
                $individual_sales_graph_count = count($individual_sales_graph);
                $flag = 0;
                foreach ($individual_sales_graph[0]['qty'] as $qtyKey => $qty) {
                    $sales_graph_value = $individual_sales_graph[0]['values'][$qtyKey];
                    $sales_graph_qty = $individual_sales_graph[0]['qty'][$qtyKey];
                    if ($qty != 0) {
                        $flag = 1;
                    }
                    for ($i = 1; $i < $individual_sales_graph_count; $i++) {
                        if ($individual_sales_graph[$i]['qty'][$qtyKey] != 0) {
                            $flag = 1;
                        }
                        $sales_graph_value[1] += $individual_sales_graph[$i]['values'][$qtyKey][1];
                        $sales_graph_qty += $individual_sales_graph[$i]['qty'][$qtyKey];
                    }
                    if ($flag == 1) {
                        $sales_graph['labels'][$qtyKey] = $individual_sales_graph[0]['labels'][$qtyKey];
                        $sales_graph['values'][$qtyKey] = $sales_graph_value;
                        $sales_graph['qty'][$qtyKey] = $sales_graph_qty;
                    }
                }
                $sales_graph['labels'] = array_values($sales_graph['labels']);
                $sales_graph['values'] = array_values($sales_graph['values']);
                $sales_graph['qty'] = array_values($sales_graph['qty']);
                $sales_graph['sx'] = $sales_graph['values'][count($sales_graph['values']) - 1][1];
                $sales_graph['lastSx'] = $sales_graph['values'][0][1];
                $sales_graph['last_timestamp'] = $individual_sales_graph[0]['last_timestamp'];
            }
            $finalData['sales_graph'] = $sales_graph;
            $sx = $finalData['sales_graph']['sx'];
            $lastSx = $finalData['sales_graph']['lastSx'];
            $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
            $finalData['doller_diff'] = str_replace('-', '', number_format((float) ($sx - $lastSx), 2, '.', ''));
            if ($sx != 0) {
                $pert_diff = ($lastSx > 0 ? ((($sx - $lastSx) / $lastSx) * 100) : 0);
                $finalData['pert_diff'] = number_format((float) $pert_diff, 2, '.', '');
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

    public function allBoards($days) {
        try {
            $user_id = auth()->user()->id;
            $boards = Board::where('user_id', $user_id)->get();
            $b_ids = BoardFollow::where('user_id', $user_id)->pluck('board_id');
            if (count($b_ids) > 0) {
                $board_follow = Board::whereIn('id', $b_ids)->get();
                $boards = $boards->merge($board_follow);
            }
            foreach ($boards as $key => $board) {
                $all_cards = json_decode($board->cards);
                if ($days == 180 || $days == 365 || $days == 1825) {
                    $boardGraph = 1;
                } else {
                    $boardGraph = 0;
                }
                foreach ($all_cards as $card) {
                    $individual_sales_graph[] = $this->__cardData($card, $days, $boardGraph);
                }
                if ($boardGraph == 0) {
                    $sales_graph['labels'] = $individual_sales_graph[0]['labels'];
                    $sales_graph['sx'] = $individual_sales_graph[0]['sx'];
                    $sales_graph['lastSx'] = $individual_sales_graph[0]['lastSx'];
                    $individual_sales_graph_count = count($individual_sales_graph);
                    foreach ($individual_sales_graph as $graphKey => $graph) {
                        if ($graphKey == 0) {
                            foreach ($individual_sales_graph[0]['values'] as $valueKey => $value) {
                                $count = count($individual_sales_graph[0]['values']);
                                if ($valueKey < ($count - 1)) {
                                    $sales_graph['values'][$valueKey] = $value;
                                    $sales_graph['qty'][$valueKey] = $individual_sales_graph[0]['qty'][$valueKey];
                                    for ($i = 1; $i < $individual_sales_graph_count; $i++) {
                                        $sales_graph['qty'][$valueKey] += $individual_sales_graph[$i]['qty'][$valueKey];
                                        if (is_array($value)) {
                                            $sales_graph['values'][$valueKey][1] += $individual_sales_graph[$i]['values'][$valueKey][1];
                                        } else {
                                            $sales_graph['values'][$valueKey] += $individual_sales_graph[$i]['values'][$valueKey];
                                        }
                                    }
                                }
                            }
                        } else {
                            $sales_graph['sx'] += $individual_sales_graph[$graphKey]['sx'];
                            $sales_graph['lastSx'] += $individual_sales_graph[$graphKey]['lastSx'];
                        }
                    }
                    $sales_graph['last_timestamp'] = $individual_sales_graph[0]['last_timestamp'];
                } else {
                    $individual_sales_graph_count = count($individual_sales_graph);
                    $flag = 0;
                    foreach ($individual_sales_graph[0]['qty'] as $qtyKey => $qty) {
                        $sales_graph_value = $individual_sales_graph[0]['values'][$qtyKey];
                        $sales_graph_qty = $individual_sales_graph[0]['qty'][$qtyKey];
                        if ($qty != 0) {
                            $flag = 1;
                        }
                        for ($i = 1; $i < $individual_sales_graph_count; $i++) {
                            if ($individual_sales_graph[$i]['qty'][$qtyKey] != 0) {
                                $flag = 1;
                            }
                            $sales_graph_value[1] += $individual_sales_graph[$i]['values'][$qtyKey][1];
                            $sales_graph_qty += $individual_sales_graph[$i]['qty'][$qtyKey];
                        }
                        if ($flag == 1) {
                            $sales_graph['labels'][$qtyKey] = $individual_sales_graph[0]['labels'][$qtyKey];
                            $sales_graph['values'][$qtyKey] = $sales_graph_value;
                            $sales_graph['qty'][$qtyKey] = $sales_graph_qty;
                        }
                    }
                    $sales_graph['labels'] = array_values($sales_graph['labels']);
                    $sales_graph['values'] = array_values($sales_graph['values']);
                    $sales_graph['qty'] = array_values($sales_graph['qty']);
                    $sales_graph['sx'] = $sales_graph['values'][count($sales_graph['values']) - 1][1];
                    $sales_graph['lastSx'] = $sales_graph['values'][0][1];
                    $sales_graph['last_timestamp'] = $individual_sales_graph[0]['last_timestamp'];
                }
                $boards[$key]['sales_graph'] = $sales_graph;

                $total_card_value = 0;
                foreach ($all_cards as $cardId) {
                    $sale = CardSales::getSx($cardId);
                    $total_card_value += $sale['sx'];
                }
                $boards[$key]['total_card_value'] = number_format((float) $total_card_value, 2, '.', '');
                $sx = $boards[$key]['sales_graph']['sx'];
                $lastSx = $boards[$key]['sales_graph']['lastSx'];
                $boards[$key]['sx_icon'] = $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                $boards[$key]['doller_diff'] = str_replace('-', '', number_format((float) ($sx - $lastSx), 2, '.', ''));
                if ($sx != 0) {
                    $pert_diff = ($lastSx > 0 ? ((($sx - $lastSx) / $lastSx) * 100) : 0);
                    $boards[$key]['pert_diff'] = number_format((float) $pert_diff, 2, '.', '');
                } else {
                    $boards[$key]['pert_diff'] = 0;
                }
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
            $boards['id'] = $board->id;
            $boards['name'] = $board->name;
            if ($days == 180 || $days == 365 || $days == 1825) {
                $boardGraph = 1;
            } else {
                $boardGraph = 0;
            }
            foreach ($all_cards as $card) {
                $individual_sales_graph[] = $this->__cardData($card, $days, $boardGraph);
            }
            if ($boardGraph == 0) {
                $sales_graph['labels'] = $individual_sales_graph[0]['labels'];
                $sales_graph['sx'] = $individual_sales_graph[0]['sx'];
                $sales_graph['lastSx'] = $individual_sales_graph[0]['lastSx'];
                $individual_sales_graph_count = count($individual_sales_graph);
                foreach ($individual_sales_graph as $graphKey => $graph) {
                    if ($graphKey == 0) {
                        foreach ($individual_sales_graph[0]['values'] as $valueKey => $value) {
                            $count = count($individual_sales_graph[0]['values']);
                            if ($valueKey < ($count - 1)) {
                                $sales_graph['values'][$valueKey] = $value;
                                $sales_graph['qty'][$valueKey] = $individual_sales_graph[0]['qty'][$valueKey];
                                for ($i = 1; $i < $individual_sales_graph_count; $i++) {
                                    $sales_graph['qty'][$valueKey] += $individual_sales_graph[$i]['qty'][$valueKey];
                                    if (is_array($value)) {
                                        $sales_graph['values'][$valueKey][1] += $individual_sales_graph[$i]['values'][$valueKey][1];
                                    } else {
                                        $sales_graph['values'][$valueKey] += $individual_sales_graph[$i]['values'][$valueKey];
                                    }
                                }
                            }
                        }
                    } else {
                        $sales_graph['sx'] += $individual_sales_graph[$graphKey]['sx'];
                        $sales_graph['lastSx'] += $individual_sales_graph[$graphKey]['lastSx'];
                    }
                }
                $sales_graph['last_timestamp'] = $individual_sales_graph[0]['last_timestamp'];
            } else {
                $individual_sales_graph_count = count($individual_sales_graph);
                $flag = 0;
                foreach ($individual_sales_graph[0]['qty'] as $qtyKey => $qty) {
                    $sales_graph_value = $individual_sales_graph[0]['values'][$qtyKey];
                    $sales_graph_qty = $individual_sales_graph[0]['qty'][$qtyKey];
                    if ($qty != 0) {
                        $flag = 1;
                    }
                    for ($i = 1; $i < $individual_sales_graph_count; $i++) {
                        if ($individual_sales_graph[$i]['qty'][$qtyKey] != 0) {
                            $flag = 1;
                        }
                        $sales_graph_value[1] += $individual_sales_graph[$i]['values'][$qtyKey][1];
                        $sales_graph_qty += $individual_sales_graph[$i]['qty'][$qtyKey];
                    }
                    if ($flag == 1) {
                        $sales_graph['labels'][$qtyKey] = $individual_sales_graph[0]['labels'][$qtyKey];
                        $sales_graph['values'][$qtyKey] = $sales_graph_value;
                        $sales_graph['qty'][$qtyKey] = $sales_graph_qty;
                    }
                }
                $sales_graph['labels'] = array_values($sales_graph['labels']);
                $sales_graph['values'] = array_values($sales_graph['values']);
                $sales_graph['qty'] = array_values($sales_graph['qty']);
                $sales_graph['sx'] = $sales_graph['values'][count($sales_graph['values']) - 1][1];
                $sales_graph['lastSx'] = $sales_graph['values'][0][1];
                $sales_graph['last_timestamp'] = $individual_sales_graph[0]['last_timestamp'];
            }
            $boards['sales_graph'] = $sales_graph;
            $total_card_value = 0;
            foreach ($all_cards as $cardId) {
                $sale = CardSales::getSx($cardId);
                $total_card_value += $sale['sx'];
            }
            $boards['total_card_value'] = number_format((float) $total_card_value, 2, '.', '');
            $sx = $boards['sales_graph']['sx'];
            $lastSx = $boards['sales_graph']['lastSx'];
            $boards['sx_icon'] = $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
            $boards['doller_diff'] = str_replace('-', '', number_format((float) ($sx - $lastSx), 2, '.', ''));
            if ($sx != 0) {
                $pert_diff = ($lastSx > 0 ? ((($sx - $lastSx) / $lastSx) * 100) : 0);
                $boards['pert_diff'] = number_format($pert_diff, 2, '.', '');
            } else {
                $boards['pert_diff'] = 0;
            }
            return response()->json(['status' => 200, 'data' => $boards], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

}
