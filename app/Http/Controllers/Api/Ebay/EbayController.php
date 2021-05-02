<?php

namespace App\Http\Controllers\Api\Ebay;

use App\Http\Controllers\Controller;
use App\Models\AdvanceSearchOptions;
use Illuminate\Http\Request;
use App\Models\Ebay\EbayItems;
use App\Models\Ebay\EbayItemSellerInfo;
use App\Models\Card;
use App\Models\CardDetails;
use App\Models\CardValues;
use App\Models\CardSales;
use App\Models\Ebay\EbayItemSpecific;
use App\Models\SeeProblem;
use App\Models\UserSearch;
use Carbon\Carbon;
use Validator;
use App\Models\Ebay\EbayItemCategories;
use App\Models\Ebay\EbayItemListingInfo;
use App\Models\Ebay\EbayItemSellingStatus;
use App\Models\AppSettings;

class EbayController extends Controller {

    private $defaultListingImage;

    public function __construct() {
        $this->defaultListingImage = $this->defaultListingImage;
        $settings = AppSettings::first();
        if ($settings) {
            $this->defaultListingImage = $settings->listing_image;
        }
    }

    public function getItemsListForAdmin(Request $request) {
//        dump(Carbon::now()->toDateTimeString());
//        dump($request->all());
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $search = $request->input('search', null);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {

            $itemsSpecsIds = EbayItemSpecific::where('value', 'like', '%' . $search . '%')->groupBy('itemId')->pluck('itemId');
            $items = EbayItems::with(['sellingStatus', 'card', 'listingInfo'])->where(function ($q) use ($itemsSpecsIds, $search, $request) {
                        if ($search != null) {
                            if (count($itemsSpecsIds) > 0) {
                                $q->whereIn('itemId', $itemsSpecsIds);
                            } else {
                                $q->where('title', 'like', '%' . $search . '%');
                                $q->orWhere('id', $search);
                                $q->orWhere('card_id', $search);
                                $q->orWhere('itemId', $search);
                            }
                        }
                        if ($request->input('sport') == 'random_bin') {
                            $q->orWhere('is_random_bin', 1);
                        }

                        if ($request->input('filter_by') == 'ending_soon') {
                            $q->orWhere('listing_ending_at', '>', Carbon::now()->toDateTimeString());
                        }
                    })->where('sold_price', '')->orderBy('updated_at', 'desc')->get();
            $items = $items->skip($skip)->take($take);
//            dd($items->toArray());
            $data = [];
            foreach ($items as $key => $item) {
                $galleryURL = $item->galleryURL;
                if ($item->pictureURLLarge != null) {
                    $galleryURL = $item->pictureURLLarge;
                } else if ($item->pictureURLSuperSize != null) {
                    $galleryURL = $item->pictureURLSuperSize;
                } else if ($galleryURL == null) {
                    $galleryURL = $this->defaultListingImage;
                }
                $data[] = [
                    'id' => $item->id,
                    'card_id' => $item->card_id,
                    'title' => $item->title,
                    'galleryURL' => $galleryURL,
                    'price' => ($item->sellingStatus ? $item->sellingStatus->price : 0),
                    'itemId' => $item->itemId,
                    'viewItemURL' => $item->viewItemURL,
                    'listing_ending_at' => $item->listing_ending_at,
                    'status' => $item->status
                ];
            }
            $sportsList = Card::select('sport')->distinct()->pluck('sport');
            return response()->json(['status' => 200, 'data' => $data, 'next' => ($page + 1), 'sportsList' => $sportsList], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getItemsListForAdminForSport(Request $request) {
//        dump(Carbon::now()->toDateTimeString());
//        dump($request->all());
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {
            if ($request->input('sport') == 'random_bin') {
                $items = EbayItems::with(['sellingStatus', 'card', 'listingInfo'])->where(function ($q) use ($request) {
                            $q->where('is_random_bin', 2);
                            if ($request->input('filter_by') == 'ending_soon') {
                                $q->orWhere('listing_ending_at', '>', Carbon::now()->toDateTimeString());
                            }
                        })->where('sold_price', '')->orderBy('updated_at', 'desc')->get();
            } else {
                $items = EbayItems::whereHas('card', function($q) use($request) {
                            $q->where('sport', $request->input('sport'));
                        })->with(['sellingStatus', 'card', 'listingInfo'])->where(function ($q) use ($request) {
                            if ($request->input('filter_by') == 'ending_soon') {
                                $q->orWhere('listing_ending_at', '>', Carbon::now()->toDateTimeString());
                            }
                        })->where('sold_price', '')->orderBy('updated_at', 'desc')->get();
            }
            $items = $items->skip($skip)->take($take);
            $data = [];
            foreach ($items as $key => $item) {
                $galleryURL = $item->galleryURL;
                if ($item->pictureURLLarge != null) {
                    $galleryURL = $item->pictureURLLarge;
                } else if ($item->pictureURLSuperSize != null) {
                    $galleryURL = $item->pictureURLSuperSize;
                } else if ($galleryURL == null) {
                    $galleryURL = $this->defaultListingImage;
                }
                $data[] = [
                    'id' => $item->id,
                    'title' => $item->title,
                    'galleryURL' => $galleryURL,
                    'price' => ($item->sellingStatus ? $item->sellingStatus->price : 0),
                    'itemId' => $item->itemId,
                    'viewItemURL' => $item->viewItemURL,
                    'listing_ending_at' => $item->listing_ending_at,
                    'status' => $item->status
                ];
            }
            $sportsList = Card::select('sport')->distinct()->pluck('sport');
            return response()->json(['status' => 200, 'data' => $data, 'next' => ($page + 1), 'sportsList' => $sportsList], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getListingEdit($listing_id) {
        try {
//            $data = CardSales::where('id', $sale_id)->first();
            $items = EbayItems::where('id', $listing_id)->with(['sellingStatus', 'card', 'listingInfo'])->first();
            $itemsSpecsIds = EbayItemSpecific::where('itemId', $items->itemId)->get();
            $itemSellerInfo = EbayItemSellerInfo::where('itemId', $items->itemId)->first();

//            $items = $items->skip($skip)->take($take);
//            $data = [];
//            foreach ($items as $key => $item) {
//                $galleryURL = $item->galleryURL;
//                if ($item->pictureURLLarge != null) {
//                    $galleryURL = $item->pictureURLLarge;
//                } else if ($item->pictureURLSuperSize != null) {
//                    $galleryURL = $item->pictureURLSuperSize;
//                } else if ($galleryURL == null) {
//                    $galleryURL = $this->defaultListingImage;
//                }
//                $data[] = [
//                    'id' => $item->id,
//                    'title' => $item->title,
//                    'galleryURL' => $galleryURL,
//                    'price' => ($item->sellingStatus ? $item->sellingStatus->price : 0),
//                    'itemId' => $item->itemId,
//                    'viewItemURL' => $item->viewItemURL,
//                    'listing_ending_at' => $item->listing_ending_at,
//                    'status' => $item->status
//                ];
//            }
//            $sportsList = Card::select('sport')->distinct()->pluck('sport');
            $data = [
                'items' => $items,
                'itemsSpecs' => $itemsSpecsIds,
                'itemSellerInfo' => $itemSellerInfo,
            ];
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getItemsListSoldAdmin(Request $request) {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $search = $request->input('search', null);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {
            $itemsSpecsIds = EbayItemSpecific::where('value', 'like', '%' . $search . '%')->groupBy('itemId')->pluck('itemId');
            $items = EbayItems::where('sold_price', '>', 0)->with(['sellingStatus', 'card', 'listingInfo'])->where(function ($q) use ($itemsSpecsIds, $search) {
                        if ($search != null) {
                            if (count($itemsSpecsIds) > 0) {
                                $q->whereIn('itemId', $itemsSpecsIds);
                            } else {
                                $q->where('title', 'like', '%' . $search . '%');
                                $q->orWhere('card_id', $search);
                                $q->orWhere('itemId', $search);
                            }
                        }
                    })->orderBy('updated_at', 'desc')->get();
            $items = $items->skip($skip)->take($take);
            $data = [];
            foreach ($items as $key => $item) {
                $galleryURL = $item->galleryURL;
                if ($item->pictureURLLarge != null) {
                    $galleryURL = $item->pictureURLLarge;
                } else if ($item->pictureURLSuperSize != null) {
                    $galleryURL = $item->pictureURLSuperSize;
                } else if ($galleryURL == null) {
                    $galleryURL = $this->defaultListingImage;
                }
                $data[] = [
                    'id' => $item->id,
                    'card_id' => $item->card_id,
                    'title' => $item->title,
                    'galleryURL' => $galleryURL,
                    'price' => ($item->sellingStatus ? $item->sellingStatus->price : 0),
                    'itemId' => $item->itemId,
                    'viewItemURL' => $item->viewItemURL,
                    'listing_ending_at' => $item->listing_ending_at,
                    'status' => $item->status,
                    'sold_price' => $item->sold_price
                ];
            }
            $sportsList = Card::select('sport')->distinct()->pluck('sport');
            return response()->json(['status' => 200, 'data' => $data, 'next' => ($page + 1), 'sportsList' => $sportsList], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getSpecificListForAdmin(Request $request) {
        $page = $request->input('page', 1);
        $card_id = (int) $request->input('card_id');
        $take = $request->input('take', 30);
        $search = $request->input('search', null);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {
//            $itemsSpecsIds = EbayItemSpecific::where('value', 'like', '%' . $search . '%')->where('itemId', 'like', '%' . $search . '%')->groupBy('itemId')->pluck('itemId');
            $items = EbayItems::where('card_id', '=', $card_id)->with(['sellingStatus', 'card', 'listingInfo'])->where(function ($q) use ($search) {
                        if ($search != null) {
//                    if (count($itemsSpecsIds) > 0) {
//                        $q->whereIn('itemId', $itemsSpecsIds);
//                    } else {
                            $q->where('title', 'like', '%' . $search . '%');
                            $q->orWhere('card_id', $search);
                            $q->orWhere('itemId', $search);
//                    }
                        }
                    })->orderBy('updated_at', 'desc')->get();
            $items = $items->skip($skip)->take($take);
            $data = [];
            foreach ($items as $key => $item) {
                $galleryURL = $item->galleryURL;
                if ($item->pictureURLLarge != null) {
                    $galleryURL = $item->pictureURLLarge;
                } else if ($item->pictureURLSuperSize != null) {
                    $galleryURL = $item->pictureURLSuperSize;
                } else if ($galleryURL == null) {
                    $galleryURL = $this->defaultListingImage;
                }
                $data[] = [
                    'id' => $item->id,
                    'card_id' => $item->card_id,
                    'title' => $item->title,
                    'galleryURL' => $galleryURL,
                    'price' => ($item->sellingStatus ? $item->sellingStatus->price : 0),
                    'itemId' => $item->itemId,
                    'viewItemURL' => $item->viewItemURL,
                    'listing_ending_at' => $item->listing_ending_at,
                    'status' => $item->status
                ];
            }
            return response()->json(['status' => 200, 'data' => $data, 'next' => ($page + 1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function changeEbayStatusAdmin(Request $request) {
//        $validator = Validator::make($request->all(), [
//                    'id' => 'required',
//                    'status' => 'required',
//        ]);
//        if ($validator->fails()) {
//            return response()->json($validator->errors(), 422);
//        }
        $idArr = $request->input('id');
        if (is_array($idArr)) {
            $idArr = $request->input('id');
            foreach ($idArr as $id) {
                (EbayItems::where('id', $id)->first())->update(['status' => $request->input('status')]);
            }
        } else {
            $idArr = $request->input('id');
            (EbayItems::where('id', $idArr)->first())->update(['status' => $request->input('status')]);
        }

        return response()->json(['status' => 200, 'message' => 'Status Changed successfully'], 200);
    }

    public function changeCardStatusAdmin(Request $request) {
        $idArr = $request->input('id');
        if ($request->input('status') != 4) {
            if (is_array($idArr)) {
                foreach ($idArr as $id) {
                    (Card::where('id', $id)->first())->update(['active' => $request->input('status')]);
                }
            } else {
                (Card::where('id', $idArr)->first())->update(['active' => $request->input('status')]);
            }
        } else {
            (Card::where('id', $idArr)->first())->delete();
        }
        return response()->json(['status' => 200, 'message' => 'Status changed successfully'], 200);
    }

    public function changeSalesStatusAdmin(Request $request) {
        $idArr = $request->input('id');
        (CardSales::where('id', $idArr)->first())->delete();
        return response()->json(['status' => 200, 'message' => 'Sales deleted successfully'], 200);
    }

    public function saveSoldPriceAdmin(Request $request) {
        try {
            $item_details = EbayItems::where('id', $request->input('id'))->first();
            if (($item_details)->update(['sold_price' => $request->input('sold_price'), 'status' => 2])) {
                $item_type = null;
                if ($item_details->listing_info_id != null) {
                    $ebayItemListingInfo = EbayItemListingInfo::where('id', $item_details->listing_info_id)->first();
                    $item_type = $ebayItemListingInfo->listingType;
                }
                CardSales::create([
                    'card_id' => $item_details->card_id,
                    'timestamp' => Carbon::now()->format('Y-m-d h:i:s'),
                    'quantity' => 1,
                    'cost' => $request->input('sold_price'),
                    'source' => 'Slabstox',
                    'type' => $item_type,
                ]);
                return response()->json(['status' => 200, 'message' => 'Sold price Chnaged successfully'], 200);
            }
            return response()->json(['status' => 400, 'message' => 'Status change failed'], 400);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getItemsList(Request $request) {
        try {
            $filter = $request->input('filter', null);
            $searchCard = $request->input('searchCard', null);
            if ($filter != null && $this->checkForAdvanceSearch($filter)) {
                $items = $this->_advanceSearch($request);
            } else {
                $items = $this->_basicSearch($request);
            }

            if ($searchCard != null && $searchCard != "null") {
                $cards = Card::where('id', $searchCard)->with('details')->get();
                UserSearch::create(['card_id' => $searchCard]);
            } else {
//dd($items['card_ids']);   
//                if ($filter != null && $this->checkForAdvanceSearch($filter)) {
//                    $cards = Card::whereIn('id', $items['cards'])->with('details')->limit(18)->get();
//                } else {
                $cards = Card::whereIn('id', $items['card_ids'])->with('details')->limit(18)->get();
//                }
                if (!empty($request->input('search')) && $request->input('search') != null) {
                    UserSearch::create(['search' => $request->input('search')]);
                }
            }
//            die('red2');
            foreach ($cards as $ind => $card) {
//                $sx = CardSales::where('card_id', $card->id)->orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
//                $sx_count = count($sx);
//                $sx = ($sx_count > 0) ? array_sum($sx->toArray()) / $sx_count : 0;
//                $lastSx = CardSales::where('card_id', $card->id)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
//                $count = count($lastSx);
//                $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;

                $sx_data = CardSales::getSxAndLastSx($card->id);
                $sx = $sx_data['sx'];
                $lastSx = $sx_data['lastSx'];

                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';

                $data['sx_value'] = number_format((float) $sx, 2, '.', '');
                $data['sx_icon'] = $sx_icon;
                $cards[$ind]['price'] = 0;
                if (isset($card->details->currentPrice)) {
                    $cards[$ind]['price'] = $card->details->currentPrice;
                }
            }

            return response()->json(['status' => 200, 'items' => $items, 'cards' => $cards], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage() . ' - ' . $e->getLine(), 500);
        }
    }

    public function getItemsListForUser(Request $request) {
        try {
            $filter = $request->input('filter', null);
            $searchCard = $request->input('searchCard', null);
            if ($filter != null && $this->checkForAdvanceSearch($filter)) {
                $items = $this->_advanceSearch($request);
            } else {
                $items = $this->_basicSearch($request);
            }

            if ($searchCard != null && $searchCard != "null") {
                $cards = Card::where('id', $searchCard)->with('details')->get();
                UserSearch::create(['card_id' => $searchCard, 'user_id' => auth()->user()->id]);
            } else {
                $cards = Card::whereIn('id', $items['cards'])->with('details')->limit(18)->get();
                if (!empty($request->input('search')) && $request->input('search') != null) {
                    UserSearch::create(['search' => $request->input('search'), 'user_id' => auth()->user()->id]);
                }
            }
            foreach ($cards as $ind => $card) {
//                $sx = CardSales::where('card_id', $card->id)->orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
//                $sx_count = count($sx);
//                $sx = ($sx_count > 0) ? array_sum($sx->toArray()) / $sx_count : 0;
//                $lastSx = CardSales::where('card_id', $card->id)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
//                $count = count($lastSx);
//                $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;

                $sx_data = CardSales::getSxAndLastSx($card->id);
                $sx = $sx_data['sx'];
                $lastSx = $sx_data['lastSx'];

                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                $data['sx_value'] = number_format((float) $sx, 2, '.', '');
                $data['sx_icon'] = $sx_icon;
                $cards[$ind]['price'] = 0;
                if (isset($card->details->currentPrice)) {
                    $cards[$ind]['price'] = $card->details->currentPrice;
                }
            }

            return response()->json(['status' => 200, 'items' => $items, 'cards' => $cards], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage() . ' - ' . $e->getLine(), 500);
        }
    }

    public function getRecentAuctionList(Request $request) {
        try {
//            $filter = $request->input('filter', null);
//            $searchCard = $request->input('searchCard', null);
//            if ($filter != null && $this->checkForAdvanceSearch($filter)) {
//                $items = $this->_advanceSearch($request);
//            } else {
//                $items = $this->_basicSearch($request);
//            }
//            if ($searchCard != null) {
//                $cards = Card::where('id', $searchCard)->with('details')->get();
//            } else {
//                $cards = Card::whereIn('id', $items['cards'])->with('details')->get();
//            }
//            foreach ($cards as $ind => $card) {
//                $cardValues = CardValues::where('card_id', $card->id)->orderBy('date', 'DESC')->limit(2)->get('avg_value');
//                $sx = 0;
//                $sx_icon = 'up';
//                if (count($cardValues) == 2) {
//                    foreach ($cardValues as $cv) {
//                        if ($sx == 0) {
//                            $sx = $cv['avg_value'];
//                        } else {
//                            $sx = $sx - $cv['avg_value'];
//                        }
//                    }
//                }
//                if ($sx < 0) {
//                    $sx = abs($sx);
//                    $sx_icon = 'down';
//                }
//                $cards[$ind]['sx_value'] = number_format((float) $sx, 2, '.', '');
//                $cards[$ind]['sx_icon'] = $sx_icon;
//                $cards[$ind]['price'] = 0;
//                if (isset($card->details->currentPrice)) {
//                    $cards[$ind]['price'] = $card->details->currentPrice;
//                }
//            }
//            $cardsIds = [];
//        $search = $request->input('search', null);
//        $page = $request->input('page', 1);
            $take = $request->input('take', 30);
//        $searchCard = $request->input('searchCard', null);
//        $filterBy = $request->input('filterBy', null);
//        $skip = $take * $page;
//        $skip = $skip - $take;
//        $cardsId = null;
//        if ($search != null && $search != '') {
//            $cardsId = Card::where(function($q) use ($search) {
//                        $search = explode(' ', $search);
//                        foreach ($search as $key => $keyword) {
//                            $q->orWhere('title', 'like', '%' . $keyword . '%');
//                        }
//                    })->pluck('id');
//        }
            $items = EbayItems::with(['category', 'card', 'card.value', 'details', 'playerDetails', 'condition', 'sellerInfo', 'listingInfo', 'sellingStatus', 'shippingInfo', 'specifications'])
                            ->where('status', 0)->orderBy('created_at', 'desc')->take($take)->get();
//        if ($filterBy == 'ending_soon') {
//            $date_one = Carbon::now()->addDay();
//            $date_one->setTimezone('UTC');
//            // $date_two = Carbon::now()->setTimezone('UTC');
//            $items = $items->where("listing_ending_at", ">", $date_one); //->where("listing_ending_at", "<", $date_one);
//            $items = $items->where('status', 0)->orderBy('listing_ending_at', 'asc')->get();
//        } else {
//            $items = $items->where('status', 0)->orderBy('updated_at', 'desc')->get();
//        }
//        if ($filterBy == 'price_low_to_high') {
//            $items = $items->sortBy(function($query) {
//                return $query->sellingStatus->currentPrice;
//            });
//        }
            $items = $items->map(function($item, $key) {
//            $cardsIds[] = $item->card_id;
                $galleryURL = $item->galleryURL;
                if ($item->pictureURLLarge != null) {
                    $galleryURL = $item->pictureURLLarge;
                } else if ($item->pictureURLSuperSize != null) {
                    $galleryURL = $item->pictureURLSuperSize;
                } else if ($galleryURL == null) {
                    $galleryURL = $this->defaultListingImage;
                }
                $listingTypeval = ($item->listingInfo ? $item->listingInfo->listingType : '');

//                $sx = CardSales::where('card_id', $item->card_id)->orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
//                $sx_count = count($sx);
//                $sx = ($sx_count > 0) ? array_sum($sx->toArray()) / $sx_count : 0;
//                $lastSx = CardSales::where('card_id', $item->card_id)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
//                $count = count($lastSx);
//                $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;

                $sx_data = CardSales::getSxAndLastSx($item->card_id);
                $sx = $sx_data['sx'];
                $lastSx = $sx_data['lastSx'];

                $price_diff = (float) str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'galleryURL' => $galleryURL,
                    'price' => ($item->sellingStatus ? $item->sellingStatus->price : 0),
                    'itemId' => $item->itemId,
                    'viewItemURL' => $item->viewItemURL,
                    'listing_ending_at' => $item->listing_ending_at,
                    'showBuyNow' => ($listingTypeval != 'Auction') ? true : false,
                    'data' => $item,
                    'sx_value' => number_format((float) $sx, 2, '.', ''),
                    'sx_icon' => (($sx - $lastSx) >= 0) ? 'up' : 'down',
                    'price_diff' => $price_diff,
                ];
            });
//        return ['data' => $items, 'next' => ($page + 1), 'cards' => $cardsIds];

            return response()->json(['status' => 200, 'items' => $items], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage() . ' - ' . $e->getLine(), 500);
        }
    }

    public function checkForAdvanceSearch($filter) {
        $flag = false;
        if ($filter['sports'] != null) {
            $flag = true;
        }
        if ($filter['year'] != null) {
            $flag = true;
        }
        if ($filter['number'] != null) {
            $flag = true;
        }
        if ($filter['card'] != null) {
            $flag = true;
        }
        if ($filter['series'] != null) {
            $flag = true;
        }
        if ($filter['grade'] != null) {
            $flag = true;
        }
        if ($filter['rookie'] != null && $filter['rookie'] == '1') {
            $flag = true;
        }

        if ($filter['player'] != null) {
            $flag = true;
        }
        if ($filter['team'] != null) {
            $flag = true;
        }
        if ($filter['season'] != null) {
            $flag = true;
        }

        if ($filter['listing'] != null) {
            $flag = true;
        }

        return $flag;
    }

    private function _advanceSearch($request) {
        $filter = $request->input('filter');
        $itemsIds = [];
        $cardsIds = [];
        $cards = [];

        $itemIds = EbayItemSpecific::where(function ($q) use ($filter) {
                    foreach ($filter as $k => $v) {
                        if ($v != null && $v != "null") {
                            $q->orWhere('value', 'like', "%$v");
                        }
                    }
                })->pluck('itemId')->unique()->toArray();
        $cards = EbayItems::whereIn('itemId', $itemIds)->pluck('card_id')->unique()->values()->toArray();

        // $search = $request->input('search', null);
        $cardsId = null;
        if ($filter['player'] != null) {
            $search = $filter['player'];
            $cardsId = Card::where(function($q) use ($search) {
                        $search = explode(' ', $search);
                        foreach ($search as $key => $keyword) {
                            $q->orWhere('title', 'like', '%' . $keyword . '%');
                        }
                    })->pluck('id');
        }

        if ($filter['team'] != null) {
            $ids = EbayItemSpecific::where('name', 'Team')->where('value', 'like', '%' . $filter['team'] . '%')->groupBy('itemId')->pluck('itemId');
            foreach ($ids as $key => $id) {
                if (!in_array($id, $itemsIds)) {
                    array_push($itemsIds, $id);
                }
            }
        }

        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $filterBy = $request->input('filterBy', null);
        $skip = $take * $page;
        $skip = $skip - $take;

        $ebayitems = EbayItems::with(['category', 'card', 'card.value', 'details', 'playerDetails', 'condition', 'sellerInfo', 'listingInfo', 'sellingStatus', 'shippingInfo', 'specifications'])->where(function ($q) use ($filter, $itemsIds, $filterBy, $cards) {
            if (!empty($itemsIds)) {
                $q->whereIn('itemId', $itemsIds);
            }
            if (!empty($cards)) {
                $q->whereIn('card_id', $cards);
            }
            if ($filter['sports'] != null) {
                $q->whereHas('card', function ($qq) use ($filter) {
                    $qq->where('sport', strtolower($filter['sports']));
                });
            }
            if ($filter['listing'] == 'buy_it_now') {
                $q->orWhereHas('listingInfo', function ($qq) {
                    $qq->where('listingType', '!=', 'Auction');
                });
            }

            if ($filter['player'] != null) {
                $q->where('title', 'like', '%' . $filter['player'] . '%');
            }
            if ($filterBy == 'buy_it_now') {
                $q->orWhereHas('listingInfo', function ($qq) {
                    $qq->where('listingType', '!=', 'Auction');
                });
            }
        });

        if ($filterBy == 'ending_soon') {
            $date_one = Carbon::now()->addDay();
            $date_one->setTimezone('UTC');
            // $date_two = Carbon::now()->setTimezone('UTC');
            $ebayitems = $ebayitems->where("listing_ending_at", ">", $date_one); //->where("listing_ending_at", "<", $date_one);
            $ebayitems = $ebayitems->where('status', 0)->orderBy('listing_ending_at', 'asc')->get();
        } else {
            $ebayitems = $ebayitems->where('status', 0)->orderBy('updated_at', 'desc')->get();
        }
        if ($filterBy == 'price_low_to_high') {
            $ebayitems = $ebayitems->sortBy(function($query) {
                return $query->sellingStatus->currentPrice;
            });
        }

        $ebayitems = $ebayitems->skip($skip)->take($take)->map(function($item, $key) use(&$cardsIds) {
            $cardsIds[] = $item->card_id;
            $galleryURL = $item->galleryURL;
            if ($item->pictureURLLarge != null) {
                $galleryURL = $item->pictureURLLarge;
            } else if ($item->pictureURLSuperSize != null) {
                $galleryURL = $item->pictureURLSuperSize;
            } else if ($galleryURL == null) {
                $galleryURL = $this->defaultListingImage;
            }
            $listingTypeVal = ($item->listingInfo ? $item->listingInfo->listingType : '');
            return [
                'id' => $item->id,
                'title' => $item->title,
                'galleryURL' => $galleryURL,
                'price' => ($item->sellingStatus ? $item->sellingStatus->price : 0),
                'itemId' => $item->itemId,
                'viewItemURL' => $item->viewItemURL,
                'listing_ending_at' => $item->listing_ending_at,
                'showBuyNow' => ($listingTypeVal != 'Auction') ? true : false,
                'data' => $item,
            ];
        });

        return ['data' => $ebayitems, 'next' => ($page + 1), 'cards' => $cardsIds, 'card_ids' => $cardsId];
    }

    private function _basicSearch($request) {
        $cardsIds = [];
        $search = $request->input('search', null);
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $searchCard = $request->input('searchCard', null);
        $filterBy = $request->input('filterBy', null);
        $skip = $take * $page;
        $skip = $skip - $take;
        $cardsId = null;
        if ($search != null) {
            $cardsId = Card::where(function($q) use ($search) {
                        $search = explode(' ', $search);
                        foreach ($search as $key => $keyword) {
                            $q->orWhere('title', 'like', '%' . $keyword . '%');
                        }
                    })->distinct('player')->limit(18)->pluck('id');
        }
        $items = EbayItems::with(['category', 'card', 'card.value', 'details', 'playerDetails', 'condition', 'sellerInfo', 'listingInfo', 'sellingStatus', 'shippingInfo', 'specifications'])->where(function ($q) use ($cardsId, $searchCard, $filterBy) {
            if ($searchCard != null) {
                $q->where('card_id', $searchCard);
            } elseif ($cardsId != null) {
                $q->whereIn('card_id', $cardsId);
            }

            if ($filterBy == 'buy_it_now') {
                $q->orWhereHas('listingInfo', function ($qq) {
                    $qq->where('listingType', '!=', 'Auction');
                });
            }
        });
        if ($filterBy == 'ending_soon') {
            $date_one = Carbon::now()->addDay();
            $date_one->setTimezone('UTC');
            // $date_two = Carbon::now()->setTimezone('UTC');
            $items = $items->where("listing_ending_at", ">", $date_one); //->where("listing_ending_at", "<", $date_one);
            $items = $items->where('status', 0)->orderBy('listing_ending_at', 'asc')->get();
        } else {
            $items = $items->where('status', 0)->orderBy('updated_at', 'desc')->get();
        }
        if ($filterBy == 'price_low_to_high') {
            $items = $items->sortBy(function($query) {
                return $query->sellingStatus->currentPrice;
            });
        }

        $items = $items->skip($skip)->take($take)->map(function($item, $key) use(&$cardsIds) {
            $cardsIds[] = $item->card_id;
            $galleryURL = $item->galleryURL;
            if ($item->pictureURLLarge != null) {
                $galleryURL = $item->pictureURLLarge;
            } else if ($item->pictureURLSuperSize != null) {
                $galleryURL = $item->pictureURLSuperSize;
            } else if ($galleryURL == null) {
                $galleryURL = $this->defaultListingImage;
            }
            $listingTypeval = ($item->listingInfo ? $item->listingInfo->listingType : '');
            return [
                'id' => $item->id,
                'title' => $item->title,
                'galleryURL' => $galleryURL,
                'price' => ($item->sellingStatus ? $item->sellingStatus->price : 0),
                'itemId' => $item->itemId,
                'viewItemURL' => $item->viewItemURL,
                'listing_ending_at' => $item->listing_ending_at,
                'showBuyNow' => ($listingTypeval != 'Auction') ? true : false,
                'data' => $item,
            ];
        });

        return ['data' => $items, 'next' => ($page + 1), 'cards' => $cardsIds, 'card_ids' => $cardsId];
    }

    public function createEbayItemForAdmin(Request $request) {
        try {
            \DB::beginTransaction();
            $data = $request->all();
            $data['card_id'] = ($data['card_id'] == "null" ? null : $data['card_id']);
            $item = EbayItems::where('card_id', $data['card_id'])->where('itemId', $data['itemId'])->first();
            if ($item == null) {
                $cat_id = 1;
                if (isset($data['details']['PrimaryCategoryID'])) {
                    $cat_id = EbayItemCategories::where('categoryId', $data['details']['PrimaryCategoryID'])->first()['id'];
                } else {
                    if (isset($data['category'])) {
                        $cat_id = $data['category'];
                    }
                }
                if (isset($data['details']['PictureURL'])) {
                    if (is_array($data['details']['PictureURL']) && count($data['details']['PictureURL']) > 0) {
                        $pictureURLLarge = $data['details']['PictureURL'][0];
                        $pictureURLSuperSize = $data['details']['PictureURL'][count($data['details']['PictureURL']) - 1];
                    } else {
                        $pictureURLLarge = $data['details']['PictureURL'];
                        $pictureURLSuperSize = $data['details']['PictureURL'];
                    }
                } else if (isset($data['image']) && !empty($data['image'])) {
                    $pictureURLLarge = $data['image'];
                    $pictureURLSuperSize = $data['image'];
                } else {
                    $pictureURLLarge = null;
                    $pictureURLSuperSize = null;
                }
                $auction_end = null;
                if (!empty($data['auction_end'])) {
                    $auction_end_str = $data['auction_end'] / 1000;
                    $auction_end = date('Y-m-d H:i:s', $auction_end_str);
                }
                $selling_status = EbayItemSellingStatus::create([
                            'itemId' => $data['details']['ebay_id'],
                            'currentPrice' => $data['price'],
                            'convertedCurrentPrice' => $data['price'],
                            'sellingState' => $data['price'],
                            'timeLeft' => $auction_end,
                ]);
                $seller_info = EbayItemSellerInfo::create([
                            'itemId' => $data['details']['ebay_id'],
                            'sellerUserName' => $data['seller_name'],
                            'positiveFeedbackPercent' => $data['positiveFeedbackPercent'],
                            'seller_contact_link' => $data['seller_contact_link'],
                            'seller_store_link' => $data['seller_store_link']
                ]);
                $listing_info = EbayItemListingInfo::create([
                            'itemId' => $data['details']['ebay_id'],
                            'startTime' => '',
                            'endTime' => $auction_end,
                            'listingType' => (isset($data['listing_type']) && $data['listing_type'] == true ? 'Auction' : 'Listing'),
                ]);

                EbayItems::create([
                    'card_id' => $data['card_id'],
                    'itemId' => $data['details']['ebay_id'],
                    'title' => $data['title'],
                    'category_id' => $cat_id,
                    'globalId' => isset($data['details']['Site']) ? 'EBAY-' . $data['details']['Site'] : null,
                    'galleryURL' => isset($data['details']['GalleryURL']) ? $data['details']['GalleryURL'] : null,
                    'viewItemURL' => isset($data['details']['ViewItemURLForNaturalSearch']) ? $data['details']['ViewItemURLForNaturalSearch'] : null,
                    'autoPay' => isset($data['details']['AutoPay']) ? $data['details']['AutoPay'] : null,
                    'postalCode' => isset($data['details']['PostalCode']) ? $data['details']['PostalCode'] : null,
                    'location' => isset($data['details']['Location']) ? $data['details']['Location'] : null,
                    'country' => isset($data['details']['Country']) ? $data['details']['Country'] : null,
                    'returnsAccepted' => isset($data['details']['ReturnPolicy']['ReturnsAccepted']) == 'ReturnsNotAccepted' ? false : true,
                    'condition_id' => isset($data['details']['ConditionID']) ? $data['details']['ConditionID'] : 1,
                    'pictureURLLarge' => $pictureURLLarge,
                    'pictureURLSuperSize' => $pictureURLSuperSize,
                    'listing_ending_at' => $auction_end,
                    'is_random_bin' => array_key_exists('random_bin', $data) ? (bool) $data['random_bin'] : 0,
                    'seller_info_id' => isset($seller_info) ? $seller_info->id : null,
                    'selling_status_id' => isset($selling_status) ? $selling_status->id : null,
                    'listing_info_id' => isset($listing_info) ? $listing_info->id : null,
                ]);

                foreach ($data['specifics'] as $key => $speci) {
                    if (isset($speci['Value'])) {
                        if ($speci['Value'] != "N/A") {
                            EbayItemSpecific::create([
                                'itemId' => $data['details']['ebay_id'],
                                'name' => $speci['Name'],
                                'value' => is_array($speci['Value']) ? implode(',', $speci['Value']) : $speci['Value']
                            ]);
                        }
                    } else {
                        EbayItemSpecific::create([
                            'itemId' => $data['details']['ebay_id'],
                            'name' => $key,
                            'value' => is_array($speci) ? implode(',', $speci) : $speci
                        ]);
                    }
                }


                \DB::commit();

                return response()->json(['status' => 200, 'data' => ['message' => 'Added successfully.']], 200);
            } else {
                $auction_end = null;
                if (!empty($data['auction_end'])) {
                    $auction_end_str = $data['auction_end'] / 1000;
                    $auction_end = date('Y-m-d H:i:s', $auction_end_str);
                }
                $ebayItem = EbayItems::where('id', $item['id'])->first();
                if ($ebayItem) {
                    $ebayItem->update([
                        'title' => $data['title'],
                        'viewItemURL' => $data['web_link'],
                        'location' => $data['location'],
                        'returnsAccepted' => $data['ReturnPolicy'],
                        'pictureURLLarge' => $data['image'],
                        'listing_ending_at' => $auction_end,
                    ]);
                }

                $ebayItemSellerInfo = EbayItemSellerInfo::where('id', $item['seller_info_id'])->first();
                if ($ebayItemSellerInfo) {
                    $ebayItemSellerInfo->update([
                        'sellerUserName' => $data['seller_name'],
                        'positiveFeedbackPercent' => $data['positiveFeedbackPercent'],
                        'seller_contact_link' => $data['seller_contact_link'],
                        'seller_store_link' => $data['seller_store_link']
                    ]);
                }
                //                foreach ($data['specifics'] as $speci) {
                //                    EbayItemSpecific::where('id', $speci['id'])->update([
                //                        'value' => $speci['value']
                //                    ]);
                //                }
                $ebayItemListingInfo = EbayItemListingInfo::where('id', $item['listing_info_id'])->first();
                if ($ebayItemListingInfo) {
                    $ebayItemListingInfo->update([
                        'startTime' => '',
                        'endTime' => $auction_end,
                        'listingType' => (isset($data['listing_type']) && $data['listing_type'] == true ? 'Auction' : 'Listing'),
                    ]);
                }

                \DB::commit();
                return response()->json(['status' => 200, 'data' => ['message' => 'Updated successfully.']], 200);
            }
        } catch (\Exception $e) {
//            dd($e);
            \Log::error($e);
            \DB::rollBack();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getListingCategories() {
        try {
            $all_categories = EbayItemCategories::get();
            return response()->json(['status' => 200, 'data' => $all_categories], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function searchedCardsByUserForAdmin(Request $request) {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {
            $data = UserSearch::with(['userDetails', 'cardDetails'])->skip($skip)->take($take)->get();
            return response()->json(['status' => 200, 'data' => $data, 'next' => ($page + 1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getInternalItemsList(Request $request) {
        try {
            $filter = $request->input('filter', null);
            $searchCard = $request->input('searchCard', null);
            if ($filter != null && $this->checkForAdvanceSearch($filter)) {
                $items = $this->_advanceSearch($request);
            } else {
                $items = $this->_internalBasicSearch($request);
            }

            if ($searchCard != null) {
                $cards = Card::where('id', $searchCard)->get();
            } else {
                $cards = Card::whereIn('id', $items['cards'])->get();
            }
            return response()->json(['status' => 200, 'items' => $items, 'cards' => $cards], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    private function _internalBasicSearch($request) {
        $cardsIds = [];
        $search = $request->input('search', null);
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $filterBy = $request->input('filterBy', null);
        $searchCard = $request->input('searchCard', null);
        $skip = $take * $page;
        $skip = $skip - $take;
        // $itemsSpecsIds = EbayItemSpecific::where('value', 'like', '%' . $search . '%')->groupBy('itemId')->pluck('itemId');
        $itemsSpecsIds = [];
        $items = EbayItems::with(['category', 'card', 'card.value', 'details', 'playerDetails', 'condition', 'sellerInfo', 'listingInfo', 'sellingStatus', 'shippingInfo', 'specifications'])->where(function ($q) use ($itemsSpecsIds, $search, $filterBy, $searchCard) {
            $q->where('title', 'like', '%' . $search . '%');
            if ($searchCard != null) {
                $q->where('card_id', $searchCard);
            }
            if (count($itemsSpecsIds) > 0) {
                $q->orWhereIn('itemId', $itemsSpecsIds);
            }
            if ($filterBy == 'buy_it_now') {
                $q->orWhereHas('listingInfo', function ($qq) {
                    $qq->where('listingType', '!=', 'Auction');
                });
            }
        });
        if ($filterBy != null) {
            if ($filterBy == 'ending_soon') {
                $date_one = Carbon::now()->addDay();
                $date_one->setTimezone('UTC');
                // $date_two = Carbon::now()->setTimezone('UTC');
                $items = $items->where("listing_ending_at", ">", $date_one); //->where("listing_ending_at", "<", $date_one);
            }
        }
        $items = $items->where('status', 0)->orderBy('updated_at', 'desc')->get();
        if ($filterBy != null && $filterBy == 'price_low_to_high') {
            $items = $items->sortBy(function($query) {
                return $query->sellingStatus->currentPrice;
            });
        }
        $items = $items->skip($skip)->take($take)->map(function($item, $key) use(&$cardsIds) {
            $cardsIds[] = $item->card_id;
            $galleryURL = $item->galleryURL;
            if ($item->pictureURLLarge != null) {
                $galleryURL = $item->pictureURLLarge;
            } else if ($item->pictureURLSuperSize != null) {
                $galleryURL = $item->pictureURLSuperSize;
            } else if ($galleryURL == null) {
                $galleryURL = $this->defaultListingImage;
            }
            $listingTypeVal = ($item->listingInfo ? $item->listingInfo->listingType : '');
            return [
                'id' => $item->id,
                'title' => $item->title,
                'galleryURL' => $galleryURL,
                'price' => ($item->sellingStatus ? $item->sellingStatus->price : 0),
                'itemId' => $item->itemId,
                'viewItemURL' => $item->viewItemURL,
                'listing_ending_at' => $item->listing_ending_at,
                'showBuyNow' => ($listingTypeVal != 'Auction') ? true : false,
                'data' => $item,
            ];
        });
        return ['data' => $items, 'next' => ($page + 1), 'cards' => $cardsIds];
    }

    public function getItemsDetails(Request $request) {
        try {
            $data['items'] = EbayItems::where('id', $request->input('id'))
                    ->with(['category', 'card', 'card.value', 'details', 'playerDetails', 'condition', 'sellerInfo', 'listingInfo', 'sellingStatus', 'shippingInfo', 'specifications'])
                    ->first();

            $sx_data = CardSales::getSxAndLastSx($data['items']->card->id);
            $sx = $sx_data['sx'];
            $lastSx = $sx_data['lastSx'];

            $sx_icon = null;
            if ($sx != null && $lastSx != null) {
                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
            }
            $data['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
            $data['sx'] = number_format((float) $sx, 2, '.', '');
            $data['sx_icon'] = $sx_icon;
            return response()->json(['status' => 200, 'data' => $data], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getItemsIds(Request $request) {
        $ids = EbayItems::pluck('id');
        return response()->json(['status' => 200, 'data' => $ids], 200);
    }

    public function getItemsForRelatedListing(Request $request) {
        $card_id = $request->input('card_id', null);
        $id = $request->input('id', '1');
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $search = $request->input('search', null);
        $filterBy = $request->input('filterBy', null);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {
            // $itemsSpecsIds = EbayItemSpecific::where('value', 'like', '%' . $search . '%')->groupBy('itemId')->pluck('itemId');
            $itemsSpecsIds = [];
            $items = EbayItems::with(['sellingStatus', 'card', 'card.value', 'listingInfo'])->where('card_id', '=', $card_id)->where('id', '!=', $id)->where(function ($q) use ($card_id, $itemsSpecsIds, $search, $request, $filterBy) {
//                if ($card_id != null) {
//                    $q->where('card_id', $card_id);
//                }
                if ($request->has('sport') && $request->input('sport') != null) {
                    $q->orWhereHas('card', function ($qq) use ($request) {
                        $qq->where('sport', $request->input('sport'));
                    });
                }
                if ($search != null) {
                    if (count($itemsSpecsIds) > 0) {
                        $q->whereIn('itemId', $itemsSpecsIds);
                    } else {
                        $q->where('title', 'like', '%' . $search . '%');
                    }
                }
                if ($filterBy == 'buy_it_now') {
                    $q->orWhereHas('listingInfo', function ($qq) {
                        $qq->where('listingType', '!=', 'Auction');
                    });
                }
            });
//            if ($filterBy != null) {
//                if ($filterBy == 'ending_soon') {
//                    $date_one = Carbon::now()->addDay();
//                    $date_one->setTimezone('UTC');
//                    // $date_two = Carbon::now()->setTimezone('UTC');
//                    $items = $items->where("listing_ending_at", ">", $date_one); //->where("listing_ending_at", "<", $date_one);
//                }
//            }
            $items = $items->where('status', 0)->orderBy('listing_ending_at', 'asc')->get();
            if ($filterBy != null && $filterBy == 'price_low_to_high') {
                $items = $items->sortBy(function($query) {
                    return $query->sellingStatus->currentPrice;
                });
            }
            $items = $items->skip($skip)->take($take)->map(function($item, $key) use($card_id) {
                $galleryURL = $item->galleryURL;
                if ($item->pictureURLLarge != null) {
                    $galleryURL = $item->pictureURLLarge;
                } else if ($item->pictureURLSuperSize != null) {
                    $galleryURL = $item->pictureURLSuperSize;
                } else if ($galleryURL == null) {
                    $galleryURL = $this->defaultListingImage;
                }
                $listingTypeVal = ($item->listingInfo ? $item->listingInfo->listingType : '');

                $sx_data = CardSales::getSxAndLastSx($card_id);
                $sx = $sx_data['sx'];
                $lastSx = $sx_data['lastSx'];
                $price_diff = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'galleryURL' => $galleryURL,
                    'price' => ($item->sellingStatus ? $item->sellingStatus->price : 0),
                    'itemId' => $item->itemId,
                    'viewItemURL' => $item->viewItemURL,
                    'listing_ending_at' => $item->listing_ending_at,
                    'showBuyNow' => ($listingTypeVal != 'Auction') ? true : false,
                    'data' => $item,
                    'sx_value' => number_format((float) $sx, 2, '.', ''),
                    'sx_icon' => (($sx - $lastSx) >= 0) ? 'up' : 'down',
                    'price_diff' => $price_diff,
                ];
            });
            return response()->json(['status' => 200, 'data' => $items, 'next' => ($page + 1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getAdanceSearchData(Request $request) {
        try {
            $last_entry = AdvanceSearchOptions::latest()->first();
            $previous_date = date('Y-m-d H:i:s', strtotime('-30 days'));
            if (!empty($last_entry) && $last_entry['updated_at'] < $previous_date) {
                $this->updateAdanceSearchOptions($request);
            }
            if (empty($last_entry)) {
                $this->updateAdanceSearchOptions($request);
            }

            $advanceSearchData = [
                'sports' => [],
                'year' => [],
                'team' => [],
                'season' => [],
                'cardmanufacturer' => [],
                'product' => [],
                'series' => [],
                'grade' => []
            ];
            AdvanceSearchOptions::where('status', 1)->get()->map(function($item, $key) use(&$advanceSearchData) {
                $advanceSearchData[$item['type']][] = $item['keyword'];
            });
            return response()->json(['status' => 200, 'data' => $advanceSearchData], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function updateAdanceSearchOptions(Request $request) {
        try {
            $advanceSearchData = [];
            $includeParams = ['Sport', 'Year', 'Card Manufacturer', 'Series', 'Grade', 'Product', 'Team', 'Season'];
            $specifics = EbayItemSpecific::whereIn('name', $includeParams)->select('name')->groupBy('name')->get();
            foreach ($specifics->toArray() as $key => $value) {
                $index = str_replace(array('/', ' '), array('', ''), strtolower($value['name']));
                $data = [];
                $c = EbayItemSpecific::where('name', $value['name'])
                                ->where('value', '!=', 'N/A')
                                ->where('value', '!=', '.')
                                ->where('value', '!=', 'na')
                                ->where('value', '!=', 'Unknown')
                                ->where('value', '!=', 'See Description')
                                ->where('value', '!=', 'See Photo')
                                ->select('value')->groupBy('value')->get()->map(function($item, $key) use(&$data) {
                    $data[] = $item['value'];
                });
                foreach ($data as $keyword) {
                    $count = AdvanceSearchOptions::where(['type' => $index, 'keyword' => $keyword])->count();
                    if ($count == 0) {
                        AdvanceSearchOptions::create(['type' => $index, 'keyword' => $keyword]);
                    }
                }
            }
            return true;
        } catch (\Exception $e) {
            echo response()->json($e->getMessage(), 500);
        }
    }

    public function getAdanceSearchDataForAdmin(Request $request) {

        try {
            $advanceSearchData = AdvanceSearchOptions::where(function($q) use ($request) {
                        if ($request->input('filter') != 'all') {
                            $q->where('type', $request->input('filter'));
                        }
                    })->get();
            return response()->json(['status' => 200, 'data' => $advanceSearchData], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function updateAdanceSearchOptionStatus(Request $request) {

        try {
            if (AdvanceSearchOptions::whereIn('id', $request->input('ids'))->update(['status' => $request->input('status')])) {
                return response()->json(['status' => 200, 'message' => 'Status updated'], 200);
            } else {
                return response()->json(['status' => 400, 'message' => 'Status Not updated'], 400);
            }
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getItemsListForCard(int $id, Request $request) {
        try {
            $page = $request->input('page', 1);
            $take = $request->input('take', 30);
            $skip = $take * $page;
            $skip = $skip - $take;
            $items = EbayItems::with(['sellingStatus', 'card', 'card.value', 'listingInfo'])->where('card_id', $id)->where('status', 0)->orderBy('updated_at', 'desc')->get();
            $items = $items->skip($skip)->take($take)->map(function($item, $key) {
                $galleryURL = $item->galleryURL;
                if ($item->pictureURLLarge != null) {
                    $galleryURL = $item->pictureURLLarge;
                } else if ($item->pictureURLSuperSize != null) {
                    $galleryURL = $item->pictureURLSuperSize;
                } else if ($galleryURL == null) {
                    $galleryURL = $this->defaultListingImage;
                }
                $listingTypeVal = ($item->listingInfo ? $item->listingInfo->listingType : '');
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'galleryURL' => $galleryURL,
                    'price' => ($item->sellingStatus ? $item->sellingStatus->price : 0),
                    'itemId' => $item->itemId,
                    'viewItemURL' => $item->viewItemURL,
                    'listing_ending_at' => $item->listing_ending_at,
                    'showBuyNow' => ($listingTypeVal != 'Auction') ? true : false,
                    'data' => $item,
                ];
            });

            return response()->json(['status' => 200, 'data' => $items, 'next' => ($page + 1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getRecentList(Request $request) {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $search = $request->input('search', null);
        $filterBy = $request->input('filterBy', null);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {
            // $itemsSpecsIds = EbayItemSpecific::where('value', 'like', '%' . $search . '%')->groupBy('itemId')->pluck('itemId');
            $itemsSpecsIds = [];
            $items = EbayItems::with(['sellingStatus', 'card', 'card.value', 'listingInfo'])->where(function ($q) use ($itemsSpecsIds, $search, $request, $filterBy) {
                if ($request->has('sport') && $request->input('sport') != null && $request->input('sport') != 'random bin') {
                    $q->orWhereHas('card', function ($qq) use ($request) {
                        $qq->where('sport', $request->input('sport'));
                    });
                }
                if ($request->input('sport') == 'random bin') {
                    $q->orWhere('is_random_bin', 1);
                }
                if ($search != null) {
                    if (count($itemsSpecsIds) > 0) {
                        $q->whereIn('itemId', $itemsSpecsIds);
                    } else {
                        $q->where('title', 'like', '%' . $search . '%');
                    }
                }
                if ($filterBy == 'buy_it_now') {
                    $q->orWhereHas('listingInfo', function ($qq) {
                        $qq->where('listingType', '!=', 'Auction');
                    });
                }
            });
            if ($request->input('sport') != 'random bin') {
                $items->whereHas('card', function($q) {
                    $q->where('active', 1);
                });
            }
            if ($filterBy == 'ending_soon') {
                $date_one = Carbon::now()->addDay();
                $date_one->setTimezone('UTC');
                $date_two = Carbon::now()->setTimezone('UTC');
                $items = $items->where("listing_ending_at", ">", $date_one); //->where("listing_ending_at", "<", $date_one);
            }
            $items = $items->where('status', 0)->orderBy('listing_ending_at', 'asc')->get();
            if ($filterBy == 'price_low_to_high') {
                $items = $items->sortBy(function($query) {
                    return ($query->sellingStatus ? $query->sellingStatus->currentPrice : null);
                });
            }


            // $items = EbayItems::with('sellingStatus','card')->where(function($q) use ($request){
            // })->orderBy('updated_at', 'desc')->get();
            $items = $items->skip($skip)->take($take)->map(function($item, $key) {
                $galleryURL = $item->galleryURL;
                if ($item->pictureURLLarge != null) {
                    $galleryURL = $item->pictureURLLarge;
                } else if ($item->pictureURLSuperSize != null) {
                    $galleryURL = $item->pictureURLSuperSize;
                } else if ($galleryURL == null) {
                    $galleryURL = $this->defaultListingImage;
                }
                $listingTypeVal = ($item->listingInfo ? $item->listingInfo->listingType : '');

                //Getting sx and price diff
//                $sx = CardSales::where('card_id', $item->card_id)->orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
//                $sx_count = count($sx);
//                $sx = ($sx_count > 0) ? array_sum($sx->toArray()) / $sx_count : 0;
//                
//                $lastSx = CardSales::where('card_id', $item->card_id)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
//                $count = count($lastSx);
//                $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;

                $sx_data = CardSales::getSxAndLastSx($item->card_id);
                $sx = $sx_data['sx'];
                $lastSx = $sx_data['lastSx'];

                $price_diff = (float) str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'galleryURL' => $galleryURL,
                    'price' => ($item->sellingStatus ? $item->sellingStatus->price : null),
                    'itemId' => $item->itemId,
                    'viewItemURL' => $item->viewItemURL,
                    'listing_ending_at' => $item->listing_ending_at,
                    'showBuyNow' => ($listingTypeVal != 'Auction') ? true : false,
                    'data' => $item,
                    'sx_value' => number_format((float) $sx, 2, '.', ''),
                    'sx_icon' => (($sx - $lastSx) >= 0) ? 'up' : 'down',
                    'price_diff' => $price_diff,
                ];
            });
            return response()->json(['status' => 200, 'data' => $items, 'next' => ($page + 1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getEndingSoonList(Request $request) {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {
            $date_one = Carbon::now()->addDay();
            $date_one->setTimezone('UTC');
            // $date_two = Carbon::now()->setTimezone('UTC');
            $items = EbayItems::with(['sellingStatus', 'card', 'card.value', 'listingInfo'])->where("listing_ending_at", ">", $date_one); //->where("listing_ending_at", "<", $date_one)
            $items = $items->where('status', 0)->orderBy('listing_ending_at', 'asc')->get();
            $items = $items->skip($skip)->take($take)->map(function($item, $key) {
                $galleryURL = $item->galleryURL;
                if ($item->pictureURLLarge != null) {
                    $galleryURL = $item->pictureURLLarge;
                } else if ($item->pictureURLSuperSize != null) {
                    $galleryURL = $item->pictureURLSuperSize;
                } else if ($galleryURL == null) {
                    $galleryURL = $this->defaultListingImage;
                }
                $listingTypeVal = ($item->listingInfo ? $item->listingInfo->listingType : '');

//                $sx = CardSales::where('card_id', $item->card_id)->orderBy('timestamp', 'DESC')->limit(3)->pluck('cost');
//                $sx_count = count($sx);
//                $sx = ($sx_count > 0) ? array_sum($sx->toArray()) / $sx_count : 0;
//                $lastSx = CardSales::where('card_id', $item->card_id)->orderBy('timestamp', 'DESC')->skip(1)->limit(3)->pluck('cost');
//                $count = count($lastSx);
//                $lastSx = ($count > 0) ? array_sum($lastSx->toArray()) / $count : 0;
                $sx_data = CardSales::getSxAndLastSx($item->card_id);
                $sx = $sx_data['sx'];
                $lastSx = $sx_data['lastSx'];

                $price_diff = (float) str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'galleryURL' => $galleryURL,
                    'price' => ($item->sellingStatus ? $item->sellingStatus->price : 0),
                    'itemId' => $item->itemId,
                    'viewItemURL' => $item->viewItemURL,
                    'listing_ending_at' => $item->listing_ending_at,
                    'showBuyNow' => ($listingTypeVal != 'Auction') ? true : false,
                    'data' => $item,
                    'sx_value' => number_format((float) $sx, 2, '.', ''),
                    'sx_icon' => (($sx - $lastSx) >= 0) ? 'up' : 'down',
                    'price_diff' => $price_diff,
                ];
            });

            $AppSettings = AppSettings::first();
            $order = ['basketball', 'soccer', 'baseball', 'football', 'pokemon'];
            if ($AppSettings) {
                $order = $AppSettings->live_listings_order;
            }

            return response()->json(['status' => 200, 'data' => $items, 'next' => ($page + 1), 'order' => $order], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getEndedList(Request $request) {
//        dump(Carbon::now()->toDateTimeString());
//        dump($request->all());
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
//        $search = $request->input('search', null);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {
            $items = EbayItems::with(['sellingStatus', 'card', 'listingInfo'])->where('listing_ending_at', '<', Carbon::now()->toDateTimeString())->where('status', 0)->orderBy('updated_at', 'desc')->get();
            $items = $items->skip($skip)->take($take);
            $data = [];
            foreach ($items as $key => $item) {
                $galleryURL = $item->galleryURL;
                if ($item->pictureURLLarge != null) {
                    $galleryURL = $item->pictureURLLarge;
                } else if ($item->pictureURLSuperSize != null) {
                    $galleryURL = $item->pictureURLSuperSize;
                } else if ($galleryURL == null) {
                    $galleryURL = $this->defaultListingImage;
                }
                $data[] = [
                    'id' => $item->id,
                    'title' => $item->title,
                    'galleryURL' => $galleryURL,
                    'price' => ($item->sellingStatus ? $item->sellingStatus->price : 0),
                    'itemId' => $item->itemId,
                    'viewItemURL' => $item->viewItemURL,
                    'listing_ending_at' => $item->listing_ending_at,
                    'status' => $item->status
                ];
            }
            $sportsList = Card::select('sport')->distinct()->pluck('sport');
            return response()->json(['status' => 200, 'data' => $data, 'next' => ($page + 1), 'sportsList' => $sportsList], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function sampleMyListing(Request $request) {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {
            $items = EbayItems::with(['sellingStatus', 'card', 'card.value', 'listingInfo'])->whereNotNull('pictureURLSuperSize')->where('status', 0)->orderBy('updated_at', 'desc')->get();
            $items = $items->skip($skip)->take($take)->map(function($item, $key) {
                $galleryURL = $item->galleryURL;
                if ($item->pictureURLLarge != null) {
                    $galleryURL = $item->pictureURLLarge;
                } else if ($item->pictureURLSuperSize != null) {
                    $galleryURL = $item->pictureURLSuperSize;
                } else if ($galleryURL == null) {
                    $galleryURL = $this->defaultListingImage;
                }
                $listingTypeVal = ($item->listingInfo ? $item->listingInfo->listingType : '');
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'galleryURL' => $galleryURL,
                    'price' => ($item->sellingStatus ? $item->sellingStatus->price : 0),
                    'itemId' => $item->itemId,
                    'viewItemURL' => $item->viewItemURL,
                    'listing_ending_at' => $item->listing_ending_at,
                    'showBuyNow' => ($listingTypeVal != 'Auction') ? true : false,
                    'data' => $item,
                ];
            });
            return response()->json(['status' => 200, 'data' => $items, 'next' => ($page + 1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getSmartKeyword(Request $request) {
        try {
            $data = EbayItems::with('card')->where('title', 'like', '%' . $request->input('keyword') . '%')->distinct('title')->get()->take(10);
            $items = [];
            foreach ($data as $key => $value) {
                $name = explode(' ', $value['card']['player']);
                $items[] = [
                    'title' => $value['title'],
                    'player' => $name[0],
                ];
            }
            return response()->json(['status' => 200, 'data' => $items], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function addSeeProblem(Request $request) {
        try {
            $user_id = auth()->user()->id;
            $validator = Validator::make($request->all(), [
                        'id' => 'required',
                        'message' => 'required',
            ]);
            if ($validator->fails()) {
                return response()->json($validator, 500);
            }
            SeeProblem::create(['user_id' => $user_id, 'ebay_item_id' => $request->input('id'), 'message' => $request->input('message')]);
            return response()->json(['status' => 200, 'data' => ['message' => 'Added successfully.']], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getSeeProblemForAdmin(Request $request) {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $search = $request->input('search', null);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {
            $sp = SeeProblem::with(['user', 'ebay'])->orderBy('updated_at', 'desc')->get();
            $sp = $sp->skip($skip)->take($take);
            $next = 0;
            if ($sp->count() > 0) {
                $next = ($page + 1);
            }
            return response()->json(['status' => 200, 'data' => $sp, 'next' => $next], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function seeProblemReject($id) {
        try {
            (EbayItems::where('id', $id)->first())->update(['status' => 0]);
            (SeeProblem::where(['ebay_item_id' => $id])->first())->delete();
            return response()->json(['status' => 200, 'message' => 'Listing status updated.'], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

}
