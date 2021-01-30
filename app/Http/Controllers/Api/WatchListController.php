<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ebay\EbayItems;
use Illuminate\Http\Request;
use App\Models\WatchList;
use Carbon\Carbon;
use Illuminate\Support\Facades\Validator;


class WatchListController extends Controller
{
    protected $user_id;

    public function getUserWatchListIds(Request $request)
    {
        try {
            $this->user_id = auth()->user()->id;
            $watchlist =  WatchList::where('user_id', $this->user_id)->pluck('ebay_item_id');
            return response()->json(['status' => 200, 'data' => $watchlist], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function addToWatchList(Request $request)
    {
        try {
            $this->user_id = auth()->user()->id;
            $validator = Validator::make($request->all(), [
                'id' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json($validator, 500);
            }
            if (WatchList::where(['user_id' => $this->user_id, 'ebay_item_id' => $request->input('id')])->count() == 0) {
                WatchList::create(['user_id' => $this->user_id, 'ebay_item_id' => $request->input('id')]);
                return response()->json(['status' => 200, 'data' => ['message' => 'Added succefully']], 200);
            } else {
                return response()->json(['status' => 200, 'data' => ['message' => 'already added']], 200);
            }
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function removeToWatchList(Request $request)
    {
        try {
            $this->user_id = auth()->user()->id;
            $validator = Validator::make($request->all(), [
                'id' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json($validator, 500);
            }
            if (WatchList::where(['user_id' => $this->user_id, 'ebay_item_id' => $request->input('id')])->count() == 1) {
                WatchList::where(['user_id' => $this->user_id, 'ebay_item_id' => $request->input('id')])->delete();
                return response()->json(['status' => 200, 'data' => ['message' => 'Remove succefully']], 200);
            } else {
                return response()->json(['status' => 200, 'data' => ['message' => 'Not is watch list']], 200);
            }
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getEbayList(Request $request)
    {
        try {
            $this->user_id = auth()->user()->id;
            $page = $request->input('page', 1);
            $take = $request->input('take', 30);
            $search = $request->input('search', null);
            $filterBy = $request->input('filterBy', null);
            $skip = $take * $page;  
            $skip = $skip - $take;  
            
            $ids = WatchList::where('user_id', $this->user_id)->pluck('ebay_item_id');
            $items = EbayItems::whereIn('id', $ids)->with(['sellingStatus', 'listingInfo','card', 'card.value'])->where(function ($q) use ($search, $filterBy) {
                if($search != null) {
                    $q->where('title', 'like', '%' . $search . '%');
                }
                if($filterBy == 'buy_it_now') {
                    $q->orWhereHas('listingInfo', function ($qq) {
                        $qq->where('buyItNowAvailable',true);
                    });
                } 
            });

            if($filterBy != null) {
                if($filterBy == 'ending_soon') {
                    $date_one = Carbon::now()->addDay();
                    $date_one->setTimezone('UTC');
                    $date_two = Carbon::now()->setTimezone('UTC');
                    $items = $items->where("listing_ending_at", ">", $date_one);//->where("listing_ending_at", "<", $date_one);
                }   
            }
            $items = $items->orderBy('listing_ending_at', 'desc')->get();
            if($filterBy != null && $filterBy == 'price_low_to_high') {
                $items = $items->sortBy(function($query){
                    return $query->sellingStatus->currentPrice;
                });
            }
            $items = $items->skip($skip)->take($take);
            $data = [];
            foreach ($items as $key => $item) {
                $galleryURL = $item->galleryURL;
                if($item->pictureURLLarge != null){
                    $galleryURL = $item->pictureURLLarge;
                }else if($item->pictureURLSuperSize != null){
                    $galleryURL = $item->pictureURLSuperSize;
                }else if($galleryURL == null){
                    $galleryURL =  env('APP_URL').'/img/default-image.jpg';
                }
                $data[] = [
                    'id' => $item->id,
                    'title' => $item->title,
                    'galleryURL' => $galleryURL,
                    'price' => $item->sellingStatus->price,
                    'itemId' => $item->itemId,
                    'viewItemURL' => $item->viewItemURL,
                    'listing_ending_at' => $item->listing_ending_at,
                    'data' => $item,
                ];
            }
            return response()->json(['status' => 200, 'data' => $data, 'next'=>($page+1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }
}
