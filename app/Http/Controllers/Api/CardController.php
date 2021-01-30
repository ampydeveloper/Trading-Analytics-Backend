<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Imports\BaseballCardsImport;
use App\Imports\BasketballCardsImport;
use App\Imports\FootballCardsImport;
use App\Imports\SoccerCardsImport;
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

class CardController extends Controller
{
    public function getFetchItemForAdmin(Request $request)
    {
        $itemId = $request->input('itemid', null);
        if($itemId != null){
            try {
                $response = EbayService::getSingleItemDetails($itemId);
                if (isset($response['data'])) {
                    return response()->json(['status'=>200, 'data' => $response['data']], 200);
                }else{
                   return response()->json('No record found.',500); 
                }
            } catch (\Exception $e) {
                return response()->json($e->getMessage(),500);
            }
        }else{
            return response()->json('Invalid item id',500);
        }
        
    }
    
    public function getCardListForAdmin(Request $request)
    {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $search = $request->input('search', null);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {

            $cards = Card::where(function ($q) use ($request) {
                if($request->has('sport') && $request->input('sport') !=null){
                    $q->where('sport',$request->input('sport'));
                }
                if ($request->has('search') && $request->get('search') != '' && $request->get('search') != null) {
                    $searchTerm = strtolower($request->get('search'));
                    foreach (['player', 'year', 'brand', 'card', 'rc', 'variation', 'grade'] as $fl) {
                        $q->orWhere($fl, 'like', '%' . $searchTerm . '%');
                    }
                }
            })->get();
            $cards = $cards->skip($skip)->take($take);
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
            return response()->json(['status'=>200, 'data' => $data, 'next' => ($page + 1), 'sportsList' => $sportsList], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(),500);
        }
    }

    public function getCardList(Request $request)
    {
        try {
            $search = $request->input('search', null);
            $length = $request->input('length', 25);
            $start = $request->input('start', 0);

            $cards = Card::where(function ($q) use ($request) {
                if($request->has('sport') && $request->input('sport') !=null){
                    $q->where('sport',$request->input('sport'));
                }
            });

            if($search != null && $search != '') {
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
            }else{
                $cards = $cards->splice($start)->take($length)->get();
            }
            $total = count($cards);
            $lastPage = ceil($total / $length);

            return response()->json(['status'=>200, 'data'=>[
                "cards" => $cards,
                "per_page" => (int)$length,
                "start" => (int)($start+$length),
                "total" => $total]
            ], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(),500);
        }
    }


    public static function getDataFromEbay()
    {
        $cards = Card::where('readyforcron',1)->get();
        foreach ($cards as $card) {
            ProcessCardsForRetrivingDataFromEbay::dispatch($card);
        }
    }

    public static function complieCardsData()
    {
        $cards = Card::where('readyforcron',1)->get();
        foreach ($cards as $card) {
            ProcessCardsComplieData::dispatch($card->id);
        }
    }

    public static function compareEbayImages()
    {
        $cards = EbayItems::where('status', 0)->distinct('card_id')->get();
        foreach ($cards as $card) {
            CompareEbayImagesWithCardImages::dispatch($card->card_id);
        }
    }

