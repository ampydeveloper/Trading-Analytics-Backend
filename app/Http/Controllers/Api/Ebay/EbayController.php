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
use App\Models\Ebay\EbayItemSpecific;
use App\Models\SeeProblem;
use Carbon\Carbon;
use Validator;
use App\Models\Ebay\EbayItemCategories;

class EbayController extends Controller {

    public function getItemsListForAdmin(Request $request) {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $search = $request->input('search', null);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {
            $itemsSpecsIds = EbayItemSpecific::where('value', 'like', '%' . $search . '%')->groupBy('itemId')->pluck('itemId');
            $items = EbayItems::with(['sellingStatus', 'card', 'listingInfo'])->where(function ($q) use ($itemsSpecsIds, $search) {
                        if ($search != null) {
                            if (count($itemsSpecsIds) > 0) {
                                $q->whereIn('itemId', $itemsSpecsIds);
                            } else {
                                $q->where('title', 'like', '%' . $search . '%');
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
                    $galleryURL = env('APP_URL') . '/img/default-image.jpg';
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
//                    $galleryURL = env('APP_URL') . '/img/default-image.jpg';
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
                    $galleryURL = env('APP_URL') . '/img/default-image.jpg';
                }
                $data[] = [
                    'id' => $item->id,
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
                    $galleryURL = env('APP_URL') . '/img/default-image.jpg';
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
            return response()->json(['status' => 200, 'data' => $data, 'next' => ($page + 1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function changeEbayStatusAdmin(Request $request) {
        $validator = Validator::make($request->all(), [
                    'id' => 'required',
                    'status' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        try {
            if (EbayItems::where('id', $request->input('id'))->update(['status' => $request->input('status')])) {
                return response()->json(['status' => 200, 'message' => 'Status Chnaged successfully'], 200);
            }
            return response()->json(['status' => 400, 'message' => 'Status change failed'], 400);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function saveSoldPriceAdmin(Request $request) {
//        $validator = Validator::make($request->all(), [
//            'id' => 'required',
//            'status' => 'required',
//        ]);
//        if ($validator->fails()) {
//            return response()->json($validator->errors(), 422);
//        }

        try {
            if (EbayItems::where('id', $request->input('id'))->update(['sold_price' => $request->input('sold_price')])) {
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

            if ($searchCard != null) {
                $cards = Card::where('id', $searchCard)->with('details')->get();
            } else {
                $cards = Card::whereIn('id', $items['cards'])->with('details')->get();
            }
            foreach ($cards as $ind => $card) {
                $cardValues = CardValues::where('card_id', $card->id)->orderBy('date', 'DESC')->limit(2)->get('avg_value');
                $sx = 0;
                $sx_icon = 'up';
                if (count($cardValues) == 2) {
                    foreach ($cardValues as $cv) {
                        if ($sx == 0) {
                            $sx = $cv['avg_value'];
                        } else {
                            $sx = $sx - $cv['avg_value'];
                        }
                    }
                }
                if ($sx < 0) {
                    $sx = abs($sx);
                    $sx_icon = 'down';
                }
                $cards[$ind]['sx_value'] = number_format((float) $sx, 2, '.', '');
                $cards[$ind]['sx_icon'] = $sx_icon;
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
        $cards = CardDetails::where(function ($q) use ($filter) {
                    if ($filter['year'] != null) {
                        $q->where('year', $filter['year']);
                    }
                    if ($filter['number'] != null) {
                        $q->where('number', '#' . $filter['number']);
                    }
                    if ($filter['card'] != null) {
                        $q->where('manufacturer', $filter['card']);
                    }
                    if ($filter['series'] != null) {
                        $q->where('series', $filter['series']);
                    }
                    if ($filter['grade'] != null) {
                        $q->where('grade', $filter['grade']);
                    }
                    if ($filter['rookie'] != null && $filter['rookie'] == '1') {
                        $q->where('rookie', 1);
                    }
                    if ($filter['season'] != null) {
                        $q->where('season', $filter['season']);
                    }
                })->pluck('card_id');

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
                $galleryURL = env('APP_URL') . '/img/default-image.jpg';
            }
            return [
                'id' => $item->id,
                'title' => $item->title,
                'galleryURL' => $galleryURL,
                'price' => $item->sellingStatus->price,
                'itemId' => $item->itemId,
                'viewItemURL' => $item->viewItemURL,
                'listing_ending_at' => $item->listing_ending_at,
                'showBuyNow' => ($item->listingInfo->listingType != 'Auction') ? true : false,
                'data' => $item,
            ];
        });
        return ['data' => $ebayitems, 'next' => ($page + 1), 'cards' => $cardsIds];
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
        if ($search != null && $search != '') {
            $cardsId = Card::where(function($q) use ($search) {
                        $search = explode(' ', $search);
                        foreach ($search as $key => $keyword) {
                            $q->orWhere('title', 'like', '%' . $keyword . '%');
                        }
                    })->pluck('id');
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
                $galleryURL = env('APP_URL') . '/img/default-image.jpg';
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
        return ['data' => $items, 'next' => ($page + 1), 'cards' => $cardsIds];
    }

    public function createEbayItemForAdmin(Request $request) {
        try {
            \DB::beginTransaction();
            $data = $request->all();
//            dump($data);
            $data['card_id'] = ($data['card_id'] == "null" ? null : $data['card_id']);
            $item = EbayItems::where('card_id', $data['card_id'])->where('itemId', $data['itemId'])->first();
//            dump($item);
            if ($item == null) {
//                dd('in');
                $cat_id = EbayItemCategories::where('categoryId', $data['details']['PrimaryCategoryID'])->first()['id'];
//                dd($cat_id);
                if(isset($data['details']['PictureURL'])){
                    if(is_array($data['details']['PictureURL']) && count($data['details']['PictureURL']) > 0){
                     $pictureURLLarge =   $data['details']['PictureURL'][0];
                     $pictureURLSuperSize =   $data['details']['PictureURL'][count($data['details']['PictureURL']) - 1];
                    }else{
                      $pictureURLLarge =  $data['details']['PictureURL'];
                      $pictureURLSuperSize =  $data['details']['PictureURL'];
                    }
                }else{
                    $pictureURLLarge = null;
                            $pictureURLSuperSize = null;
                }
                
                EbayItems::create([
                    'card_id' => $data['card_id'],
                    'itemId' => $data['itemId'],
                    'title' => $data['title'],
                    'category_id' => $cat_id,
                    'globalId' => $data['details']['Site'] ? 'EBAY-' . $data['details']['Site'] : null,
                    'galleryURL' => $data['details']['GalleryURL'] ? $data['details']['GalleryURL'] : null,
                    'viewItemURL' => $data['details']['ViewItemURLForNaturalSearch'] ? $data['details']['ViewItemURLForNaturalSearch'] : null,
                    'autoPay' => $data['details']['AutoPay'] ? $data['details']['AutoPay'] : null,
                    'postalCode' => $data['details']['PostalCode'] ? $data['details']['PostalCode'] : null,
                    'location' => $data['details']['Location'] ? $data['details']['Location'] : null,
                    'country' => $data['details']['Country'] ? $data['details']['Country'] : null,
                    'returnsAccepted' => $data['details']['ReturnPolicy']['ReturnsAccepted'] == 'ReturnsNotAccepted' ? false : true,
                    'condition_id' => $data['details']['ConditionID'] ? $data['details']['ConditionID'] : '',
                    'pictureURLLarge' => $pictureURLLarge,
                    'pictureURLSuperSize' => $pictureURLSuperSize,
                    'listing_ending_at' => $data['details']['EndTime'] ? $data['details']['EndTime'] : null,
                    'is_random_bin' => array_key_exists('random_bin', $data) ? (bool) $data['random_bin'] : 0
                ]);
                EbayItemSellerInfo::create([
                    'itemId' => $data['itemId'],
                    'sellerUserName' => $data['seller_name'],
                    'positiveFeedbackPercent' => $data['positiveFeedbackPercent'],
                    'seller_contact_link' => $data['seller_contact_link'],
                    'seller_store_link' => $data['seller_store_link']
                ]);
                foreach($data['specifics'] as $speci){
                    if ($speci['Value'] != "N/A") {
                        EbayItemSpecific::create([
                            'itemId' => $data['itemId'],
                            'name' => $speci['Name'],
                            'value' => is_array($speci['Value']) ? implode(',', $speci['Value']) : $speci['Value']
                        ]);
                    }
                }
                \DB::commit();

                return response()->json(['status' => 200, 'data' => ['message' => 'Added successfully.']], 200);
            } else {
                \DB::rollBack();
                return response()->json(['status' => 200, 'data' => ['message' => 'Updated successfully.']], 200);
            }
        } catch (\Exception $e) {
//            dd($e);
            \Log::error($e);
            \DB::rollBack();
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
                $galleryURL = env('APP_URL') . '/img/default-image.jpg';
            }
            return [
                'id' => $item->id,
                'title' => $item->title,
                'galleryURL' => $galleryURL,
                'price' => $item->sellingStatus->price,
                'itemId' => $item->itemId,
                'viewItemURL' => $item->viewItemURL,
                'listing_ending_at' => $item->listing_ending_at,
                'showBuyNow' => ($item->listingInfo->listingType != 'Auction') ? true : false,
                'data' => $item,
            ];
        });
        return ['data' => $items, 'next' => ($page + 1), 'cards' => $cardsIds];
    }

    public function getItemsDetails(Request $request) {
        try {
            $items = EbayItems::where('id', $request->input('id'))
                    ->with(['category', 'card', 'card.value', 'details', 'playerDetails', 'condition', 'sellerInfo', 'listingInfo', 'sellingStatus', 'shippingInfo', 'specifications'])
                    ->first();
            if ($items) {
                return response()->json(['status' => 200, 'data' => $items], 200);
            } else {
                return response()->json(['status' => 404], 200);
            }
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
            $items = EbayItems::with(['sellingStatus', 'card', 'card.value', 'listingInfo'])->where('id', '!=', $id)->where(function ($q) use ($card_id, $itemsSpecsIds, $search, $request, $filterBy) {
                if ($card_id != null) {
                    $q->where('card_id', $card_id);
                }
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
            if ($filterBy != null) {
                if ($filterBy == 'ending_soon') {
                    $date_one = Carbon::now()->addDay();
                    $date_one->setTimezone('UTC');
                    // $date_two = Carbon::now()->setTimezone('UTC');
                    $items = $items->where("listing_ending_at", ">", $date_one); //->where("listing_ending_at", "<", $date_one);
                }
            }
            $items = $items->where('status', 0)->orderBy('listing_ending_at', 'asc')->get();
            if ($filterBy != null && $filterBy == 'price_low_to_high') {
                $items = $items->sortBy(function($query) {
                    return $query->sellingStatus->currentPrice;
                });
            }
            $items = $items->skip($skip)->take($take)->map(function($item, $key) {
                $galleryURL = $item->galleryURL;
                if ($item->pictureURLLarge != null) {
                    $galleryURL = $item->pictureURLLarge;
                } else if ($item->pictureURLSuperSize != null) {
                    $galleryURL = $item->pictureURLSuperSize;
                } else if ($galleryURL == null) {
                    $galleryURL = env('APP_URL') . '/img/default-image.jpg';
                }
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'galleryURL' => $galleryURL,
                    'price' => $item->sellingStatus->price,
                    'itemId' => $item->itemId,
                    'viewItemURL' => $item->viewItemURL,
                    'listing_ending_at' => $item->listing_ending_at,
                    'showBuyNow' => ($item->listingInfo->listingType != 'Auction') ? true : false,
                    'data' => $item,
                ];
            });
            return response()->json(['status' => 200, 'data' => $items, 'next' => ($page + 1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getAdanceSearchData(Request $request) {
        try {
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
            echo response()->json('Done', 200);
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
                    $galleryURL = env('APP_URL') . '/img/default-image.jpg';
                }
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'galleryURL' => $galleryURL,
                    'price' => $item->sellingStatus->price,
                    'itemId' => $item->itemId,
                    'viewItemURL' => $item->viewItemURL,
                    'listing_ending_at' => $item->listing_ending_at,
                    'showBuyNow' => ($item->listingInfo->listingType != 'Auction') ? true : false,
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
                    })->whereHas('card', function($q) {
                $q->where('active', 1);
            });
            if ($filterBy == 'ending_soon') {
                $date_one = Carbon::now()->addDay();
                $date_one->setTimezone('UTC');
                // $date_two = Carbon::now()->setTimezone('UTC');
                //$items = $items->where("listing_ending_at", ">", $date_one);//->where("listing_ending_at", "<", $date_one);
            }
            $items = $items->where('status', 0)->orderBy('listing_ending_at', 'asc')->get();
            if ($filterBy == 'price_low_to_high') {
                $items = $items->sortBy(function($query) {
                    return $query->sellingStatus->currentPrice;
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
                    $galleryURL = env('APP_URL') . '/img/default-image.jpg';
                }
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'galleryURL' => $galleryURL,
                    'price' => $item->sellingStatus->price,
                    'itemId' => $item->itemId,
                    'viewItemURL' => $item->viewItemURL,
                    'listing_ending_at' => $item->listing_ending_at,
                    'showBuyNow' => ($item->listingInfo->listingType != 'Auction') ? true : false,
                    'data' => $item,
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
                    $galleryURL = env('APP_URL') . '/img/default-image.jpg';
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
                    'showBuyNow' => ($item->listingInfo->listingType != 'Auction') ? true : false,
                    'data' => $item,
                ];
            });
            return response()->json(['status' => 200, 'data' => $items, 'next' => ($page + 1)], 200);
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
                    $galleryURL = env('APP_URL') . '/img/default-image.jpg';
                }
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'galleryURL' => $galleryURL,
                    'price' => $item->sellingStatus->price,
                    'itemId' => $item->itemId,
                    'viewItemURL' => $item->viewItemURL,
                    'listing_ending_at' => $item->listing_ending_at,
                    'showBuyNow' => ($item->listingInfo->listingType != 'Auction') ? true : false,
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
            return response()->json(['status' => 200, 'data' => ['message' => 'Added succefully']], 200);
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

            return response()->json(['status' => 200, 'data' => $sp, 'next' => ($page + 1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

}
