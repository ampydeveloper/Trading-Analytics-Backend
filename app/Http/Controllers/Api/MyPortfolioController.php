<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\MyPortfolioCreateRequest;
use App\Models\Auth\User;
use App\Models\Card;
use App\Models\CardDetails;
use App\Models\MyPortfolio;
use App\Models\CardValues;
use App\Models\PortfolioUserValues;
use Exception;
use Illuminate\Http\Request;
use Carbon\Carbon;



class MyPortfolioController extends Controller
{
    protected $user_id;

    public function add(MyPortfolioCreateRequest $request)
    {
        try {
            $this->user_id = auth()->user()->id;
            if (MyPortfolio::where('user_id', $this->user_id)->where('card_id', $request->input('id'))->count() == 0) {
                $myPortfolio =  MyPortfolio::create([
                    'user_id' => $this->user_id,
                    'card_id' => $request->input('id')
                ]);
                if (!$myPortfolio) {
                    throw new Exception('Unable to add in your portfolio');
                }
                return response()->json(['status' => 200, 'data' => 'Added in your portfolio'], 200);
            }
            return response()->json(['status' => 200, 'data' => 'Already added'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getList(Request $request)
    {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $filterBy = $request->input('filterBy', null);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {
            $this->user_id = auth()->user()->id;
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
                $sx = 0; $sx_icon = 'up';
                foreach ($cardValues as $i => $cv) {
                    if($sx == 0){ $sx = $cv->avg_value; }else{ $sx = $sx - $cv->avg_value; }
                }
                if($sx < 0){ $sx = abs($sx); $sx_icon = 'down'; }
                $data[] = [
                    'id' => $card->id,
                    'title' => $card->title,
                    'cardImage' => $card->cardImage,
                    'sx_value' => $sx,
                    'sx_icon' => $sx_icon,
                    'price' => $card->details->currentPrice
                ];
            }
            
            usort($data, function($a, $b) {
                return $a['price'] <=> $b['price'];
            });
            return response()->json(['status' => 200, 'data' => $data, 'next' => ($page + 1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getFiltersData(Request $request)
    {
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

    public function search(Request $request)
    {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $filter = $request->input('filter');
        $skip = $take * $page;
        $skip = $skip - $take;
        try {
            $this->user_id = auth()->user()->id;
            $card_ids = MyPortfolio::where("user_id", $this->user_id)->pluck('card_id');
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
            })->whereNotIn('id', $card_ids)->get();
            $cards = $cards->skip($skip)->take($take);
            return response()->json(['status' => 200, 'data' => $cards, 'next' => ($page + 1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function portfolioValue(Request $request)
    {
        try {
            self::calculateAllUserRank();
            $this->user_id = auth()->user()->id;
            $user = User::where("id", $this->user_id)->first();
            
            $diff = 0;
            $diff_icon = 'up';
            $updated = '';
            $myPortfolioValues = PortfolioUserValues::where('user_id', $this->user_id)->orderBy('date', 'DESC')->limit(2)->get()->toArray();
            foreach ($myPortfolioValues as $pv) {
                if ($diff == 0) {
                    $updated = Carbon::create($pv['updated_at'])->format('F d Y \- h:i:s A');
                    $diff = $pv['value'];
                } else {
                    $diff = $diff - $pv['value'];
                }
            }
            if ($diff < 0) {
                $diff = abs($diff);
                $diff_icon = 'down';
            }
            
            return response()->json([
                'status' => 200, 
                'value' => $user->slab_value, 
                'rank' => $user->overall_rank, 
                'diff_value' => $diff, 
                'diff_icon' => $diff_icon, 
                'updated' => $updated
            ], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public static function calculateAllUserRank()
    {
        $users = User::pluck('id');
        $userSlabValue = [];
        foreach ($users as $key => $user_id) {
            $card_ids = MyPortfolio::where("user_id", $user_id)->pluck('card_id');
            $details = CardDetails::whereIn('card_id',$card_ids)->get();
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

    public function getPortfolioGraphData($days = 2){
        try {
            $data = ['values' => [], 'labels' => []];
            $this->user_id = auth()->user()->id;
            $myPortfolioValues = PortfolioUserValues::where('user_id', $this->user_id)->orderBy('date', 'DESC')->limit($days);
            $data['values'] = $myPortfolioValues->pluck('value')->toArray();
            $data['labels'] = $myPortfolioValues->pluck('date')->toArray();
            
            $data = (new CardController())->__groupGraphData($days, $data);
            
            if($days == 2) {
                $labels = [];
                $values = [];
                for($i = 0; $i<=23; $i++){
                    $labels[] = ($i < 10) ? '0'.$i.':00' : $i.':00';
                    $values[] = (count($data['values']) > 0 ) ? $data['values'][0] : 0;
                }
                $data['labels'] = $labels;
                $data['values'] = $values;
            }else{
                $data['values'] = array_reverse($data['values']);
                $data['labels'] = array_reverse($data['labels']);
            }
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
}