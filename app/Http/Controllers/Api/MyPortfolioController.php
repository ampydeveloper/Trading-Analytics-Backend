<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MyPortfolioCreateRequest;
use App\Models\Auth\User;
use App\Models\Card;
use App\Models\CardDetails;
use App\Models\MyPortfolio;
use App\Models\CardValues;
use App\Models\CardSales;
use App\Models\PortfolioUserValues;
use Exception;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Models\WatchList;

class MyPortfolioController extends Controller {

    protected $user_id;

    public function add(MyPortfolioCreateRequest $request) {
        try {
            $is_edit = $request->input('isedit', 'no');
            $cardId = $request->input('id', null);
            $price = (float) $request->input('price', 0);
            $price = (($price > 0) ? $price : 0);
            $quantity = (int) $request->input('quantity', 1);
            $quantity = (($quantity > 0) ? $quantity : 1);

            if ($cardId <= 0) {
                throw new Exception('Unable to add in your portfolio');
            }

            $this->user_id = auth()->user()->id;
            if ($is_edit == 'yes') {
                $myPortfolio = MyPortfolio::where('id', $cardId)->update([
                    'purchase_price' => $price,
                ]);

                return response()->json(['status' => 200, 'data' => 'Updated in your portfolio'], 200);
            } else {
                if ($quantity == 1) {
                    $myPortfolio = MyPortfolio::create([
                                'user_id' => $this->user_id,
                                'card_id' => $cardId,
                                'purchase_price' => $price,
                    ]);
                } else {
                    for ($i = 1; $i <= $quantity; $i++) {
                        $myPortfolio = MyPortfolio::create([
                                    'user_id' => $this->user_id,
                                    'card_id' => $cardId,
                                    'purchase_price' => $price,
                        ]);
                    }
                }
                return response()->json(['status' => 200, 'data' => 'Added in your portfolio'], 200);
            }

            return response()->json(['status' => 200, 'data' => 'Already added portfolio.'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function delete(MyPortfolioCreateRequest $request) {
        try {
//            $is_edit = $request->input('isedit', 'no');
            $cardId = $request->input('id', null);
//            $price = (float) $request->input('price', 0);
//            $price = (($price > 0) ? $price : 0);
//            $quantity = (int) $request->input('quantity', 1);
//            $quantity = (($quantity > 0) ? $quantity : 1);

            if ($cardId <= 0) {
                throw new Exception('Unable to add in your portfolio');
            }

            $this->user_id = auth()->user()->id;
            $myPortfolio = MyPortfolio::where('id', $cardId)->delete();

            return response()->json(['status' => 200, 'data' => 'Portfolio has been deleted.'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getListNavneet(Request $request) {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $filterBy = $request->input('filterBy', null);
        $skip = $take * $page;
        $skip = $skip - $take;
//        return response()->json(['status' => 200, 'data' => auth()->user(), 'next' => ($page + 1)], 200);
        try {
            $this->user_id = auth()->user()->id;
            $portfoliocards = MyPortfolio::where("user_id", $this->user_id)->get();
            $ptempcards = array();
            foreach ($portfoliocards as $pval) {
                $ptempcards[$pval->card_id] = $pval;
            }
            $card_ids = MyPortfolio::where("user_id", $this->user_id)->pluck('card_id');
            $cards = Card::whereIn('id', $card_ids)->where(function ($q) use ($filterBy) {
                        if ($filterBy != 'price_low_to_high' && $filterBy != null) {
                            $q->where('sport', $filterBy);
                        }
                    })->with('details')->get();
            $cards = $cards->skip($skip)->take($take);
            $data = [];
            foreach ($cards as $key => $card) {
                $cardValues = CardValues::where('card_id', $card->id)->orderBy('date', 'DESC')->limit(2)->get('avg_value');
                $sx = 0;
                $sx_icon = 'up';
                foreach ($cardValues as $i => $cv) {
                    if ($sx == 0) {
                        $sx = $cv->avg_value;
                    } else {
                        $sx = $sx - $cv->avg_value;
                    }
                }
                if ($sx < 0) {
                    $sx = abs($sx);
                    $sx_icon = 'down';
                }
                $purchase_price = (isset($ptempcards[$card->id]) ? $ptempcards[$card->id]->purchase_price : 0);
                $purchase_quantity = (isset($ptempcards[$card->id]) ? $ptempcards[$card->id]->purchase_quantity : 1);
                $portfolio_id = (isset($ptempcards[$card->id]) ? $ptempcards[$card->id]->id : 0);
                $differ = number_format((float) ($sx - $purchase_price), 2, '.', '');
                $sx_icon = (($differ < 0) ? 'down' : 'up');
                $data[] = [
                    'id' => $card->id,
                    'title' => $card->title,
                    'cardImage' => $card->cardImage,
                    'sx_value' => $sx,
                    'sx_icon' => $sx_icon,
                    'price' => (isset($card->details->currentPrice) ? $card->details->currentPrice : ''),
                    'purchase_price' => $purchase_price,
                    'differ' => $differ,
                    'portfolio_id' => $portfolio_id,
                    'purchase_quantity' => $purchase_quantity
                ];
            }

            usort($data, function($a, $b) {
                return $a['purchase_price'] <=> $b['purchase_price'];
            });

            return response()->json(['status' => 200, 'data' => $data, 'next' => ($page + 1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getList(Request $request) {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $filterBy = $request->input('filterBy', null);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {
            $this->user_id = auth()->user()->id;
            $ptempcards = MyPortfolio::where("user_id", $this->user_id)->get()->groupBy('card_id');
            $cards = Card::whereIn('id', array_keys($ptempcards->toArray()))->where(function ($q) use ($filterBy) {
                        if ($filterBy != 'price_low_to_high' && $filterBy != null) {
                            $q->where('sport', $filterBy);
                        }
                    })->with('details')->skip($skip)->take($take)->get();
//            $cards = $cards->skip($skip)->take($take);
            $card_grades = MyPortfolio::where("user_id", auth()->user()->id)->get(['card_id', 'grade'])->mapWithKeys(function ($crd) {
                        return [$crd->card_id => $crd->grade];
                    })->toArray();
            $data = [];
            foreach ($cards as $key => $card) {
//                $sx = CardSales::where('card_id', $card->id)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
                $sx = CardSales::where('card_id', $card->id)->orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
                $sx_count = count($sx);
                $sx = ($sx_count > 0) ? array_sum($sx->toArray()) / $sx_count : 0;

//                $sx = ($sx == null) ? 0 : $sx;
                foreach ($ptempcards[$card->id] as $ptempcard) {
                    $purchase_price = (isset($ptempcard) ? $ptempcard->purchase_price : 0);
                    $portfolio_id = (isset($ptempcard) ? $ptempcard->id : 0);
                    $differ = str_replace('-', '', number_format((float) ($sx - $purchase_price), 2, '.', ''));
                    $sx_icon = ((($sx - $purchase_price) < 0) ? 'down' : 'up');
                    $data[] = [
                        'id' => $card->id,
                        'title' => $card->title,
                        'cardImage' => $card->cardImage,
                        'sx_value' => $sx,
                        'sx_icon' => $sx_icon,
                        'price' => (isset($card->details->currentPrice) ? $card->details->currentPrice : ''),
                        'purchase_price' => $purchase_price,
                        'differ' => $differ,
                        'portfolio_id' => $portfolio_id,
                        'grade' => $card_grades[$card->id]
                    ];
                }
            }
            usort($data, function($a, $b) {
                return $a['purchase_price'] <=> $b['purchase_price'];
            });
            return response()->json(['status' => 200, 'data' => $data, 'next' => ($page + 1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getDashboardList(Request $request) {
        $take = $request->input('take', 6);
        try {
            $this->user_id = auth()->user()->id;
            $ptempcards = MyPortfolio::where("user_id", $this->user_id)->get()->groupBy('card_id');
            $cards = Card::whereIn('id', array_keys($ptempcards->toArray()))->with('details')->get();
            $cards = $cards->take($take);
            $data = [];
            foreach ($cards as $key => $card) {
                $data[] = [
                    'id' => $card->id,
                    'title' => $card->title,
                    'price' => (isset($card->details->currentPrice) ? $card->details->currentPrice : ''),
                ];
            }
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getFiltersData(Request $request) {
        try {
            $data = [
                'sport' => Card::select('sport')->groupby('sport')->pluck('sport'),
                'year' => Card::select('year')->groupby('year')->pluck('year'),
                'brand' => Card::select('brand')->groupby('brand')->pluck('brand'),
                'card' => Card::select('card')->groupby('card')->orderby('card', 'asc')->pluck('card'),
                'variation' => Card::select('variation')->groupby('variation')->pluck('variation'),
                'grade' => Card::select('grade')->groupby('grade')->pluck('grade'),
            ];
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function search(Request $request) {
//        $page = $request->input('page', 1);
//        $take = $request->input('take', 30);
        $filter = $request->input('filter');
//        $skip = $take * $page;
//        $skip = $skip - $take;
        try {
            $this->user_id = auth()->user()->id;
//            $card_ids = MyPortfolio::where("user_id", $this->user_id)->pluck('card_id');
            $cards = Card::where(function ($q) use ($filter) {
                        if ($filter['player'] != '') {
                            $q->where('player', 'like', '%' . $filter['player'] . '%');
                        }
                        if ($filter['sport'] != '') {
                            $q->where('sport', $filter['sport']);
                        }
                        if ($filter['year'] != '') {
                            $q->where('year', $filter['year']);
                        }
                        if ($filter['brand'] != '') {
                            $q->where('brand', $filter['brand']);
                        }
                        if ($filter['card'] != '') {
                            $q->where('card', $filter['card']);
                        }
                        if ($filter['rc'] != '') {
                            $q->where('rc', $filter['rc']);
                        }
                        if ($filter['variation'] != '') {
                            $q->where('variation', $filter['variation']);
                        }
                        if ($filter['grade'] != '') {
                            $q->where('grade', $filter['grade']);
                        }
                    })->get();
            ;
//                    })->whereNotIn('id', $card_ids)->get();
//            $cards = $cards->skip($skip)->take($take)->get();
//            return response()->json(['status' => 200, 'data' => $cards, 'next' => ($page + 1)], 200);
            return response()->json(['status' => 200, 'data' => $cards], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function portfolioValue(Request $request) {
        try {
//            self::calculateAllUserRank();
            $this->user_id = auth()->user()->id;
            $user = User::where("id", $this->user_id)->first();

            $purchaseVal = MyPortfolio::where("user_id", $this->user_id)->selectRaw('sum(purchase_price) as total_purchases')->pluck('total_purchases');
            $total_purchases = (isset($purchaseVal[0]) ? $purchaseVal[0] : 0);

//            $myPortfolioValues = PortfolioUserValues::where('user_id', $this->user_id)->orderBy('date', 'DESC')->limit(2)->get();
//            $myPortfolioValues = MyPortfolio::where('user_id', $this->user_id)->orderBy('updated_at', 'DESC')->first();
//            if (!empty($myPortfolioValues)) {
//                $updated = $myPortfolioValues->updated_at;
//            }

            //Calculate SX value
            $card_ids = MyPortfolio::where('user_id', $this->user_id)->pluck('card_id');
            $total_sx_value = 0;
            foreach ($card_ids as $card_id) {
//                $sx = CardSales::where('card_id', $card_id)->orderBy('timestamp', 'DESC')->limit(3)->avg('cost');
                $sx = CardSales::where('card_id', $card_id)->orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
                $sx_count = count($sx);
                $sx = ($sx_count > 0) ? array_sum($sx->toArray()) / $sx_count : 0;
                $total_sx_value += $sx;
            }


            $diff = ((float) $total_sx_value) - ((float) $total_purchases);
            $diff_icon = (($diff >= 0) ? 'up' : 'down');
            $diff = number_format(abs($diff), 2, '.', '');

            $percent_diff =  ($total_purchases>0? (($diff / $total_purchases)* 100): 0 ) ;
//            $percent_diff_icon = (($percent_diff >= 0) ? 'up' : 'down');
            $percent_diff = number_format(abs($percent_diff), 2, '.', '');

            return response()->json([
                        'status' => 200,
                        'value' => number_format($total_sx_value, 2, '.', ''), //$user->slab_value, 
//                        'rank' => $user->overall_rank,
                        'diff_value' => $diff,
                        'diff_icon' => $diff_icon,
//                        'updated' => $updated,
                        'total_purchases' => $total_purchases,
                        'percent_differ' => $percent_diff,
                        'percent_diff_icon' => $diff_icon
                            ], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public static function calculateAllUserRank() {
        $users = User::pluck('id');
        $userSlabValue = [];
        foreach ($users as $key => $user_id) {
            $card_ids = MyPortfolio::where("user_id", $user_id)->pluck('card_id');
            $details = CardDetails::whereIn('card_id', $card_ids)->get();
            $price = 0;
            foreach ($details as $key => $detail) {
                $price += (float) $detail->currentPrice;
            }
            $userSlabValue[$user_id] = $price;
        }
        arsort($userSlabValue);
        $i = 1;

        // User Portfolio Rank History
        $today = Carbon::now()->format('Y-m-d');

        foreach ($userSlabValue as $id => $value) {
            if (PortfolioUserValues::where(['user_id' => $id, 'date' => $today])->doesntExist()) {
                PortfolioUserValues::create(['user_id' => $id, 'value' => $value, 'date' => $today]);
            }
            User::where('id', $id)->update([
                'overall_rank' => $i,
                'slab_value' => $value,
            ]);
            $i++;
        }
    }

    public function getPortfolioGraphData($days = 2) {
        try {
            $data = ['values' => [], 'labels' => []];
            $this->user_id = auth()->user()->id;
            $myPortfolioValues = PortfolioUserValues::where('user_id', $this->user_id)->orderBy('date', 'DESC')->limit($days);
            $data['values'] = $myPortfolioValues->pluck('value')->toArray();
            $data['labels'] = $myPortfolioValues->pluck('date')->toArray();

            $data = (new CardController())->__groupGraphData($days, $data);

            if ($days == 2) {
                $labels = [];
                $values = [];
                for ($i = 0; $i <= 23; $i++) {
                    $labels[] = ($i < 10) ? '0' . $i . ':00' : $i . ':00';
                    $values[] = (count($data['values']) > 0 ) ? $data['values'][0] : 0;
                }
                $data['labels'] = $labels;
                $data['values'] = $values;
            } else {
                $data['values'] = array_reverse($data['values']);
                $data['labels'] = array_reverse($data['labels']);
            }
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function gradeCard(Request $request) {
        try {
            if ($request->has('my_portfolio_id') && $request->has('grade')) {
                MyPortfolio::whereId($request->get('my_portfolio_id'))->update(['grade' => $request->get('grade')]);
                return response()->json(['status' => 200, 'data' => 'Grading Successful!'], 200);
            }
            return response()->json(['status' => 200, 'data' => 'Grading Un-Successful!'], 500);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

}
