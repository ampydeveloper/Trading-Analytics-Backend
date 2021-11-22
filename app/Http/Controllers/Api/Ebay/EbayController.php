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
use App\Models\SportsQueue;
use Carbon\Carbon;
use Validator;
use App\Models\Ebay\EbayItemCategories;
use App\Models\Ebay\EbayItemListingInfo;
use App\Models\Ebay\EbayItemSellingStatus;
use App\Models\AppSettings;
use App\Jobs\JobForTrender;
use App\Models\CardsSx;
use App\Models\CardsTotalSx;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Log;

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
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $search = $request->input('search', null);
        $sport = $request->input('sport', null);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {

            $itemsIdsCombine = EbayItemSpecific::where('value', 'like', '%' . $search . '%')->groupBy('itemId')->pluck('itemId');
//            if ($sport != null) {
//                $itemsCatsIds = EbayItemCategories::where('name', 'like', '%' . $sport . '%')->pluck('categoryId');
//                $itemsIdsCombine = array_merge($itemsSpecsIds->toArray(), $itemsCatsIds->toArray());
//            } else {
//                $itemsIdsCombine = $itemsSpecsIds;
//            }
            $items = EbayItems::with(['sellingStatus', 'card', 'listingInfo'])->where(function ($q) use ($itemsIdsCombine, $search, $request) {
                        if ($search != null) {
                            if (count($itemsIdsCombine) > 0) {
                                $q->whereIn('itemId', $itemsIdsCombine);
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

                        $date_one = Carbon::now();
                        $date_one->setTimezone('America/Los_Angeles');
                        $q->where("listing_ending_at", ">", $date_one);
                    })->where('sold_price', '')
                    ->orderBy('listing_ending_at', 'asc');

            $items_count = $items->count();
            $all_pages = ceil($items_count / $take);
            $items = $items->skip($skip)->take($take)->get();
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

                date_default_timezone_set("America/Los_Angeles");
                $datetime1 = new \DateTime($item->listing_ending_at);
                $datetime2 = new \DateTime('now');
                $interval = $datetime1->diff($datetime2);
                if ($interval->invert == 1) {
                    $days = $interval->format('%d');
                    $hours = $interval->format('%h');
                    $mins = $interval->format('%i');
                    $secs = $interval->format('%s');
                    if ($days > 0) {
                        $timeleft = $days . 'd ' . $hours . 'h';
                    } else if ($hours >= 1) {
                        $timeleft = $hours . 'h ' . $mins . 'm';
                    } else if ($mins >= 1) {
                        $timeleft = $mins . 'm ' . $secs . 's';
                    } else {
                        $timeleft = $secs . 's';
                    }
                } else {
                    $timeleft = '0s';
                }
                $item_status = '';
                if (!empty($item->item_status)) {
                    if ($item->item_status == 3) {
                        $item_status = 'Manual';
                    } else if ($item->item_status == 2) {
                        $item_status = 'Rejected';
                    }
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
                    'time_left' => $timeleft,
                    'status' => $item->status,
                    'item_status' => $item_status
                ];
            }
            $sportsList = Card::select('sport')->distinct()->pluck('sport');
            return response()->json(['status' => 200, 'data' => $data, 'all_pages' => $all_pages, 'next' => ($page + 1), 'sportsList' => $sportsList], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getItemsOtherListForAdmin(Request $request) {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $search = $request->input('search', null);
        $sport = $request->input('sport', null);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {

            $itemsIdsCombine = EbayItemSpecific::where('value', 'like', '%' . $search . '%')->groupBy('itemId')->pluck('itemId');
            $items = EbayItems::with(['sellingStatus', 'card', 'listingInfo'])->where(function ($q) use ($itemsIdsCombine, $search, $request) {
                        if ($search != null) {
                            if (count($itemsIdsCombine) > 0) {
                                $q->whereIn('itemId', $itemsIdsCombine);
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

                        $date_one = Carbon::now();
                        $date_one->setTimezone('America/Los_Angeles');
                        $q->where("listing_ending_at", "<", $date_one);
                        $q->where('sold_price', '');
//                            ->where('sold_price', '==', null)
                    })
                    ->where(function($query) {
                        $query->orWhere('item_status', 2)->orWhere('item_status', 3);
                    })
                    ->where('manual_counter', '!=', null)
                    ->orderBy('listing_ending_at', 'asc');

            $items_count = $items->count();
            $all_pages = ceil($items_count / $take);
            $items = $items->skip($skip)->take($take)->get();
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

                date_default_timezone_set("America/Los_Angeles");
                $datetime1 = new \DateTime($item->listing_ending_at);
                $datetime2 = new \DateTime('now');
                $interval = $datetime1->diff($datetime2);
                if ($interval->invert == 1) {
                    $days = $interval->format('%d');
                    $hours = $interval->format('%h');
                    $mins = $interval->format('%i');
                    $secs = $interval->format('%s');
                    if ($days > 0) {
                        $timeleft = $days . 'd ' . $hours . 'h';
                    } else if ($hours >= 1) {
                        $timeleft = $hours . 'h ' . $mins . 'm';
                    } else if ($mins >= 1) {
                        $timeleft = $mins . 'm ' . $secs . 's';
                    } else {
                        $timeleft = $secs . 's';
                    }
                } else {
                    $timeleft = '0s';
                }
                $item_status = 'Queue';
                if (!empty($item->item_status)) {
                    if ($item->item_status == 3) {
                        $item_status = 'Manual';
                    } else if ($item->item_status == 2) {
                        $item_status = 'Rejected';
                    }
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
                    'time_left' => $timeleft,
                    'status' => $item->status,
                    'item_status' => $item_status
                ];
            }
            $sportsList = Card::select('sport')->distinct()->pluck('sport');
            return response()->json(['status' => 200, 'data' => $data, 'all_pages' => $all_pages, 'next' => ($page + 1), 'sportsList' => $sportsList], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getItemsListForAdminForSport(Request $request) {
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
        $sport = $request->input('sport', null);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {
            $itemsSpecsIds = EbayItemSpecific::where('value', 'like', '%' . $search . '%')->groupBy('itemId')->pluck('itemId');
            if ($sport != null) {
                $itemsCatsIds = EbayItemCategories::where('name', 'like', '%' . $sport . '%')->pluck('categoryId');
                $itemsIdsCombine = array_merge($itemsSpecsIds->toArray(), $itemsCatsIds->toArray());
            } else {
                $itemsIdsCombine = $itemsSpecsIds;
            }
            $items = EbayItems::where('sold_price', '>', 0)->with(['sellingStatus', 'card', 'listingInfo', 'cardSale', 'cardSale.saleUser'])->where(function ($q) use ($itemsIdsCombine, $search, $request) {
                        if ($search != null) {
                            if (count($itemsIdsCombine) > 0) {
                                $q->whereIn('itemId', $itemsIdsCombine);
                            } else {
                                $q->where('title', 'like', '%' . $search . '%');
                                $q->orWhere('card_id', $search);
                                $q->orWhere('itemId', $search);
                            }
                        }
                        if ($request->input('sport') == 'random_bin') {
                            $q->orWhere('is_random_bin', 1);
                        }
                    })->orderBy('updated_at', 'desc');
            $items_count = $items->count();
            $all_pages = ceil($items_count / $take);
            $items = $items->skip($skip)->take($take)->get();
//dd($items->toArray());
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
                    'sold_price' => $item->sold_price,
                    'card_sales' => $item->cardSale
                ];
            }
            $sportsList = AppSettings::select('sports')->first();
//            json_decode($sportsList);
            return response()->json(['status' => 200, 'data' => $data, 'all_pages' => $all_pages, 'next' => ($page + 1), 'sportsList' => $sportsList->sports], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getEbayListSoldSorting(Request $request) {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $search = $request->input('search', null);
        $sport = $request->input('sport', null);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {
            $itemsSpecsIds = EbayItemSpecific::where('value', 'like', '%' . $search . '%')->groupBy('itemId')->pluck('itemId');
            if ($sport != null) {
                $itemsCatsIds = EbayItemCategories::where('name', 'like', '%' . $sport . '%')->pluck('categoryId');
                $itemsIdsCombine = array_merge($itemsSpecsIds->toArray(), $itemsCatsIds->toArray());
            } else {
                $itemsIdsCombine = $itemsSpecsIds;
            }

            $items = EbayItems::where('sold_price', '>', 0)->with(['sellingStatus', 'card', 'listingInfo', 'cardSale', 'cardSale.saleUser'])->where(function ($q) use ($itemsIdsCombine, $search, $request) {
                if ($search != null) {
                    if (count($itemsIdsCombine) > 0) {
                        $q->whereIn('itemId', $itemsIdsCombine);
                    } else {
                        $q->where('title', 'like', '%' . $search . '%');
                        $q->orWhere('card_id', $search);
                        $q->orWhere('itemId', $search);
                    }
                }
                if ($request->input('sport') == 'random_bin') {
                    $q->orWhere('is_random_bin', 1);
                }
            });

            $items_count = $items->count();
            $all_pages = ceil($items_count / $take);
            
            if (isset($request->column_name) && $request->column_name == "sold_price") {
                $items = $items->orderBy($request->column_name, $request->sort_type)->skip($skip)->take($take)->get();
            } else if (isset($request->column_name) && $request->column_name == "listing_ending_at") {
                $items = $items->orderBy($request->column_name, $request->sort_type)->skip($skip)->take($take)->get();
            } else {
                $items = $items->orderBy('updated_at', 'desc')->skip($skip)->take($take)->get();
            }

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
                    'sold_price' => $item->sold_price,
                    'card_sales' => $item->cardSale
                ];
            }
            if (isset($request->column_name) && $request->column_name == "price") {
                if ($request->sort_type == "ASC") {
                    usort($data, function ($item1, $item2) {
                        return $item1['price'] <=> $item2['price'];
                    });
                } else {
                    usort($data, function ($item1, $item2) {
                        return $item2['price'] <=> $item1['price'];
                    });
                }
            }

            $sportsList = AppSettings::select('sports')->first();
            return response()->json(['status' => 200, 'data' => $data, 'all_pages' => $all_pages, 'next' => ($page + 1), 'sportsList' => $sportsList->sports], 200);
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
            if ($request->input('status') != '3') {
                foreach ($idArr as $id) {
                    (EbayItems::where('id', $id)->first())->update(['status' => $request->input('status')]);
                }
            } else {
                foreach ($idArr as $id) {
                    (EbayItems::where('id', $id)->first())->delete();
                }
            }
        } else {
            $idArr = $request->input('id');
            if ($request->input('status') != '3') {
                (EbayItems::where('id', $idArr)->first())->update(['status' => $request->input('status')]);
            } else {
                (EbayItems::where('id', $idArr)->first())->delete();
            }
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

        \DB::beginTransaction();
        try {
            $idArr = $request->input('id');
            $cardData = CardSales::where('id', $idArr)->first();
            $cardId = $cardData->card_id;

            if ($cardData->source == "Ebay") {
                EbayItems::where("id", $cardData->ebay_items_id)->update(["sold_price" => NULL]);
            }

            $cardSport = Card::where('id', $cardId)->pluck('sport');
            $requestedDate = Carbon::create($cardData->timestamp)->format('Y-m-d');
            CardSales::where('id', $idArr)->delete();
            Log::info('SALE DELETE - CARD ' . $cardId . ' ++++++++++++');
            $cardSXExistingvalue = 0;
            if (CardsSx::where('card_id', $cardId)->exists()) {
                $cardSXExisting = CardsSx::where('card_id', $cardId)->orderBy('date', 'DESC')->first();
                $cardExistingLatestSaleDate = $cardSXExisting->date;
                $cardSXExistingvalue = $cardSXExisting->sx;
            }

            if (CardSales::where('card_id', $cardId)->where('timestamp', 'like', '%' . $requestedDate . '%')->exists()) {
                $updatedSx = CardSales::where('card_id', $cardId)->where('timestamp', 'like', '%' . $requestedDate . '%')->get();
                CardsSx::where('card_id', $cardId)->where('date', $requestedDate)->update(['sx' => $updatedSx->avg('cost'), 'quantity' => $updatedSx->sum('quantity')]);
                Log::info('CardsSx UPDATE DATE ' . $requestedDate . ' SX ' . $updatedSx->avg('cost') . ' quantity ' . $updatedSx->sum('quantity'));
            } else {
                CardsSx::where('card_id', $cardId)->where('date', $requestedDate)->delete();
                Log::info('CardsSx DELETE DATE ' . $requestedDate);
            }

            if (CardSales::where('timestamp', 'like', '%' . $requestedDate . '%')->get()) {
                $updatedCTSx = CardSales::where('timestamp', 'like', '%' . $requestedDate . '%')->get();
                CardsTotalSx::where('date', $requestedDate)->update(['amount' => $updatedCTSx->avg('cost'), 'quantity' => $updatedCTSx->sum('quantity')]);
                Log::info('CardsTotalSx UPDATE DATE ' . $requestedDate . ' SX ' . $updatedCTSx->avg('cost') . ' quantity ' . $updatedCTSx->sum('quantity'));
            } else {
                CardsTotalSx::where('date', $requestedDate)->delete();
                Log::info('CardsTotalSx DELETE DATE ' . $requestedDate);
            }

            if (isset($updatedSx)) {
                $updatedCostSx = $updatedSx->avg('cost');
            } else {
                $updatedCostSx = 0;
            }

            if ($requestedDate >= $cardExistingLatestSaleDate) {
                $old_total_sx_value = AppSettings::select('total_sx_value')->first();
                $changed_total_sx_value = ($old_total_sx_value->total_sx_value - $cardSXExistingvalue) + $updatedCostSx;
                AppSettings::first()->update(["total_sx_value" => $changed_total_sx_value]);
                Log::info('AppSettings total_sx_value ' . $changed_total_sx_value);
            }

            $current_card_id = $cardId;
            $days = config('constant.days');
            foreach ($days as $daykey => $day) {
                if (($requestedDate >= $day['to']) && ($requestedDate <= $day['from'])) {
                    $checkSalesCount = CardSales::where('card_id', $current_card_id)->whereBetween('timestamp', [$day['to'], $day['from']])->count();
                    $name = 'trenders_' . $daykey . '_' . strtolower($cardSport[0]);
                    $value = Cache::get($name);
                    if ($value != null && !empty($value) && count($value) > 0) {
                        if ($checkSalesCount >= 2) {
                            $flag = 0;
                            foreach ($value as $key => $val) {
                                if (isset($val['id']) && $current_card_id == $val['id']) {
                                    $sx_data = CardSales::getSxAndOldestSx($current_card_id, $day['to'], $day['from'], $day['daysForSx']);
                                    $sx = $sx_data['sx'];
                                    $lastSx = $sx_data['oldestSx'];
                                    $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';

                                    $value[$key]['price'] = number_format((float) $sx, 2, '.', '');
                                    $value[$key]['sx_value_signed'] = (float) $sx - $lastSx;
                                    $value[$key]['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                                    $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                                    $value[$key]['sx_percent_signed'] = $sx_percent;
                                    $value[$key]['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                                    $value[$key]['sx_icon'] = $sx_icon;
                                    $value[$key]['sale_qty'] = CardSales::where('card_id', $current_card_id)->sum('quantity');
                                    $flag = 1;
                                    Log::info('Cache >= 2 ID ' . $current_card_id . ' SX ' . $sx . ' LASTSX ' . $lastSx);
                                    break;
                                }
                            }
                        } else {
                            $flag = 0;
                            foreach ($value as $key => $val) {
                                if (isset($val['id']) && $current_card_id == $val['id']) {
                                    unset($value[$key]);
                                    Log::info('Cache simple UNSET');
                                    $flag = 1;
                                    break;
                                }
                            }
                        }
                        if ($flag == 1) {
                            Cache::put($name, $value);
                        }
                    }
                }
            }

            $value = Cache::get('trenders_all_cards');
            // $checkSalesCount = CardSales::where('card_id', $current_card_id)->count();
            // if ($checkSalesCount >=2) {
            if ($value != null && !empty($value) && count($value) > 0) {
                foreach ($value as $key => $val) {
                    if ($current_card_id == $val['id']) {
                        $checkSalesCount = CardSales::where('card_id', $current_card_id)->count();
                        if ($checkSalesCount >= 2) {
                            $sx_data = CardSales::getSxAndOldestSx($current_card_id);
                            $sx = $sx_data['sx'];
                            $lastSx = $sx_data['oldestSx'];
                            $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';

                            $value[$key]['price'] = number_format((float) $sx, 2, '.', '');
                            $value[$key]['sx_value_signed'] = (float) $sx - $lastSx;
                            $value[$key]['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                            $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                            $value[$key]['sx_percent_signed'] = $sx_percent;
                            $value[$key]['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                            $value[$key]['sx_icon'] = $sx_icon;
                            $value[$key]['sale_qty'] = CardSales::where('card_id', $current_card_id)->sum('quantity');
                            Log::info('T Cache ID ' . $current_card_id . ' SX ' . $sx . ' lastSX ' . $lastSx);
                            break;
                        } else {
                            unset($value[$key]);
                            Log::info('T Cache UNSET');
                            break;
                        }
                    }
                }
                Cache::put('trenders_all_cards', $value);
                Log::info('+++++++++++++');
            }
            // }
            \DB::commit();
            return response()->json(['status' => 200, 'message' => 'Sales deleted successfully.'], 200);
        } catch (\Exception $e) {
            \DB::rollback();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function saveSoldPriceAdmin(Request $request) {
        \DB::beginTransaction();
        try {
            $item_details = EbayItems::where('id', $request->input('id'))->first();
            $sold_price = str_replace(",", "", $request->input('sold_price'));
            if (($item_details)->update(['sold_price' => $sold_price, 'status' => 2])) {
                $item_type = null;
                if ($item_details->listing_info_id != null) {
                    $ebayItemListingInfo = EbayItemListingInfo::where('id', $item_details->listing_info_id)->first();
                    $item_type = $ebayItemListingInfo->listingType;
                }
                $carbonNow = Carbon::create($item_details->listing_ending_at);
                $cardSport = Card::where('id', $item_details->card_id)->pluck('sport');

                //checking if ebay item has card Id. Some ebay items are random bin. Hence no card attached to them.
                if ($item_details->card_id == null) {
                    //random bin
                    CardSales::create([
                        'timestamp' => $carbonNow->format('Y-m-d H:i:s'),
                        'quantity' => 1,
                        'cost' => $sold_price,
                        'source' => 'Ebay',
                        'type' => $item_type,
                        'ebay_items_id' => $item_details->id,
                        'user_id' => auth()->user()->id,
                    ]);
                } else if ($item_details->card_id != null) {
                    //item with card
                    CardSales::create([
                        'card_id' => $item_details->card_id,
                        'timestamp' => $carbonNow->format('Y-m-d H:i:s'),
                        'quantity' => 1,
                        'cost' => $sold_price,
                        'source' => 'Ebay',
                        'type' => $item_type,
                        'ebay_items_id' => $item_details->id,
                        'user_id' => auth()->user()->id,
                    ]);

                    $saleDateYmd = $carbonNow->format('Y-m-d');
                    Log::info('SOLD PRICE SAVE - CARD ' . $item_details->card_id . 'DATE ' . $saleDateYmd . ' ++++++++++++');
//                $cardAllTimestamps = CardSales::where('card_id', $data['card_id'])->groupBy(DB::raw('DATE(timestamp)'))->orderBy('timestamp', 'DESC')->pluck('timestamp');
//                $latestTimestamp = Carbon::create($cardAllTimestamps[0])->format('Y-m-d');
//                if($saleDateYmd >= $latestTimestamp) {
                    $cardSXExistingvalue = 0;
                    if (CardsSx::where('card_id', $item_details->card_id)->exists()) {
                        $cardSXExisting = CardsSx::where('card_id', $item_details->card_id)->orderBy('date', 'DESC')->first();
                        $cardExistingLatestSaleDate = $cardSXExisting->date;
                        $cardSXExistingvalue = $cardSXExisting->sx;
                    }
//                }

                    $cardsSxValue = CardSales::where('card_id', $item_details->card_id)->where('timestamp', 'like', '%' . $saleDateYmd . '%')->get();
                    if (CardsSx::where('card_id', $item_details->card_id)->where('date', $saleDateYmd)->exists()) {
                        CardsSx::where('card_id', $item_details->card_id)->where('date', $saleDateYmd)->update(['sx' => $cardsSxValue->avg('cost'), 'quantity' => $cardsSxValue->sum('quantity')]);

                        Log::info('CardsSx ID ' . $item_details->card_id . ' SX ' . $cardsSxValue->avg('cost') . ' quantity ' . $cardsSxValue->sum('quantity'));
                    } else {
                        CardsSx::create([
                            'card_id' => $item_details->card_id,
                            'date' => $saleDateYmd,
                            'sx' => $sold_price,
                            'quantity' => 1,
                        ]);
                        Log::info('CardsSx ID ' . $item_details->card_id . ' SX ' . $sold_price . ' quantity 1');
                    }
                    if (CardsTotalSx::where('date', $saleDateYmd)->exists()) {
                        $sxValue = CardSales::where('timestamp', 'like', '%' . $saleDateYmd . '%')->get();
                        CardsTotalSx::where('date', $saleDateYmd)->update(['amount' => $sxValue->avg('cost'), 'quantity' => $sxValue->sum('quantity')]);

                        Log::info('CardsTotalSx amount ' . $sxValue->avg('cost') . ' quantity ' . $sxValue->sum('quantity'));
                    } else {
                        CardsTotalSx::create([
                            'date' => $saleDateYmd,
                            'amount' => $sold_price,
                            'quantity' => 1,
                        ]);

                        Log::info('CardsTotalSx amount ' . $sold_price . ' quantity 1');
                    }
                    Log::info('REACH AppSettings');

                    if (isset($cardExistingLatestSaleDate)) {
                        if ($saleDateYmd >= $cardExistingLatestSaleDate) {
                            $old_total_sx_value = AppSettings::select('total_sx_value')->first();
                            $changed_total_sx_value = ($old_total_sx_value->total_sx_value - $cardSXExistingvalue) + $cardsSxValue->avg('cost');

                            AppSettings::first()->update(["total_sx_value" => $changed_total_sx_value]);
                            Log::info('AppSettings total_sx_value ' . $changed_total_sx_value);
                        }
                    }
                    $current_card_id = $item_details->card_id;

                    $days = config('constant.days');
                    foreach ($days as $daykey => $day) {
                        if (($carbonNow >= $day['to']) && ($carbonNow <= $day['from'])) {
                            $checkSalesCount = CardSales::where('card_id', $current_card_id)->whereBetween('timestamp', [$day['to'], $day['from']])->count();
                            $name = 'trenders_' . $daykey . '_' . strtolower($cardSport[0]);
                            $value = Cache::get($name);
                            if ($value != null && !empty($value) && count($value) > 0) {
                                if ($checkSalesCount >= 3) {
                                    $flag = 0;
                                    foreach ($value as $key => $val) {
                                        if (isset($val['id']) && $current_card_id == $val['id']) {
                                            $sx_data = CardSales::getSxAndOldestSx($current_card_id, $day['to'], $day['from'], $day['daysForSx']);
                                            $sx = $sx_data['sx'];
                                            $lastSx = $sx_data['oldestSx'];
                                            $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';

                                            $value[$key]['price'] = number_format((float) $sx, 2, '.', '');
                                            $value[$key]['sx_value_signed'] = (float) $sx - $lastSx;
                                            $value[$key]['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                                            $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                                            $value[$key]['sx_percent_signed'] = $sx_percent;
                                            $value[$key]['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                                            $value[$key]['sx_icon'] = $sx_icon;
                                            $value[$key]['sale_qty'] = CardSales::where('card_id', $current_card_id)->sum('quantity');

                                            Log::info('Cache >= 3 ID ' . $current_card_id . ' SX ' . $sx . ' LASTSX ' . $lastSx);
                                            $flag = 1;
                                            break;
                                        }
                                    }
                                    if ($flag == 1) {
                                        Cache::put($name, $value);
                                    }
                                } elseif ($checkSalesCount == 2) {
                                    $flag = 0;
                                    $cards = Card::where('id', $current_card_id)->with('details')->first();
                                    if (!empty($cards)) {
                                        $sx_data = CardSales::getSxAndOldestSx($cards->id, $day['to'], $day['from'], $day['daysForSx']);
                                        $sx = $sx_data['sx'];
                                        $lastSx = $sx_data['oldestSx'];
                                        $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                                        $cards['price'] = number_format((float) $sx, 2, '.', '');
                                        $cards['sx_value_signed'] = (float) $sx - $lastSx;
                                        $cards['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                                        $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                                        $cards['sx_percent_signed'] = $sx_percent;
                                        $cards['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                                        $cards['sx_icon'] = $sx_icon;
                                        $cards['sale_qty'] = CardSales::where('card_id', $cards->id)->sum('quantity');
                                        $flag = 1;
                                        Log::info('Cache == 2 ID ' . $current_card_id . ' SX ' . $sx . ' LASTSX ' . $lastSx);
                                    }
                                    if ($flag == 1) {
                                        $value[] = $cards;
                                        Cache::put($name, $value);
                                    }
                                }
                            } else if ($checkSalesCount >= 2) {
                                $cards = Card::where('id', $current_card_id)->with('details')->first();
                                if (!empty($cards)) {
                                    $sx_data = CardSales::getSxAndOldestSx($cards->id, $day['to'], $day['from'], $day['daysForSx']);
                                    $sx = $sx_data['sx'];
                                    $lastSx = $sx_data['oldestSx'];
                                    $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                                    $cards['price'] = number_format((float) $sx, 2, '.', '');
                                    $cards['sx_value_signed'] = (float) $sx - $lastSx;
                                    $cards['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                                    $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                                    $cards['sx_percent_signed'] = $sx_percent;
                                    $cards['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                                    $cards['sx_icon'] = $sx_icon;
                                    $cards['sale_qty'] = CardSales::where('card_id', $cards->id)->sum('quantity');
                                    $value[] = $cards;
                                    Cache::put($name, $value);
                                    Log::info('Cache >= 2 ID ' . $current_card_id . ' SX ' . $sx . ' LASTSX ' . $lastSx);
                                }
                            }
                        }
                    }
                    Log::info('REACH trenders_all_cards');
                    $value = Cache::get('trenders_all_cards');
                    $checkSalesCount = CardSales::where('card_id', $current_card_id)->count();

                    if ($checkSalesCount >= 2) {
                        if ($value != null && !empty($value) && count($value) > 0) {
                            foreach ($value as $key => $val) {
                                if ($current_card_id == $val['id']) {
                                    $checkSalesCount = CardSales::where('card_id', $current_card_id)->count();
                                    if ($checkSalesCount >= 2) {
                                        $sx_data = CardSales::getSxAndOldestSx($current_card_id);
                                        $sx = $sx_data['sx'];
                                        $lastSx = $sx_data['oldestSx'];
                                        $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';

                                        $value[$key]['price'] = number_format((float) $sx, 2, '.', '');
                                        $value[$key]['sx_value_signed'] = (float) $sx - $lastSx;
                                        $value[$key]['sx_value'] = str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                                        $sx_percent = ($lastSx > 0 ? (($sx - $lastSx) / $lastSx) * 100 : 0);
                                        $value[$key]['sx_percent_signed'] = $sx_percent;
                                        $value[$key]['sx_percent'] = str_replace('-', '', number_format($sx_percent, 2, '.', ''));
                                        $value[$key]['sx_icon'] = $sx_icon;
                                        $value[$key]['sale_qty'] = CardSales::where('card_id', $current_card_id)->sum('quantity');

                                        Log::info('T Cache ID ' . $current_card_id . ' SX ' . $sx . ' lastSX ' . $lastSx);
                                        break;
                                    }
                                }
                            }
                            Cache::put('trenders_all_cards', $value);
                            Log::info('+++++++++++++');
                        }
                    }
                    \DB::commit();
                    return response()->json(['status' => 200, 'message' => 'Sold price changed successfully.'], 200);
                } else {
                    \DB::commit();
                    return response()->json(['status' => 200, 'message' => 'Sold price changed successfully.'], 200);
                }
            }
            \DB::rollback();
            return response()->json(['status' => 400, 'message' => 'Status change failed'], 400);
        } catch (\Exception $e) {
            \DB::rollback();
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getItemsList(Request $request) {
        try {
            $limit = 18;
            $filter = $request->input('filter', null);
            $searchCard = $request->input('searchCard', null);
            $page = $request->input('page', 1);

            if ($filter != null && $this->checkForAdvanceSearch($filter)) {
                $items = $this->_advanceSearch($request);
            } else {
                $items = $this->_basicSearch($request);
            }

            $take = $request->input('take', 12);
            // if ($page == 1)
            //     $take = $request->input('take', 30);
            // else
            //     $take = $request->input('take', 18);

            $skip = $take * $page;
            $skip = $skip - $take;

            if ($searchCard != null && $searchCard != "null") {
                $cards = Card::where('id', $searchCard)->with('details')->skip($skip)->take($take)->get();
                $totalCards = Card::where('id', $searchCard)->count();
                UserSearch::create(['card_id' => $searchCard]);
            } else {
                if ($items['card_ids'] != null) {
                    $cardCombinedIds = array_merge($items['cards'], $items['card_ids']->toArray());
                } else {
                    $cardCombinedIds = $items['cards'];
                }
                $cards = Card::whereIn('id', $cardCombinedIds)->with('details')->skip($skip)->take($take)->get();
                $totalCards = Card::whereIn('id', $cardCombinedIds)->count();
                if (!empty($request->input('search')) && $request->input('search') != null) {
                    UserSearch::create(['search' => $request->input('search')]);
                }
            }
            foreach ($cards as $ind => $card) {
                $sx_data = CardSales::getSxAndLastSx($card->id);
                $sx = $sx_data['sx'];
                $lastSx = $sx_data['lastSx'];

                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                $cards[$ind]['sx_icon'] = $sx_icon;
                $cards[$ind]['price'] = number_format((float) $sx, 2, '.', '');
                $cards[$ind]['sx_value'] = str_replace('-', '', number_format($sx - $lastSx, 2, '.', ''));
            }
            $nextPage = $request->input('page', 1);
            if (($nextPage * $limit) < $totalCards) {
                $next = $nextPage + 1;
            } else {
                $next = false;
            }

            return response()->json(['status' => 200, 'items' => $items, 'cards' => $cards, 'next' => $next, 'totalCards' => $totalCards], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage() . ' - ' . $e->getLine(), 500);
        }
    }

    public function getItemsListForUser(Request $request) {
        try {
            $limit = 12;
            $filter = $request->input('filter', null);
            $searchCard = $request->input('searchCard', null);
            $page = $request->input('page', 1);

            if ($filter != null && $this->checkForAdvanceSearch($filter)) {
                $items = $this->_advanceSearch($request);
            } else {
                $items = $this->_basicSearch($request);
            }

            $take = $request->input('take', 12);
            // if ($page == 1)
            //     $take = $request->input('take', 12);
            // else
            //     $take = $request->input('take', 12);

            $skip = $take * $page;
            $skip = $skip - $take;

            if ($searchCard != null && $searchCard != "null") {
                $cards = Card::where('id', $searchCard)->with('details')->skip($skip)->take($take)->get();
                $totalCards = Card::where('id', $searchCard)->count();
                UserSearch::create(['card_id' => $searchCard, 'user_id' => auth()->user()->id]);
            } else {
                if ($items['card_ids'] != null) {
                    $cardCombinedIds = array_merge($items['cards'], $items['card_ids']->toArray());
                } else {
                    $cardCombinedIds = $items['cards'];
                }
                $cards = Card::whereIn('id', $cardCombinedIds)->with('details')->skip($skip)->take($take)->get();
                $totalCards = Card::whereIn('id', $cardCombinedIds)->count();
                if (!empty($request->input('search')) && $request->input('search') != null) {
                    UserSearch::create(['search' => $request->input('search'), 'user_id' => auth()->user()->id]);
                }
            }

            foreach ($cards as $ind => $card) {
                $sx_data = CardSales::getSxAndLastSx($card->id);
                $sx = $sx_data['sx'];
                $lastSx = $sx_data['lastSx'];
                $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';
                //                $data['sx_value'] = number_format((float) $sx, 2, '.', '');
                $cards[$ind]['sx_icon'] = $sx_icon;
                $cards[$ind]['price'] = number_format((float) $sx, 2, '.', '');
                $cards[$ind]['sx_value'] = str_replace('-', '', number_format($sx - $lastSx, 2, '.', ''));
            }
            // $totalCards = $cards->count();
            $nextPage = $request->input('page', 1);
            if (($nextPage * $limit) < $totalCards) {
                $next = $nextPage + 1;
            } else {
                $next = false;
            }
            $totalCardPages = 0;
            if ($totalCards > $limit) {
                $totalCardPages = ($totalCards / $limit);
                if ($totalCardPages % $limit) {
                    $totalCardPages = round($totalCardPages) + 1;
                }
            }

            return response()->json(['status' => 200, 'items' => $items, 'cards' => $cards, 'next' => $next, 'totalCardPages' => $totalCardPages, 'totalCards' => $totalCards], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage() . ' - ' . $e->getLine(), 500);
        }
    }

    public function getRecentAuctionList(Request $request) {
        try {
            $page = $request->input('page', 1);
            $take = $request->input('take', 30);
            $skip = $take * $page;
            $skip = $skip - $take;
            $search = $request->input('search', null);
            $filterBy = $request->input('filterBy', null);

            if ($filterBy == 'sx_high_to_low') {
                $data = CardsSx::whereHas('card.ebayItems', function($q) {
                            $q->where('status', 0);
                        })->latest('date')->groupBy('card_id')->get();
                $data = $data->sortByDesc('sx');
                $data = $data->values()->all();
            } elseif ($filterBy == 'sx_low_to_high') {
                $data = CardsSx::whereHas('card.ebayItems', function($q) {
                            $q->where('status', 0);
                        })->latest('date')->groupBy('card_id')->get();
                $data = $data->sortBy('sx');
                $data = $data->values()->all();
            }

            if (isset($data)) {
                for ($i = 0; $i < count($data); $i++) {
                    $cardIds[] = $data[$i]['card_id'];
                }
                $ids_ordered = implode(',', $cardIds);
                $items = EbayItems::whereIn('card_id', $cardIds)->with(['category', 'card', 'card.value', 'details', 'playerDetails', 'condition', 'sellerInfo', 'listingInfo', 'sellingStatus', 'shippingInfo', 'specifications'])
                        ->orderByRaw("FIELD(card_id, $ids_ordered)")
                        ->where(function ($q) use ($search, $request, $filterBy) {
                    if ($search != null) {
                        $q->where('title', 'like', '%' . $search . '%');
                    }
                    if ($filterBy == 'buy_it_now') {
                        $q->orWhereHas('listingInfo', function ($qq) {
                            $qq->where('listingType', '!=', 'Auction');
                        });
                    }
                });
                if ($filterBy == 'ending_soon') {
                    $date_one = Carbon::now();
                    $date_one->setTimezone('America/Los_Angeles');
                    $items = $items->where("listing_ending_at", ">", $date_one);
                }
                $items = $items->skip($skip)->take($take)->get();
            } else {
                $items = EbayItems::with(['category', 'card', 'card.value', 'details', 'playerDetails', 'condition', 'sellerInfo', 'listingInfo', 'sellingStatus', 'shippingInfo', 'specifications'])
                        ->where(function ($q) use ($search, $request, $filterBy) {
                    if ($search != null) {
                        $q->where('title', 'like', '%' . $search . '%');
                    }
                    if ($filterBy == 'buy_it_now') {
                        $q->orWhereHas('listingInfo', function ($qq) {
                            $qq->where('listingType', '!=', 'Auction');
                        });
                    }
                });
                if ($filterBy == 'ending_soon') {
                    $date_one = Carbon::now();
                    $date_one->setTimezone('America/Los_Angeles');
                    $items = $items->where("listing_ending_at", ">", $date_one);
                }
                $items = $items->where('status', 0)->orderBy('created_at', 'desc')->skip($skip)->take($take)->get();
            }


//            if ($filterBy == 'price_low_to_high') {
//                $items = $items->sortBy(function($query) {
//                    return ($query->sellingStatus ? $query->sellingStatus->currentPrice : null);
//                });
//            }

            $items = $items->map(function($item, $key) {
                $galleryURL = $item->galleryURL;
                if ($item->pictureURLLarge != null) {
                    $galleryURL = $item->pictureURLLarge;
                } else if ($item->pictureURLSuperSize != null) {
                    $galleryURL = $item->pictureURLSuperSize;
                } else if ($galleryURL == null) {
                    $galleryURL = $this->defaultListingImage;
                }

                $listingTypeval = ($item->listingInfo ? $item->listingInfo->listingType : '');
                date_default_timezone_set("America/Los_Angeles");
                $datetime1 = new \DateTime($item->listing_ending_at);
                $datetime2 = new \DateTime('now');
                $interval = $datetime1->diff($datetime2);
                if ($interval->invert == 1) {
                    $days = $interval->format('%d');
                    $hours = $interval->format('%h');
                    $mins = $interval->format('%i');
                    $secs = $interval->format('%s');
                    if ($days > 0) {
                        $timeleft = $days . 'd ' . $hours . 'h';
                    } else if ($hours >= 1) {
                        $timeleft = $hours . 'h ' . $mins . 'm';
                    } else if ($mins >= 1) {
                        $timeleft = $mins . 'm ' . $secs . 's';
                    } else {
                        $timeleft = $secs . 's';
                    }
                } else {
                    $timeleft = '0s';
                }

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
                    'showBuyNow' => ($listingTypeval == 'AuctionWithBIN') ? true : false,
                    'data' => $item,
                    'sx_value' => number_format((float) $sx, 2, '.', ''),
                    'sx_icon' => (($sx - $lastSx) >= 0) ? 'up' : 'down',
                    'price_diff' => $price_diff,
                    'timeleft' => $timeleft,
                ];
            });
//            if ($filterBy == 'price_low_to_high') {
//                $items = $items->values()->all();
//            }

            if ($filterBy == 'sx_high_to_low') {
                $items = $items->sortByDesc('sx_value');
                $items = $items->values()->all();
            }
            if ($filterBy == 'sx_low_to_high') {
                $items = $items->sortBy('sx_value');
                $items = $items->values()->all();
            }
            return response()->json(['status' => 200, 'next' => ($page + 1), 'items' => $items], 200);
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
        $cards = EbayItems::whereHas('card.sales')->whereIn('itemId', $itemIds)->pluck('card_id')->unique()->values()->toArray();

        // $search = $request->input('search', null);
        $cardsId = null;
        if ($filter['player'] != null) {
            $search = $filter['player'];
            $cardsId = Card::whereHas('sales')->where(function($q) use ($search) {
                        $search = explode(' ', $search);
                        foreach ($search as $key => $keyword) {
//                            $q->orWhere('title', 'like', '%' . $keyword . '%');
                            $q->where('title', 'like', '%' . $keyword . '%');
                        }
                    })->distinct('player')->where('active', 1)->pluck('id');
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

        $ebayitems = EbayItems::whereHas('card.sales')->with(['category', 'card', 'card.value', 'details', 'playerDetails', 'condition', 'sellerInfo', 'listingInfo', 'sellingStatus', 'shippingInfo', 'specifications'])->where(function ($q) use ($filter, $itemsIds, $filterBy, $cards) {
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
            $date_one = Carbon::now();
            $date_one->setTimezone('America/Los_Angeles');
            // $date_two = Carbon::now()->setTimezone('UTC');
            $ebayitems = $ebayitems->where("listing_ending_at", ">", $date_one); //->where("listing_ending_at", "<", $date_one);
            $ebayitems = $ebayitems->where('status', 0)->orderBy('listing_ending_at', 'asc')->get();
        } else {
            $ebayitems = $ebayitems->where('status', 0)->orderBy('updated_at', 'desc')->get();
        }
//        if ($filterBy == 'price_low_to_high') {
//            $ebayitems = $ebayitems->sortBy(function($query) {
//                return $query->sellingStatus->currentPrice;
//            });
//        }

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

            $sx_data = CardSales::getSxAndLastSx($item->card_id);
            $sx = $sx_data['sx'];
            $lastSx = $sx_data['lastSx'];
            $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';

            date_default_timezone_set("America/Los_Angeles");
            $datetime1 = new \DateTime($item->listing_ending_at);
            $datetime2 = new \DateTime('now');
            $interval = $datetime1->diff($datetime2);
            if ($interval->invert == 1) {
                $days = $interval->format('%d');
                $hours = $interval->format('%h');
                $mins = $interval->format('%i');
                $secs = $interval->format('%s');
                if ($days > 0) {
                    $timeleft = $days . 'd ' . $hours . 'h';
                } else if ($hours >= 1) {
                    $timeleft = $hours . 'h ' . $mins . 'm';
                } else if ($mins >= 1) {
                    $timeleft = $mins . 'm ' . $secs . 's';
                } else {
                    $timeleft = $secs . 's';
                }
            } else {
                $timeleft = '0s';
            }

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
                'sx_icon' => $sx_icon,
                'sx_value' => number_format((float) $sx, 2, '.', ''),
                'price_diff' => str_replace('-', '', number_format($sx - $lastSx, 2, '.', '')),
                'timeleft' => $timeleft,
            ];
        });

        return ['data' => $ebayitems, 'next' => ($page + 1), 'cards' => $cardsIds, 'card_ids' => $cardsId];
    }

    private function _basicSearch($request) {
        $cardsIds = [];
        $search = $request->input('search', null);
        $page = $request->input('page', 1);

        $take = $request->input('take', 18);
        // if ($page == 1)
        //     $take = $request->input('take', 30);
        // else
        //     $take = $request->input('take', 18);

        $searchCard = $request->input('searchCard', null);
        $filterBy = $request->input('filterBy', null);
        $skip = $take * $page;
        $skip = $skip - $take;
        $cardsId = null;
        if ($search != null) {
            $cardsId = Card::whereHas('sales')->where(function($q) use ($search) {
                        $search = explode(' ', $search);
                        foreach ($search as $key => $keyword) {
//                            $q->orWhere('title', 'like', '%' . $keyword . '%');
                            $q->where('title', 'like', '%' . $keyword . '%');
                        }
                    })->distinct('player')->where('active', 1)->pluck('id');
        }
        $items = EbayItems::whereHas('card.sales')->with(['category', 'card', 'card.value', 'details', 'playerDetails', 'condition', 'sellerInfo', 'listingInfo', 'sellingStatus', 'shippingInfo', 'specifications'])->where(function ($q) use ($cardsId, $searchCard, $filterBy) {
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
            $date_one = Carbon::now();
            $date_one->setTimezone('America/Los_Angeles');
            // $date_two = Carbon::now()->setTimezone('UTC');
            $items = $items->where("listing_ending_at", ">", $date_one); //->where("listing_ending_at", "<", $date_one);
            $items = $items->where('status', 0)->orderBy('listing_ending_at', 'asc')->get();
        } else {
            $items = $items->where('status', 0)->orderBy('updated_at', 'desc')->get();
        }
//        if ($filterBy == 'price_low_to_high') {
//            $items = $items->sortBy(function($query) {
//                return $query->sellingStatus->currentPrice;
//            });
//        }

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
            $sx_data = CardSales::getSxAndLastSx($item->card_id);
            $sx = $sx_data['sx'];
            $lastSx = $sx_data['lastSx'];
            $sx_icon = (($sx - $lastSx) >= 0) ? 'up' : 'down';

            date_default_timezone_set("America/Los_Angeles");
            $datetime1 = new \DateTime($item->listing_ending_at);
            $datetime2 = new \DateTime('now');
            $interval = $datetime1->diff($datetime2);
            if ($interval->invert == 1) {
                $days = $interval->format('%d');
                $hours = $interval->format('%h');
                $mins = $interval->format('%i');
                $secs = $interval->format('%s');
                if ($days > 0) {
                    $timeleft = $days . 'd ' . $hours . 'h';
                } else if ($hours >= 1) {
                    $timeleft = $hours . 'h ' . $mins . 'm';
                } else if ($mins >= 1) {
                    $timeleft = $mins . 'm ' . $secs . 's';
                } else {
                    $timeleft = $secs . 's';
                }
            } else {
                $timeleft = '0s';
            }

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
                'sx_icon' => $sx_icon,
                'sx_value' => number_format((float) $sx, 2, '.', ''),
                'price_diff' => str_replace('-', '', number_format($sx - $lastSx, 2, '.', '')),
                'timeleft' => $timeleft,
            ];
        });

        return ['data' => $items, 'next' => ($page + 1), 'cards' => $cardsIds, 'card_ids' => $cardsId];
    }

    public function createEbayItemForAdmin(Request $request) {
        try {
            \DB::beginTransaction();
            $data = $request->all();
            $data['card_id'] = ($data['card_id'] == "null" ? null : $data['card_id']);
            $item = EbayItems::where('itemId', $data['itemId'])->first();
            if ($item == null) {
                $cat_id = 1;
                if (isset($data['details']['PrimaryCategoryID'])) {
                    $cat_id = EbayItemCategories::where('categoryId', $data['details']['PrimaryCategoryID'])->first()['id'];
                } else {
                    if (isset($data['category'])) {
                        $cat_id = $data['category'];
                    } else {
                        $card_details = Card::where('id', $data['card_id'])->select('sport')->first();
                        $allCat = SportsQueue::get();
                        $cat = [];
                        foreach ($allCat as $key => $c) {
                            $cat[$c->sport] = $c->id;
                        }

                        // $cat = array(
                        //     'Football' => '1',
                        //     'Baseball' => '2',
                        //     'Basketball' => '3',
                        //     'Soccer' => '4',
                        //     'Pokémon' => '10',
                        //     'Hockey' => '11',
                        // );
                        if (!empty($card_details->sport)) {
                            $cat_id = $cat[ucfirst($card_details->sport)];
                        }
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
                    date_default_timezone_set("America/Los_Angeles");
                    $auction_end_str = $data['auction_end'] / 1000;
                    $auction_end = date('Y-m-d H:i:s', $auction_end_str);
                }
                $listing_type = 'Listing';
                if (isset($data['listing_type']) && !empty($data['listing_type'])) {
                    $listing_type = ($data['listing_type'] == true ? 'Auction' : 'Listing');
                } else if (isset($data['details']['auction']) && !empty($data['details']['auction'])) {
                    $listing_type = ($data['details']['auction'] == true ? 'Auction' : 'Listing');
                }
                if (!empty($data['price'])) {
                    $selling_status = EbayItemSellingStatus::create([
                                'itemId' => $data['details']['ebay_id'],
                                'currentPrice' => $data['price'],
                                'convertedCurrentPrice' => $data['price'],
                                'sellingState' => $data['price'],
                                'timeLeft' => $auction_end,
                    ]);
                }
                if (array_key_exists('seller_name', $data) && !empty($data['seller_name'])) {
                    $seller_info = EbayItemSellerInfo::create([
                                'itemId' => $data['details']['ebay_id'],
                                'sellerUserName' => $data['seller_name'],
                                'positiveFeedbackPercent' => $data['positiveFeedbackPercent'],
                                'seller_contact_link' => $data['seller_contact_link'],
                                'seller_store_link' => $data['seller_store_link']
                    ]);
                }
                $listing_info = EbayItemListingInfo::create([
                            'itemId' => $data['details']['ebay_id'],
                            'startTime' => '',
                            'endTime' => $auction_end,
                            'listingType' => $listing_type,
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
                if (array_key_exists('specifics', $data) && !empty($data['specifics'])) {
                    foreach ($data['specifics'] as $key => $speci) {
                        if (isset($speci['Value'])) {
                            if ($speci['Value'] != "N/A") {
                                EbayItemSpecific::create([
                                    'itemId' => $data['details']['ebay_id'],
                                    'name' => $speci['Name'],
                                    'value' => is_array($speci['Value']) ? implode(', ', $speci['Value']) : $speci['Value']
                                ]);
                            }
                        } else {
                            EbayItemSpecific::create([
                                'itemId' => $data['details']['ebay_id'],
                                'name' => $key,
                                'value' => is_array($speci) ? implode(', ', $speci) : $speci
                            ]);
                        }
                    }
                }
                \DB::commit();
                return response()->json(['status' => 200, 'data' => ['message' => 'Added successfully.']], 200);
            } else {
                $cat_id = 1;
                if (isset($data['details']['PrimaryCategoryID'])) {
                    $cat_id = EbayItemCategories::where('categoryId', $data['details']['PrimaryCategoryID'])->first()['id'];
                } else {
                    if (isset($data['category'])) {
                        $cat_id = $data['category'];
                    } else {
                        $card_details = Card::where('id', $data['card_id'])->select('sport')->first();
                        $allCat = SportsQueue::get();
                        $cat = [];
                        foreach ($allCat as $key => $c) {
                            $cat[$c->sport] = $c->id;
                        }

                        // dd($cat);
                        // $cat = array(
                        //     'Football' => '1',
                        //     'Baseball' => '2',
                        //     'Basketball' => '3',
                        //     'Soccer' => '4',
                        //     'Pokémon' => '10',
                        //     'Hockey' => '11',
                        // );
                        if (!empty($card_details->sport)) {
                            $cat_id = $cat[ucfirst($card_details->sport)];
                        }
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
                    date_default_timezone_set("America/Los_Angeles");
                    $auction_end_str = $data['auction_end'] / 1000;
                    $auction_end = date('Y-m-d H:i:s', $auction_end_str);
                }
                $listing_type = 'Listing';
                if (isset($data['listing_type']) && !empty($data['listing_type'])) {
                    $listing_type = ($data['listing_type'] == true ? 'Auction' : 'Listing');
                } else if (isset($data['details']['auction']) && !empty($data['details']['auction'])) {
                    $listing_type = ($data['details']['auction'] == true ? 'Auction' : 'Listing');
                }

                if (!empty($item['selling_status_id'])) {
                    (EbayItemSellingStatus::where('id', $item['selling_status_id'])->first())->update([
                        'currentPrice' => $data['price'],
                        'convertedCurrentPrice' => $data['price'],
                        'sellingState' => $data['price'],
                        'timeLeft' => $auction_end,
                    ]);
                }
                if (array_key_exists('seller_name', $data) && !empty($data['seller_name'])) {
                    $seller_info = EbayItemSellerInfo::where('id', $item['seller_info_id'])->first();
                    if (!empty($seller_info)) {
                        $seller_info->update([
                            'sellerUserName' => $data['seller_name'],
                            'positiveFeedbackPercent' => $data['positiveFeedbackPercent'],
                            'seller_contact_link' => $data['seller_contact_link'],
                            'seller_store_link' => $data['seller_store_link']
                        ]);
                    }
                }
                $listing_info = EbayItemListingInfo::where('id', $item['listing_info_id'])->first();
                if (!empty($listing_info)) {
                    $listing_info->update([
                        'startTime' => '',
                        'endTime' => $auction_end,
                        'listingType' => $listing_type,
                    ]);
                }

                $ebayItem = EbayItems::where('id', $item['id'])->first();
                if (!empty($ebayItem)) {
                    $ebayItem->update([
                        'title' => $data['title'],
                        'card_id' => $data['card_id'],
                        'viewItemURL' => isset($data['web_link']) ? $data['web_link'] : null,
                        'location' => isset($data['Location']) ? $data['Location'] : null,
                        'returnsAccepted' => isset($data['ReturnPolicy']) ? $data['ReturnPolicy'] : false,
                        'pictureURLLarge' => $pictureURLLarge,
                        'pictureURLSuperSize' => $pictureURLSuperSize,
                        'listing_ending_at' => $auction_end,
                        'status' => 0,
                    ]);
                }
                if (array_key_exists('specifics', $data) && !empty($data['specifics'])) {
                    foreach ($data['specifics'] as $key => $speci) {
                        if (isset($speci['Value'])) {
                            if ($speci['Value'] != "N/A") {
                                EbayItemSpecific::where('itemId', $data['details']['ebay_id'])
                                        ->where('name', $speci['Name'])
                                        ->update([
                                            'value' => is_array($speci['Value']) ? implode(', ', $speci['Value']) : $speci['Value']
                                ]);
                            }
                        } else {
                            EbayItemSpecific::where('itemId', $data['details']['ebay_id'])
                                    ->where('name', $key)
                                    ->update([
                                        'value' => is_array($speci) ? implode(', ', $speci) : $speci
                            ]);
                        }
                    }
                }
                \DB::commit();
                return response()->json(['status' => 200, 'data' => ['message' => 'Updated successfully.']], 200);
            }
        } catch (\Exception $e) {
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
//        $page = $request->input('page', 1);
//        $take = $request->input('take', 30);
//        $skip = $take * $page;
//        $skip = $skip - $take;
        try {
//            $data = UserSearch::with(['userDetails', 'cardDetails'])->skip($skip)->take($take)->get();
//            return response()->json(['status' => 200, 'data' => $data, 'next' => ($page + 1)], 200);
            $data = UserSearch::with(['userDetails', 'cardDetails'])->orderBy('created_at', 'desc')->get();
            return response()->json(['status' => 200, 'data' => $data], 200);
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

            $limit = 18;
            $nextPage = $request->input('page', 1);
            if (($nextPage * $limit) < count($cards)) {
                $next = $nextPage + 1;
            } else {
                $next = false;
            }

            return response()->json(['status' => 200, 'items' => $items, 'cards' => $cards, 'next' => $next, 'totalCards' => count($cards), 'totalItems' => $items['totalItems']], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    private function _internalBasicSearch($request) {
        $cardsIds = [];
        $search = $request->input('search', null);
        $page = $request->input('page', 1);

        if ($page == 1)
            $take = $request->input('take', 30);
        else
            $take = $request->input('take', 18);

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
                $date_one = Carbon::now();
                $date_one->setTimezone('America/Los_Angeles');
                // $date_two = Carbon::now()->setTimezone('UTC');
                $items = $items->where("listing_ending_at", ">", $date_one); //->where("listing_ending_at", "<", $date_one);
            }
        }
        $items = $items->where('status', 0)->orderBy('updated_at', 'desc')->get();
        $totalItems = count($items);
//        if ($filterBy != null && $filterBy == 'price_low_to_high') {
//            $items = $items->sortBy(function($query) {
//                return $query->sellingStatus->currentPrice;
//            });
//        }
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

            date_default_timezone_set("America/Los_Angeles");
            $datetime1 = new \DateTime($item->listing_ending_at);
            $datetime2 = new \DateTime('now');
            $interval = $datetime1->diff($datetime2);
            if ($interval->invert == 1) {
                $days = $interval->format('%d');
                $hours = $interval->format('%h');
                $mins = $interval->format('%i');
                $secs = $interval->format('%s');
                if ($days > 0) {
                    $timeleft = $days . 'd ' . $hours . 'h';
                } else if ($hours >= 1) {
                    $timeleft = $hours . 'h ' . $mins . 'm';
                } else if ($mins >= 1) {
                    $timeleft = $mins . 'm ' . $secs . 's';
                } else {
                    $timeleft = $secs . 's';
                }
            } else {
                $timeleft = '0s';
            }

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
                'timeleft' => $timeleft,
            ];
        });
        return ['data' => $items, 'next' => ($page + 1), 'cards' => $cardsIds, 'totalItems' => $totalItems];
    }

    public function getItemsDetails(Request $request) {
        try {
            $data['items'] = EbayItems::where('id', $request->input('id'))
                    ->with(['category', 'card', 'card.value', 'details', 'playerDetails', 'condition', 'sellerInfo', 'listingInfo', 'sellingStatus', 'shippingInfo', 'specifications'])
                    ->first();
            date_default_timezone_set("America/Los_Angeles");
            $datetime1 = new \DateTime($data['items']->listing_ending_at);
            $datetime2 = new \DateTime('now');
            $interval = $datetime1->diff($datetime2);
            if ($interval->invert == 1) {
                $data['items']['time_days'] = $days = $interval->format('%d');
                $data['items']['time_hours'] = $hours = $interval->format('%h');
                $data['items']['time_mins'] = $mins = $interval->format('%i');
                $data['items']['time_secs'] = $secs = $interval->format('%s');
                if ($days > 0) {
                    $timeleft = $days . 'd ' . $hours . 'h';
                } else if ($hours >= 1) {
                    $timeleft = $hours . 'h ' . $mins . 'm';
                } else if ($mins >= 1) {
                    $timeleft = $mins . 'm ' . $secs . 's';
                } else {
                    $timeleft = $secs . 's';
                }
            } else {
                $data['items']['time_days'] = 0;
                $data['items']['time_hours'] = 0;
                $data['items']['time_mins'] = 0;
                $data['items']['time_secs'] = 0;
                $timeleft = '0s';
            }
            $data['timeleft'] = $timeleft;
            $data['items']['time_now'] = date('Y-m-d H:i:s');
            if (!empty($data['items']->card_id)) {
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
            } else {
                $data['sx_value'] = 0;
                $data['sx'] = null;
                $data['sx_icon'] = '';
            }
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

            $items = EbayItems::with(['sellingStatus', 'card', 'card.value', 'listingInfo'])->where('id', '!=', $id)->where(function ($q) use ($card_id, $search, $request, $filterBy) {
                if ($card_id != null) {
                    $q->where('card_id', $card_id);
                }
                if ($request->has('sport') && $request->input('sport') != null) {
                    $q->orWhereHas('card', function ($qq) use ($request) {
                        $qq->where('sport', $request->input('sport'));
                    });
                }
                if ($search != null) {
                    $q->where('title', 'like', '%' . $search . '%');
                }
                if ($filterBy == 'buy_it_now') {
                    $q->orWhereHas('listingInfo', function ($qq) {
                        $qq->where('listingType', '!=', 'Auction');
                    });
                }
            });

//            if ($filterBy == 'ending_soon') {
            $date_one = Carbon::now();
            $date_one->setTimezone('America/Los_Angeles');
            $items = $items->where("listing_ending_at", ">", $date_one);
//            }

            $items = $items->where('status', 0)->orderBy('listing_ending_at', 'asc')->get();

//            if ($filterBy != null && $filterBy == 'price_low_to_high') {
//                $items = $items->sortBy(function($query) {
//                    return $query->sellingStatus->currentPrice;
//                });
//            }
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

                date_default_timezone_set("America/Los_Angeles");
                $datetime1 = new \DateTime($item->listing_ending_at);
                $datetime2 = new \DateTime('now');
                $interval = $datetime1->diff($datetime2);
                if ($interval->invert == 1) {
                    $days = $interval->format('%d');
                    $hours = $interval->format('%h');
                    $mins = $interval->format('%i');
                    $secs = $interval->format('%s');
                    if ($days > 0) {
                        $timeleft = $days . 'd ' . $hours . 'h';
                    } else if ($hours >= 1) {
                        $timeleft = $hours . 'h ' . $mins . 'm';
                    } else if ($mins >= 1) {
                        $timeleft = $mins . 'm ' . $secs . 's';
                    } else {
                        $timeleft = $secs . 's';
                    }
                } else {
                    $timeleft = '0s';
                }

                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'galleryURL' => $galleryURL,
                    'price' => ($item->sellingStatus ? $item->sellingStatus->price : 0),
                    'itemId' => $item->itemId,
                    'viewItemURL' => $item->viewItemURL,
                    'listing_ending_at' => $item->listing_ending_at,
                    'timeleft' => $timeleft,
                    'showBuyNow' => ($listingTypeVal != 'Auction') ? true : false,
                    'data' => $item,
                    'sx_value' => number_format((float) $sx, 2, '.', ''),
                    'sx_icon' => (($sx - $lastSx) >= 0) ? 'up' : 'down',
                    'price_diff' => $price_diff,
                ];
            });

//            if ($filterBy == 'price_low_to_high') {
//                $items = $items->values()->all();
//            }

            if ($filterBy == 'sx_high_to_low') {
                $items = $items->sortByDesc('sx_value');
                $items = $items->values()->all();
            }

            return response()->json(['status' => 200, 'data' => $items, 'next' => ($page + 1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    //Function needs to be faster
    //sports needs to be removed from $advanceSearchData
    public function getAdanceSearchData(Request $request) {
        try {
            $last_entry = AdvanceSearchOptions::latest()->first();
            $previous_date = date('Y-m-d H:i:s', strtotime('-3 days'));

            if (!empty($last_entry) && $last_entry['updated_at'] < $previous_date) {
                $this->updateAdanceSearchOptions($request);
            }
            if (empty($last_entry)) {
                $this->updateAdanceSearchOptions($request);
            }
            $advanceSearchData = [
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
            $sportsList = AppSettings::select('sports')->first();
            json_decode($sportsList);
            $advanceSearchData["sport"] = $sportsList->sports;

            return response()->json(['status' => 200, 'data' => $advanceSearchData], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function updateAdanceSearchOptions(Request $request) {
        try {
            $advanceSearchData = [];
            $includeParams = ['Year', 'Year:', 'Card Manufacturer', 'Card Manufacturer:', 'Series', 'Series:', 'Grade', 'Grade:', 'Product', 'Product:', 'Team', 'Team:', 'Season', 'Season:'];
            $specifics = EbayItemSpecific::whereIn('name', $includeParams)->select('name')->groupBy('name')->get();
            foreach ($specifics->toArray() as $key => $value) {
                $index = str_replace(array('/', ' ', ':'), array('', '', ''), strtolower($value['name']));
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
            return response()->json($e->getMessage(), 500);
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
            if ($filterBy == 'sx_high_to_low') {
                $data = CardsSx::whereHas('card.ebayItems', function($q) {
                            $q->where('status', 0);
                        })->latest('date')->groupBy('card_id')->get();
                $data = $data->sortByDesc('sx');
                $data = $data->values()->all();
            } elseif ($filterBy == 'sx_low_to_high') {
                $data = CardsSx::whereHas('card.ebayItems', function($q) {
                            $q->where('status', 0);
                        })->latest('date')->groupBy('card_id')->get();
                $data = $data->sortBy('sx');
                $data = $data->values()->all();
            }

            if (isset($data)) {
                for ($i = 0; $i < count($data); $i++) {
                    $cardIds[] = $data[$i]['card_id'];
                }
                $ids_ordered = implode(',', $cardIds);
                $items = EbayItems::whereIn('card_id', $cardIds)->with(['sellingStatus', 'card', 'card.value', 'listingInfo'])
                        ->orderByRaw("FIELD(card_id, $ids_ordered)")
                        ->where(function ($q) use ($search, $request, $filterBy) {
                    if ($request->has('sport') && $request->input('sport') != null && $request->input('sport') != 'random bin') {
                        $q->orWhereHas('card', function ($qq) use ($request) {
                            $qq->where('sport', $request->input('sport'));
                        });
                    }
                    if ($request->input('sport') == 'random bin') {
                        $q->orWhere('is_random_bin', 1);
                    }
                    if ($search != null) {
                        $q->where('title', 'like', '%' . $search . '%');
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
                //Intentionally making all listing ending_soon
//            if ($filterBy == 'ending_soon') {
                $date_one = Carbon::now();
                $date_one->setTimezone('America/Los_Angeles');
                $items = $items->where("listing_ending_at", ">", $date_one);
//            }
                $items = $items->skip($skip)->take($take)->get();
            } else {
                $items = EbayItems::with(['sellingStatus', 'card', 'card.value', 'listingInfo'])->where(function ($q) use ($search, $request, $filterBy) {
                    if ($request->has('sport') && $request->input('sport') != null && $request->input('sport') != 'random bin') {
                        $q->orWhereHas('card', function ($qq) use ($request) {
                            $qq->where('sport', $request->input('sport'));
                        });
                    }
                    if ($request->input('sport') == 'random bin') {
                        $q->orWhere('is_random_bin', 1);
                    }
                    if ($search != null) {
                        $q->where('title', 'like', '%' . $search . '%');
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
                    $date_one = Carbon::now();
                    $date_one->setTimezone('America/Los_Angeles');
                    $items = $items->where("listing_ending_at", ">", $date_one);
//                            ->orWhere("listing_ending_at", "=", null);
                }
                //Intentionally making all listing ending_soon
//            if ($filterBy == 'ending_soon') {
                // dd($items->get());
//            }
//                if ($request->input('sport') != 'random bin') {
//                    $items = $items->where('status', 0)->orderBy('listing_ending_at', 'asc')->skip($skip)->take($take)->get();
//                } else {
                //Order by listing_ending_at and after that buy it now listing
                $items = $items->where('status', 0)
                                ->orderBy(DB::raw('ISNULL(listing_ending_at), listing_ending_at'), 'ASC')
                                ->skip($skip)->take($take)->get();
//                }
            }


//            if ($filterBy == 'price_low_to_high') {
//                $items = $items->sortBy(function($query) {
//                    return ($query->sellingStatus ? $query->sellingStatus->currentPrice : null);
//                });
//            }

            $items = $items->map(function($item, $key) {
                $galleryURL = $item->galleryURL;
                if ($item->pictureURLLarge != null) {
                    $galleryURL = $item->pictureURLLarge;
                } else if ($item->pictureURLSuperSize != null) {
                    $galleryURL = $item->pictureURLSuperSize;
                } else if ($galleryURL == null) {
                    $galleryURL = $this->defaultListingImage;
                }
                $listingTypeVal = ($item->listingInfo ? $item->listingInfo->listingType : '');
                date_default_timezone_set("America/Los_Angeles");
                $datetime1 = new \DateTime($item->listing_ending_at);
                $datetime2 = new \DateTime('now');
                $interval = $datetime1->diff($datetime2);
                if ($interval->invert == 1) {
                    $days = $interval->format('%d');
                    $hours = $interval->format('%h');
                    $mins = $interval->format('%i');
                    $secs = $interval->format('%s');
                    if ($days > 0) {
                        $timeleft = $days . 'd ' . $hours . 'h';
                    } else if ($hours >= 1) {
                        $timeleft = $hours . 'h ' . $mins . 'm';
                    } else if ($mins >= 1) {
                        $timeleft = $mins . 'm ' . $secs . 's';
                    } else {
                        $timeleft = $secs . 's';
                    }
                } else {
                    $timeleft = '0s';
                }
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
                    'showBuyNow' => ($listingTypeVal == 'AuctionWithBIN') ? true : false,
                    'data' => $item,
                    'sx_value' => number_format((float) $sx, 2, '.', ''),
                    'sx_icon' => (($sx - $lastSx) >= 0) ? 'up' : 'down',
                    'price_diff' => $price_diff,
                    'timeleft' => $timeleft,
                ];
            });

//            if ($filterBy == 'price_low_to_high') {
//                $items = $items->values()->all();
//            }
            if ($filterBy == 'sx_high_to_low') {
                $items = $items->sortByDesc('sx_value');
                $items = $items->values()->all();
            }
            if ($filterBy == 'sx_low_to_high') {
                $items = $items->sortBy('sx_value');
                $items = $items->values()->all();
            }
            return response()->json(['status' => 200, 'data' => $items, 'next' => ($page + 1)], 200);
        } catch (\Exception $e) {
            return response()->json($e->getMessage(), 500);
        }
    }

    public function getEndingSoonList(Request $request) {
        $page = $request->input('page', 1);
        $take = $request->input('take', 30);
        $search = $request->input('search', null);
        $filterBy = $request->input('filterBy', null);
        $skip = $take * $page;
        $skip = $skip - $take;
        try {
            $date_one = Carbon::now();
            $date_one->setTimezone('America/Los_Angeles');
            if ($filterBy == 'sx_high_to_low') {
                $data = CardsSx::whereHas('card.ebayItems', function($q) {
                            $q->where('status', 0);
                        })->latest('date')->groupBy('card_id')->get();
                $data = $data->sortByDesc('sx');
                $data = $data->values()->all();
            } elseif ($filterBy == 'sx_low_to_high') {
                $data = CardsSx::whereHas('card.ebayItems', function($q) {
                            $q->where('status', 0);
                        })->latest('date')->groupBy('card_id')->get();
                $data = $data->sortBy('sx');
                $data = $data->values()->all();
            }
            if (isset($data)) {
                for ($i = 0; $i < count($data); $i++) {
                    $cardIds[] = $data[$i]['card_id'];
                }
                $ids_ordered = implode(',', $cardIds);
                $items = EbayItems::whereIn('card_id', $cardIds)->with(['sellingStatus', 'card', 'card.value', 'listingInfo'])
                        ->orderByRaw("FIELD(card_id, $ids_ordered)")
                        ->where("listing_ending_at", ">", $date_one)
                        ->where(function ($q) use ($search, $request, $filterBy) {
                    if ($search != null) {
                        $q->where('title', 'like', '%' . $search . '%');
                    }
                    if ($filterBy == 'buy_it_now') {
                        $q->orWhereHas('listingInfo', function ($qq) {
                            $qq->where('listingType', '!=', 'Auction');
                        });
                    }
                });
                if ($filterBy == 'ending_soon') {
                    $date_one = Carbon::now();
                    $date_one->setTimezone('America/Los_Angeles');
                    $items = $items->where("listing_ending_at", ">", $date_one);
                }
                $items = $items->skip($skip)->take($take)->get();
            } else {
                $items = EbayItems::with(['sellingStatus', 'card', 'card.value', 'listingInfo'])->where("listing_ending_at", ">", $date_one)
                        ->where(function ($q) use ($search, $request, $filterBy) {
                    if ($search != null) {
                        $q->where('title', 'like', '%' . $search . '%');
                    }
                    if ($filterBy == 'buy_it_now') {
                        $q->orWhereHas('listingInfo', function ($qq) {
                            $qq->where('listingType', '!=', 'Auction');
                        });
                    }
                });
                if ($filterBy == 'ending_soon') {
                    $date_one = Carbon::now();
                    $date_one->setTimezone('America/Los_Angeles');
                    $items = $items->where("listing_ending_at", ">", $date_one);
                }
                $items = $items->where('status', 0)->orderBy('listing_ending_at', 'asc')->skip($skip)->take($take)->get();
            }


//            if ($filterBy == 'price_low_to_high') {
//                $items = $items->sortBy(function($query) {
//                    return ($query->sellingStatus ? $query->sellingStatus->currentPrice : null);
//                });
//            }

            $items = $items->map(function($item, $key) {
                $galleryURL = $item->galleryURL;
                if ($item->pictureURLLarge != null) {
                    $galleryURL = $item->pictureURLLarge;
                } else if ($item->pictureURLSuperSize != null) {
                    $galleryURL = $item->pictureURLSuperSize;
                } else if ($galleryURL == null) {
                    $galleryURL = $this->defaultListingImage;
                }
                $listingTypeVal = ($item->listingInfo ? $item->listingInfo->listingType : '');
                date_default_timezone_set("America/Los_Angeles");
                $datetime1 = new \DateTime($item->listing_ending_at);
                $datetime2 = new \DateTime('now');
                $interval = $datetime1->diff($datetime2);
                if ($interval->invert == 1) {
                    $days = $interval->format('%d');
                    $hours = $interval->format('%h');
                    $mins = $interval->format('%i');
                    $secs = $interval->format('%s');
                    if ($days > 0) {
                        $timeleft = $days . 'd ' . $hours . 'h';
                    } else if ($hours >= 1) {
                        $timeleft = $hours . 'h ' . $mins . 'm';
                    } else if ($mins >= 1) {
                        $timeleft = $mins . 'm ' . $secs . 's';
                    } else {
                        $timeleft = $secs . 's';
                    }
                } else {
                    $timeleft = '0s';
                }

                $sx_data = CardSales::getSxAndLastSx($item->card_id);
                $sx = $sx_data['sx'];
                $lastSx = $sx_data['lastSx'];

                $price_diff = (float) str_replace('-', '', number_format((float) $sx - $lastSx, 2, '.', ''));
                return [
                    'id' => $item->id,
                    'title' => $item->title,
                    'galleryURL' => $galleryURL,
                    'price' => number_format((float) ($item->sellingStatus ? $item->sellingStatus->price : 0), 2, '.', ''),
                    'itemId' => $item->itemId,
                    'viewItemURL' => $item->viewItemURL,
                    'listing_ending_at' => $item->listing_ending_at,
                    'showBuyNow' => ($listingTypeVal == 'AuctionWithBIN') ? true : false,
                    'data' => $item,
                    'sx_value' => number_format((float) $sx, 2, '.', ''),
                    'sx_icon' => (($sx - $lastSx) >= 0) ? 'up' : 'down',
                    'price_diff' => $price_diff,
                    'timeleft' => $timeleft,
                ];
            });

//            if ($filterBy == 'price_low_to_high') {
//                $items = $items->values()->all();
//            }
            if ($filterBy == 'sx_high_to_low') {
                $items = $items->sortByDesc('sx_value');
                $items = $items->values()->all();
            }
            if ($filterBy == 'sx_low_to_high') {
                $items = $items->sortBy('sx_value');
                $items = $items->values()->all();
            }

            $AppSettings = AppSettings::first();
            // $order = ['basketball', 'soccer', 'baseball', 'football', 'pokemon'];
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

            $sportsList = AppSettings::select('sports')->first();
            json_decode($sportsList);

            return response()->json(['status' => 200, 'data' => $data, 'next' => ($page + 1), 'sportsList' => $sportsList->sports], 200);
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
//        $page = $request->input('page', 1);
//        $take = $request->input('take', 30);
//        $skip = $take * $page;
//        $skip = $skip - $take;
        try {
            $sp = SeeProblem::with(['user', 'ebay'])->orderBy('updated_at', 'desc')->get();
//            $sp = $sp->skip($skip)->take($take);
//            $next = 0;
//            if ($sp->count() > 0) {
//                $next = ($page + 1);
//            }
            return response()->json(['status' => 200, 'data' => $sp], 200);
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

    public function getItemsForStatus() {
        \DB::beginTransaction();
        try {
            Log::info('Start getItemsForStatus');
            $items = EbayItems::where(function($query) {
                        $query->where('sold_price', '')->orWhereNull('sold_price');
                    })
                    ->whereNull('item_status')
                    ->where(function ($q) {
                        $date_one = Carbon::now();
                        $date_one->setTimezone('America/Los_Angeles');
                        $q->where("listing_ending_at", "<", $date_one);
                    })
                    ->select('id', 'viewItemURL', 'item_status', 'manual_counter', "itemId", "card_id", "listing_info_id")
                    ->orderBy('listing_ending_at', 'asc')
                    ->take('50')
                    ->get();

//             dd($items->toArray());
            foreach ($items as $item) {
                $update = [];
                if (!empty($item->viewItemURL)) {
                    $script_link = '/home/' . env('SCRAP_USER') . '/ebay_short/ebayFetch/bin/python3 /home/' . env('SCRAP_USER') . '/ebay_short/core.py """' . $item->viewItemURL . '"""';
                    $scrap_response = shell_exec($script_link . " 2>&1");
                    $response = json_decode($scrap_response);
                } else {
                    $link = "https://www.ebay.com/itm/" . $item->itemId;
                    // $link = "https://www.ebay.com/itm/363433849426";
                    $script_link = '/home/' . env('SCRAP_USER') . '/ebay_short/ebayFetch/bin/python3 /home/' . env('SCRAP_USER') . '/ebay_short/core.py """' . $link . '"""';
                    $scrap_response = shell_exec($script_link . " 2>&1");
                    $response = json_decode($scrap_response);
                }
                if (!empty($response->timeLeft)) {
                    date_default_timezone_set("America/Los_Angeles");
                    $auction_end_str = $response->timeLeft / 1000;
                    $auction_end = date('Y-m-d H:i:s', $auction_end_str);
                }

                //checking if response has data from ebay
                if (empty($response->name)) {
                    if ($item->manual_counter >= 5) {
                        //Manual approval
                        $update = [
                            "item_status" => 3
                        ];
                    } else {
                        $update = [
                            "manual_counter" => $item->manual_counter + 1
                        ];
                    }
                    //Manual Approval
                } else if (!empty($response->status) && $response->status == "Item Ended") {
                    //rejected - update status item Ended 
                    $update = [
                        "item_status" => 2,
                        "listing_ending_at" => isset($auction_end) ? $auction_end : ""
                    ];
                } else if (!empty($response->status) && $response->status == "Item Sold") {
                    //Accepted - update status item Sold and add sold price with time
                    $soldPrice = substr($response->last_price, strpos($response->last_price, "$") + 1);
                    $soldPrice = str_replace(",", "", $soldPrice);

                    $update = [
                        "item_status" => 1,
                        "sold_price" => $soldPrice,
                        'status' => 2,
                        "listing_ending_at" => isset($auction_end) ? $auction_end : ""
                    ];

                    $item_type = null;
                    if ($item->listing_info_id != null) {
                        $ebayItemListingInfo = EbayItemListingInfo::where('id', $item->listing_info_id)->first();
                        $item_type = $ebayItemListingInfo->listingType;
                    }
                    $carbonNow = Carbon::create($auction_end);

                    if ($item->card_id == null) {
                        CardSales::create([
                            'timestamp' => isset($auction_end) ? $auction_end : "",
                            'quantity' => 1,
                            'cost' => $soldPrice,
                            'source' => 'Script', //Ebay
                            'type' => $item_type,
                            'ebay_items_id' => $item->id,
                        ]);
                    } else {
                        // $cardSport = Card::where('id', $item->card_id)->pluck('sport');

                        CardSales::create([
                            'card_id' => $item->card_id,
                            'timestamp' => isset($auction_end) ? $auction_end : "",
                            'quantity' => 1,
                            'cost' => $soldPrice,
                            'source' => 'Script', //Ebay
                            'type' => $item_type,
                            'ebay_items_id' => $item->id,
                        ]);

                        $saleDateYmd = $carbonNow->format('Y-m-d');
                        $cardSXExistingvalue = 0;

                        if (CardsSx::where('card_id', $item->card_id)->exists()) {
                            $cardSXExisting = CardsSx::where('card_id', $item->card_id)->orderBy('date', 'DESC')->first();
                            $cardExistingLatestSaleDate = $cardSXExisting->date;
                            $cardSXExistingvalue = $cardSXExisting->sx;
                        }

                        $cardsSxValue = CardSales::where('card_id', $item->card_id)->where('timestamp', 'like', '%' . $saleDateYmd . '%')->get();
                        if (CardsSx::where('card_id', $item->card_id)->where('date', $saleDateYmd)->exists()) {
                            CardsSx::where('card_id', $item->card_id)->where('date', $saleDateYmd)->update(['sx' => $cardsSxValue->avg('cost'), 'quantity' => $cardsSxValue->sum('quantity')]);
                        } else {
                            CardsSx::create([
                                'card_id' => $item->card_id,
                                'date' => $saleDateYmd,
                                'sx' => $soldPrice,
                                'quantity' => 1,
                            ]);
                        }
                        if (CardsTotalSx::where('date', $saleDateYmd)->exists()) {
                            $sxValue = CardSales::where('timestamp', 'like', '%' . $saleDateYmd . '%')->get();
                            CardsTotalSx::where('date', $saleDateYmd)->update(['amount' => $sxValue->avg('cost'), 'quantity' => $sxValue->sum('quantity')]);
                        } else {
                            CardsTotalSx::create([
                                'date' => $saleDateYmd,
                                'amount' => $soldPrice,
                                'quantity' => 1,
                            ]);
                        }
                        if (isset($cardExistingLatestSaleDate)) {
                            if ($saleDateYmd >= $cardExistingLatestSaleDate) {
                                $old_total_sx_value = AppSettings::select('total_sx_value')->first();
                                $changed_total_sx_value = ($old_total_sx_value->total_sx_value - $cardSXExistingvalue) + $cardsSxValue->avg('cost');

                                AppSettings::first()->update(["total_sx_value" => $changed_total_sx_value]);
                            }
                        }
                    }
                }
                if (isset($update))
                    EbayItems::where("id", $item->id)->update($update);
            }
            Log::info("End getItemsForStatus");
            \DB::commit();
        } catch (\Exception $e) {
            \DB::rollback();
            \Log::info($e->getMessage());
        }
    }

}