    public function getCardDetails(int $id, Request $request)  
    {
        try {
            $rank = 0;
            CardDetails::orderBy('currentPrice', 'DESC')->get('card_id')->map(function($item, $key) use($id, &$rank){
                if($item['card_id'] == $id){
                    $rank = $key;
                }
            });
            $cards = Card::where('id',$id)->with('details')->firstOrFail()->toArray();
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
            $cards['sx'] = $sx; $cards['sx_icon'] = $sx_icon;
            $cards['rank'] = $rank;
            return response()->json(['status' => 200, 'data'=>$cards], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }


    public function getRecentList(Request $request)
    {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $search = $request->input('search', null);
        $top_trend = $request->input('top_trend', false);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {

            $cards = Card::where(function ($q) use ($request, $search) {
                if($request->has('sport') && $request->input('sport') !=null){
                    $q->where('sport',$request->input('sport'));
                }
                if($search !=null){
                    $q->where('title','like','%'.$search.'%');
                }
                if($request->has('is_featured') && $request->input('is_featured') !=null){
                    $q->where('is_featured',((bool) $request->input('is_featured')));
                }                
            })->where('active',1)->with('details');
            
            if($top_trend){ $cards = $cards->groupBy('sport'); }
            
            $cards = $cards->skip($skip)->take($take)->get();
            $cards = $cards->map(function($card, $key) {
                $data = $card;
                $cardValues = CardValues::where('card_id', $card->id)->orderBy('date', 'DESC')->limit(2)->get('avg_value');
                $sx = 0;
                $sx_icon = 'up';
                if(count($cardValues) == 2){
                    foreach ($cardValues as $i => $cv) {
                        if ($sx == 0) {
                            $sx = $cv->avg_value;
                        } else {
                            $sx = $sx - $cv->avg_value;
                        }
                    }
                }
                if ($sx < 0) {
                    $sx = abs($sx);
                    $sx_icon = 'down';
                }
                $data['sx_value'] = number_format((float)$sx, 2, '.', '');;
                $data['sx_icon'] = $sx_icon;
                $data['price'] = 0;
                if($card->details->currentPrice){ $data['price'] = $card->details->currentPrice; }
                return $data;
            });
            
            if($top_trend){ $cards = $cards->sortBy('price'); }
            
            return response()->json(['status' => 200, 'data' => $cards, 'next' => ($page + 1)], 200);
        } catch (\Exception $e) {
            dump($e);
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getSmartKeyword(Request $request)
    {
        try {
            $data = Card::where('player', 'like', '%' . $request->input('keyword') . '%')->distinct('player')->where('active',1)->get()->take(10);
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

    public function getSmartKeywordWithData(Request $request)
    {
        try {
            $data = Card::with(['details'])->where(function($q) use ($request){
                // $search = explode(' ', $request->input('search'));
                $search = $request->input('search');
                $q->orWhere('title', 'like', '%' . $search . '%');
                // foreach ($search as $key => $keyword) {
                // }
            })->where('active',1)->get()->take(10)->map(function($item, $key){
                $temp = $item;
                $temp['rank'] = 0;
                return $temp;
            });
            return response()->json(['status' => 200, 'data' => $data, 'keyword' => $request->input('search')], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage().' '.$e->getLine(), 500);
        }
    }

    public function getPopularPickCards(Request $request)
    {
        try {
            $data = Card::with(['details'])->where(function($q) use ($request){
                // $search = explode(' ', $request->input('search'));
                $search = $request->input('search');
                // $q->orWhere('title', 'like', '%' . $search . '%');
                // foreach ($search as $key => $keyword) {
                // }
            })->orderBy('updated_at', 'desc')->get()->take($request->input('take', 10))->map(function($item, $key){
                $temp = $item;
                $temp['rank'] = 0;
                return $temp;
            });
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage().' '.$e->getLine(), 500);
        }
    }

    

    public function getCardRank($id)
    {
        $rank = 0;
        CardDetails::orderBy('currentPrice', 'DESC')->get('card_id')->map(function($item, $key) use($id, &$rank){
            if($item['card_id'] == $id){
                $rank = $key;
            }
        });
        return $rank;
    }

    public function getSmartKeywordOnlyName(Request $request)
    {
        try {
            $data = Card::select('player')->where('player', 'like', '%' . $request->input('keyword') . '%')->where('sport',$request->input('sport'))->where('active',1)->distinct()->get()->take(10);
            return response()->json(['status' => 200, 'data' => $data, 'keyword' => $request->input('keyword')], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
    
    public function setFeatured(Request $request){
        try {
            $is_featured =  (bool) $request->input('is_featured');
            $data = Card::where('id', $request->input('id'))->first();
            if(isset($data->id)){
                $cardSport = trim($data->sport);
                //echo $cardSport.' : '.$is_featured;
                /** update is featured start **/
                Card::where('id', $request->input('id'))->update(array('is_featured'=>$is_featured));
                if($is_featured){Card::where('sport', $cardSport)->where('id', '<>', $request->input('id'))->update(array('is_featured'=>0));}
                /** update is featured end **/
                
                return response()->json(['status' => 200, 'message' => (($is_featured)?'Card set as featured':'Card remove from featured')], 200);
            }else{
                return response()->json(['status' => 500, 'message' => 'Invalid card id!'], 500);
            }
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
    
    public function setStatus(Request $request){
        try {
            $status =  (bool) $request->input('status');
            $data = Card::where('id', $request->input('id'))->first();
            if(isset($data->id)){
                //echo $cardSport.' : '.$status;
                /** update is featured start **/
                Card::where('id', $request->input('id'))->update(array('active'=>$status));
                /** update is featured end **/
                
                return response()->json(['status' => 200, 'message' => (($status)?'Card active successfully':'Card in-active successfully')], 200);
            }else{
                return response()->json(['status' => 500, 'message' => 'Invalid card id!'], 500);
            }
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
    public function setSx(Request $request){
        try {
            $is_sx =  (bool) $request->input('is_sx');
            $data = Card::where('id', $request->input('id'))->first();
            if(isset($data->id)){
                Card::where('id', $request->input('id'))->update(array('is_sx'=>$is_sx));
                
                return response()->json(['status' => 200, 'message' => ('Card SX pro value updated.')], 200);
            }else{
                return response()->json(['status' => 500, 'message' => 'Invalid card id!'], 500);
            }
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function create(Request $request)
    {
        try {
            $data = Card::where('sport', $request->input('sport'))->orderBy('updated_at', 'desc')->first();
            
            Card::create([
                'row_id'=> (int)$data->row_id + 1,
                'sport'=> $request->input('sport'),
                'player'=> $request->input('player'),
                'year'=> $request->input('year'),
                'brand'=> $request->input('brand'),
                'card'=> $request->input('card'),
                'rc'=> $request->input('rc'),
                'variation'=> $request->input('variation'),
                'grade'=> $request->input('grade'),
                'qualifiers'=> $request->input('qualifiers'),
                'qualifiers2'=> $request->input('qualifiers2'),
                'qualifiers3'=> $request->input('qualifiers3'),
                'qualifiers4'=> $request->input('qualifiers4'),
                'qualifiers5'=> $request->input('qualifiers5'),
                'qualifiers6'=> $request->input('qualifiers6'),
                'qualifiers7'=> $request->input('qualifiers7'),
                'qualifiers8'=> $request->input('qualifiers8'),
                'is_featured' => ((bool) $request->input('is_featured')),
            ]);
            return response()->json(['status' => 200, 'message' => 'Card Created'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
    public function createSales(Request $request)
    {
        try {
//            $data = Card::where('sport', $request->input('sport'))->orderBy('updated_at', 'desc')->first();
            
            CardSales::create([
                'card_id'=> $request->input('card_id'),
                'timestamp'=> Carbon::create($request->input('timestamp'))->format('Y-m-d h:i:s'),
                'quantity'=> $request->input('quantity'),
                'cost'=> $request->input('cost'),
                'source'=> $request->input('source'),
            ]);
            return response()->json(['status' => 200, 'message' => 'Sales data added.'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getStoxtickerData(){
        try{
            $data = ['total'=> 0, 'sale'=> 0, 'avg_sale' => 0, 'change'=> 0, 'change_arrow' => 'up', 'last_updated' => ''];
            $data['total'] = Card::count();
            // $cardIds = Card::pluck('id');
            $last_updated = CardValues::orderBy('date', 'DESC')->limit(1)->first('updated_at');
            if($last_updated != null){ $data['last_updated'] = $last_updated->updated_at->format('F d Y \- h:i:s A'); }
            $updateDates = CardValues::orderBy('date', 'DESC')->limit(2)->pluck('date');
            if(count($updateDates) > 0){
                // Latest Date sum
                $data['sale'] = CardValues::where('date', $updateDates[0])->sum('avg_value');
                $data['avg_sale'] = round($data['sale']);
            }
            if (count($updateDates) > 1) {
                // Diff sum in latest and latest-1 
                $data['change'] = $data['sale'] - CardValues::where('date', $updateDates[1])->sum('avg_value');
                if($data['change'] < 0){ $data['change_arrow'] = 'down'; }
                $data['change'] = abs($data['change']);
            }
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getDashboardGraphData(Request $request,$days=2){
        try {
            $data = ['values' => [], 'labels' => []];
            $card_id = ($request->input('card_id')?$request->input('card_id'):1);
            $cvs = CardSales::where('card_id', $card_id)->groupBy('timestamp')->orderBy('timestamp', 'DESC')->limit($days);
            $data['values'] = $cvs->pluck('quantity')->toArray();
            $data['labels'] = $cvs->pluck('timestamp')->toArray();
            
//            $cvs = CardValues::select('date', 'avg_value')->groupBy('date')->orderBy('date', 'DESC')->limit($days);
//            $data['values'] = $cvs->pluck('avg_value')->toArray();
//            $data['labels'] = $cvs->pluck('date')->toArray();

            $data = $this->__groupGraphData($days, $data);
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

    public function getCardGraphData($card_id, $days=2){
        try {
            $data = ['values' => [], 'labels' => []];

            $cids = explode('|', (string) $card_id);
            if(count($cids) > 1){
                $datas = ['values1' => [], 'labels' => [], 'values2' => []];
                foreach ($cids as $ind => $cid) {
                    $cvs = CardValues::where('card_id', $cid)->orderBy('date', 'DESC')->limit($days);
                    $data['values'] = $cvs->pluck('avg_value')->toArray();
                    $data['labels'] = $cvs->pluck('date')->toArray();

                    $data = $this->__groupGraphData($days, $data);

                    if(count($datas['labels']) == 0){ $datas['labels'] = $data['labels']; }
                    $datas['values'.++$ind] = $data['values'];
                }
                unset($data['values']);
                $data['labels'] = array_reverse($datas['labels']);
                $data['values1'] = array_reverse($datas['values1']);
                $data['values2'] = array_reverse($datas['values2']);
                $data['rank1'] = $this->getCardRank($cids[0]);
                $data['rank2'] = $this->getCardRank($cids[1]);
            }else{
                $cvs = CardValues::where('card_id', $card_id)->orderBy('date', 'DESC')->limit($days);
                $data['values'] = $cvs->pluck('avg_value')->toArray();
                $data['labels'] = $cvs->pluck('date')->toArray();

                $data = $this->__groupGraphData($days, $data);
                
                $data['values'] = array_reverse($data['values']);
                $data['labels'] = array_reverse($data['labels']);
                $data['rank'] = $this->getCardRank($card_id);
            }

            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function __groupGraphData($days, $data){
        $months = null; $years = null;
        if ($days > 30 && $days <= 365) { $months = (int) ($days / 30); }
        if ($days > 365) { $years = (int) ($days / 365); }

        $grouped = []; $max = 0;
        if($months != null){
            $max = $months;
            foreach ($data['labels'] as $ind => $dt) {
                $dt = explode('-', $dt); $dt = sprintf('%s-%s', $dt[0], $dt[1]);
                if(!in_array($dt, array_keys($grouped))){ $grouped[$dt] = 0; }
                $grouped[$dt] += round($data['values'][$ind]);
            }
        }
        if ($years != null) {
            $max = $years;
            foreach ($data['labels'] as $ind => $dt) {
                $dt = explode('-', $dt)[0];
                if(!in_array($dt, array_keys($grouped))){ $grouped[$dt] = 0; }
                $grouped[$dt] += round($data['values'][$ind]);
            }
        }

        if($grouped != []){
            $data['values'] = array_values($grouped);
            $data['labels'] = array_keys($grouped);
            $data['values'] = array_splice($data['values'], 0, $max);
            $data['labels'] = array_splice($data['labels'], 0, $max);
        }
        return $data;
    }

    public function addRequestSlab(Request $request)
    {
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

    public function getRequestSlabListForAdmin(Request $request)
    {
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
    
    public function uploadSlabForExcelImport(Request $request)
    {
        try {
            if($request->input('for') == 'baseball') {
                Excel::import(new BaseballCardsImport,request()->file('file'));
            }else if ($request->input('for') == 'basketball') {
                Excel::import(new BasketballCardsImport,request()->file('file'));
            }else if ($request->input('for') == 'football') {
                Excel::import(new FootballCardsImport,request()->file('file'));
            }else if ($request->input('for') == 'soccer') {
                Excel::import(new SoccerCardsImport,request()->file('file'));
            }
            return response()->json(['message' =>'Card imported successfully' ], 200);
        } catch (\Exception $e) {
            \Log::error($e);
            return response()->json($e->getMessage(), 500);
        }
    }
}
